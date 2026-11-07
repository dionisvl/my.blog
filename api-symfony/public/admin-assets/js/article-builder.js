/* Modular article builder (admin). Plain browser JS; every dynamic string is set with textContent/value. */
(function () {
    'use strict';

    var root = document.getElementById('article-builder');
    if (!root) { return; }

    var cfg = JSON.parse(document.getElementById('article-initial').textContent);
    var state = cfg.initial;
    var doc = state.document;
    if (!Array.isArray(doc.blocks)) { doc.blocks = []; }
    var dirty = false;
    var dirtyGeneration = 0;
    var pendingEditors = [];
    var T = cfg.labels;

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') { node.textContent = attrs[k]; } else if (k === 'class') { node.className = attrs[k]; } else { node.setAttribute(k, attrs[k]); }
        });
        (children || []).forEach(function (c) { if (c) { node.appendChild(c); } });
        return node;
    }
    function markDirty() {
        dirty = true;
        dirtyGeneration += 1;
        var s = document.getElementById('ab-status');
        s.textContent = T.unsaved;
        s.className = 'text-warning';
    }
    var counter = 0;
    function genId(prefix) {
        counter += 1;
        return prefix + '-' + Math.random().toString(36).slice(2, 6) + counter;
    }
    function group(label, control, help) {
        if (!control.id) { control.id = genId('field'); }
        return el('div', { class: 'form-group' }, [
            el('label', { text: label, for: control.id }), control, help ? el('p', { class: 'help-block', text: help }) : null
        ]);
    }

    var RATINGS = [['good', 'good'], ['mixed', 'mixed'], ['bad', 'bad'], ['neutral', 'neutral']];
    var RATINGS_OPT = [['', '—']].concat(RATINGS);
    var CTA_FIELDS = [
        { k: 'label', t: 'text', l: 'Label' }, { k: 'url', t: 'text', l: 'URL (https://…)' },
        { k: 'variant', t: 'select', l: 'Style', options: [['primary', 'primary'], ['ghost', 'ghost']] },
        { k: 'sponsored', t: 'bool', l: 'Sponsored / affiliate link' }
    ];
    function newCta() { return { label: '', url: '', variant: 'primary', sponsored: false }; }
    function comparisonBlocks() { return doc.blocks.filter(function (b) { return b.type === 'comparisonTable'; }); }
    function columnsOf(ctx) { return (ctx.block.props.columns || []).map(function (c) { return [c.id, c.name || c.id]; }); }
    function targetColumns(ctx) {
        var t = doc.blocks.filter(function (b) { return b.id === ctx.block.props.targetBlockId; })[0];
        return t ? (t.props.columns || []).map(function (c) { return [c.id, c.name || c.id]; }) : [];
    }
    var LABEL_DEFAULTS = {
        en: { selectedTable: 'Selected: {name}. {cta}', stickyCaption: '{name}: {cta}', ctaAria: '{cta} — {name}', mobileHint: 'Choose a card to see its column.' },
        ru: { selectedTable: 'Выбрано: {name}. {cta}', stickyCaption: '{name}: {cta}', ctaAria: '{cta} — {name}', mobileHint: 'Выберите карточку, чтобы увидеть её столбец.' }
    };

    var BLOCKS = {
        richText: { label: 'Rich text', make: function () { return { html: '' }; }, fields: [
            { k: 'html', t: 'html', l: 'Text' },
            { k: 'variant', t: 'select', l: 'Variant', options: [['', 'default'], ['lead', 'lead'], ['callout', 'callout']] }] },
        comparisonTable: { label: 'Comparison table', make: function () {
            return { caption: '', criteriaLabel: '', columns: [{ id: genId('col'), name: '' }, { id: genId('col'), name: '' }], rows: [] };
        }, fields: [
            { k: 'caption', t: 'text', l: 'Caption' }, { k: 'criteriaLabel', t: 'text', l: 'First column heading' },
            { k: 'legend', t: 'list', l: 'Legend', make: function () { return { rating: 'good', label: '' }; }, fields: [
                { k: 'rating', t: 'select', l: 'Rating', options: RATINGS }, { k: 'label', t: 'text', l: 'Meaning' }] },
            { k: 'columns', t: 'list', l: 'Columns (products)', make: function () { return { id: genId('col'), name: '' }; }, fields: [
                { k: 'name', t: 'text', l: 'Name' }, { k: 'subtitle', t: 'text', l: 'Subtitle' },
                { k: 'link', t: 'obj', optional: true, l: 'Link', make: function () { return { url: '', sponsored: false }; }, fields: [
                    { k: 'url', t: 'text', l: 'URL' }, { k: 'sponsored', t: 'bool', l: 'Sponsored' }] }] },
            { k: 'rows', t: 'list', l: 'Rows', make: function () { return { id: genId('row'), label: '', cells: [] }; }, fields: [
                { k: 'label', t: 'text', l: 'Criterion' }, { k: 'cells', t: 'cells', l: 'Cells' }] },
            { k: 'ctaRow', t: 'obj', optional: true, l: 'Button row', make: function () { return { label: '', items: [] }; }, fields: [
                { k: 'label', t: 'text', l: 'Row label' },
                { k: 'items', t: 'list', l: 'Buttons', make: function () { var c = newCta(); c.columnId = ''; return c; },
                  fields: [{ k: 'columnId', t: 'select', l: 'Column', options: columnsOf }].concat(CTA_FIELDS) }] }] },
        productCard: { label: 'Product card', make: function () { return { title: '' }; }, fields: [
            { k: 'title', t: 'text', l: 'Title' }, { k: 'badge', t: 'text', l: 'Badge' }, { k: 'tag', t: 'text', l: 'Tag' },
            { k: 'summary', t: 'area', l: 'Summary' }, { k: 'bullets', t: 'strs', l: 'Bullets' },
            { k: 'rating', t: 'select', l: 'Rating', options: RATINGS_OPT },
            { k: 'cta', t: 'obj', optional: true, l: 'Main button', make: newCta, fields: CTA_FIELDS },
            { k: 'secondaryCta', t: 'obj', optional: true, l: 'Second button', make: newCta, fields: CTA_FIELDS },
            { k: 'note', t: 'text', l: 'Note' }] },
        faq: { label: 'FAQ', make: function () { return { heading: '', items: [] }; }, fields: [
            { k: 'heading', t: 'text', l: 'Heading' },
            { k: 'items', t: 'list', l: 'Questions', make: function () { return { id: genId('q'), question: '', answerHtml: '' }; }, fields: [
                { k: 'question', t: 'text', l: 'Question' }, { k: 'answerHtml', t: 'html', l: 'Answer' }] }] },
        cta: { label: 'Button (CTA)', make: newCta, fields: CTA_FIELDS.concat([
            { k: 'title', t: 'text', l: 'Title' }, { k: 'text', t: 'text', l: 'Text' }, { k: 'sticky', t: 'bool', l: 'Sticky bar on small screens' }]) },
        cardSelector: { label: 'Card selector', make: function () {
            var c = { heading: '', groupLabel: '', targetBlockId: '', defaultOptionId: '', options: [], labels: {} };
            Object.assign(c.labels, LABEL_DEFAULTS[cfg.locale] || LABEL_DEFAULTS.en);
            return c;
        }, fields: [
            { k: 'heading', t: 'text', l: 'Heading' }, { k: 'groupLabel', t: 'text', l: 'Accessible group label' },
            { k: 'targetBlockId', t: 'select', l: 'Comparison table', options: function () {
                return [['', '—']].concat(comparisonBlocks().map(function (b) { return [b.id, (b.props.caption || b.id)]; })); } },
            { k: 'options', t: 'list', l: 'Options', make: function () { return { id: genId('opt'), label: '', columnId: '' }; }, fields: [
                { k: 'label', t: 'text', l: 'Label' }, { k: 'columnId', t: 'select', l: 'Column', options: targetColumns }] },
            { k: 'defaultOptionId', t: 'select', l: 'Default option', options: function (ctx) {
                return [['', '—']].concat((ctx.block.props.options || []).map(function (o) { return [o.id, o.label || o.id]; })); } },
            { k: 'labels', t: 'obj', l: 'Texts ({name} and {cta} are replaced)', fields: [
                { k: 'selectedTable', t: 'text', l: 'Selected caption' }, { k: 'stickyCaption', t: 'text', l: 'Button text' },
                { k: 'ctaAria', t: 'text', l: 'Button accessible label' }, { k: 'mobileHint', t: 'text', l: 'Hint' }] }] },
        sources: { label: 'Sources', make: function () { return { heading: '', items: [] }; }, fields: [
            { k: 'heading', t: 'text', l: 'Heading' },
            { k: 'items', t: 'list', l: 'Sources', make: function () { return { id: genId('src'), label: '', url: '' }; }, fields: [
                { k: 'label', t: 'text', l: 'Title' }, { k: 'url', t: 'text', l: 'URL' }, { k: 'publisher', t: 'text', l: 'Publisher' },
                { k: 'accessed_at', t: 'date', l: 'Accessed (YYYY-MM-DD)' }, { k: 'sponsored', t: 'bool', l: 'Sponsored' }] }] },
        sponsoredDisclosure: { label: 'Sponsored disclosure', make: function () { return { text: '' }; }, fields: [
            { k: 'text', t: 'area', l: 'Disclosure text' }, { k: 'learnMoreLabel', t: 'text', l: 'Link label' }, { k: 'learnMoreUrl', t: 'text', l: 'Link URL' }] },
        customCode: { label: 'Custom code (HTML/CSS/JS, as is)', make: function () { return {}; }, fields: [
            { k: 'html', t: 'code', l: 'HTML' }, { k: 'css', t: 'code', l: 'CSS' }, { k: 'js', t: 'code', l: 'JavaScript' }] }
    };

    function renderFields(parent, obj, fields, ctx, rerender) {
        fields.forEach(function (f) { parent.appendChild(renderField(obj, f, ctx, rerender)); });
    }

    function renderField(obj, f, ctx, rerender) {
        var input;
        switch (f.t) {
        case 'text': case 'date':
            input = el('input', { type: 'text', class: 'form-control' });
            input.value = obj[f.k] == null ? '' : obj[f.k];
            input.addEventListener('input', function () { obj[f.k] = input.value; markDirty(); });
            return group(f.l, input);
        case 'area': case 'code':
            input = el('textarea', { class: 'form-control', rows: f.t === 'code' ? '8' : '3', spellcheck: 'false' });
            if (f.t === 'code') { input.style.fontFamily = 'monospace'; }
            input.value = obj[f.k] == null ? '' : obj[f.k];
            input.addEventListener('input', function () { obj[f.k] = input.value; markDirty(); });
            return group(f.l, input);
        case 'html':
            input = el('textarea', { class: 'form-control', id: genId('rte') });
            input.value = obj[f.k] == null ? '' : obj[f.k];
            input.addEventListener('input', function () { obj[f.k] = input.value; markDirty(); });
            input.addEventListener('change', function () { obj[f.k] = input.value; markDirty(); });
            pendingEditors.push({ node: input, obj: obj, key: f.k });
            return group(f.l, input);
        case 'select':
            input = el('select', { class: 'form-control' });
            var opts = typeof f.options === 'function' ? f.options(ctx) : f.options;
            var cur = obj[f.k] == null ? '' : obj[f.k];
            var known = opts.some(function (o) { return o[0] === cur; });
            if (!known && cur !== '') { opts = opts.concat([[cur, cur + ' (missing)']]); }
            opts.forEach(function (o) { var op = el('option', { value: o[0], text: o[1] }); if (o[0] === cur) { op.selected = true; } input.appendChild(op); });
            input.addEventListener('change', function () { obj[f.k] = input.value; markDirty(); rerender(); });
            return group(f.l, input);
        case 'bool':
            input = el('input', { type: 'checkbox' });
            input.checked = obj[f.k] === true;
            input.addEventListener('change', function () { obj[f.k] = input.checked; markDirty(); });
            return el('div', { class: 'checkbox' }, [el('label', {}, [input, el('span', { text: ' ' + f.l })])]);
        case 'strs':
            return renderStrings(obj, f, rerender);
        case 'obj':
            return renderObject(obj, f, ctx, rerender);
        case 'list':
            return renderList(obj, f, ctx, rerender);
        case 'cells':
            return renderCells(obj, f, ctx);
        }
        return el('div');
    }

    function renderStrings(obj, f, rerender) {
        var arr = obj[f.k] = Array.isArray(obj[f.k]) ? obj[f.k] : [];
        var box = el('div', { class: 'form-group' }, [el('label', { text: f.l })]);
        arr.forEach(function (v, i) {
            var inp = el('input', { type: 'text', class: 'form-control' });
            inp.value = v;
            inp.addEventListener('input', function () { arr[i] = inp.value; markDirty(); });
            var del = el('button', { type: 'button', class: 'btn btn-xs btn-default', text: '✕' });
            del.addEventListener('click', function () { arr.splice(i, 1); markDirty(); rerender(); });
            box.appendChild(el('div', { class: 'input-group', style: 'margin-bottom:4px' }, [inp, el('span', { class: 'input-group-btn' }, [del])]));
        });
        var add = el('button', { type: 'button', class: 'btn btn-xs btn-default', text: '+ ' + T.add });
        add.addEventListener('click', function () { arr.push(''); markDirty(); rerender(); });
        box.appendChild(add);
        return box;
    }

    function renderObject(obj, f, ctx, rerender) {
        var box = el('fieldset', { class: 'ab-obj', style: 'border:1px solid #555;padding:8px;margin-bottom:10px' }, [el('legend', { text: f.l, style: 'font-size:14px;width:auto;border:0;margin:0' })]);
        if (f.optional) {
            var tog = el('input', { type: 'checkbox' });
            tog.checked = obj[f.k] != null;
            tog.addEventListener('change', function () {
                if (tog.checked) { obj[f.k] = f.make(); } else { delete obj[f.k]; }
                markDirty(); rerender();
            });
            box.appendChild(el('div', { class: 'checkbox' }, [el('label', {}, [tog, el('span', { text: ' ' + T.enable })])]));
            if (obj[f.k] == null) { return box; }
        } else if (obj[f.k] == null) { obj[f.k] = {}; }
        renderFields(box, obj[f.k], f.fields, ctx, rerender);
        return box;
    }

    function move(arr, i, d, rerender) {
        var j = i + d;
        if (j < 0 || j >= arr.length) { return; }
        var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
        comparisonBlocks().forEach(function (table) {
            if (table.props.columns !== arr) { return; }
            (table.props.rows || []).forEach(function (row) {
                if (Array.isArray(row.cells) && row.cells.length > Math.max(i, j)) {
                    var cell = row.cells[i]; row.cells[i] = row.cells[j]; row.cells[j] = cell;
                }
            });
        });
        markDirty(); rerender();
    }
    function toolbar(arr, i, rerender, withIdText) {
        var up = el('button', { type: 'button', class: 'btn btn-xs btn-default', text: '↑', 'aria-label': T.up });
        var down = el('button', { type: 'button', class: 'btn btn-xs btn-default', text: '↓', 'aria-label': T.down });
        var del = el('button', { type: 'button', class: 'btn btn-xs btn-danger', text: '✕', 'aria-label': T.remove });
        up.addEventListener('click', function () { move(arr, i, -1, rerender); });
        down.addEventListener('click', function () { move(arr, i, 1, rerender); });
        del.addEventListener('click', function () { if (window.confirm(T.confirmRemove)) {
            comparisonBlocks().forEach(function (table) {
                if (table.props.columns !== arr) { return; }
                (table.props.rows || []).forEach(function (row) { if (Array.isArray(row.cells)) { row.cells.splice(i, 1); } });
            });
            arr.splice(i, 1); markDirty(); rerender();
        } });
        return el('div', { class: 'pull-right' }, [up, document.createTextNode(' '), down, document.createTextNode(' '), del]);
    }

    function renderList(obj, f, ctx, rerender) {
        var arr = obj[f.k] = Array.isArray(obj[f.k]) ? obj[f.k] : [];
        var box = el('div', { class: 'form-group' }, [el('label', { text: f.l })]);
        arr.forEach(function (item, i) {
            var card = el('div', { style: 'border:1px solid #555;padding:8px;margin-bottom:6px;overflow:hidden' }, [
                toolbar(arr, i, rerender),
                item.id ? el('small', { class: 'text-muted', text: '#' + item.id }) : null
            ]);
            renderFields(card, item, f.fields, ctx, rerender);
            box.appendChild(card);
        });
        var add = el('button', { type: 'button', class: 'btn btn-xs btn-default', text: '+ ' + T.add + ': ' + f.l });
        add.addEventListener('click', function () {
            arr.push(f.make(ctx));
            if (f.k === 'columns' && ctx.block && ctx.block.type === 'comparisonTable') {
                (ctx.block.props.rows || []).forEach(function (row) { if (Array.isArray(row.cells)) { row.cells.push({}); } });
            }
            markDirty(); rerender();
        });
        box.appendChild(add);
        return box;
    }

    function renderCells(row, f, ctx) {
        var cols = ctx.block.props.columns || [];
        row.cells = Array.isArray(row.cells) ? row.cells : [];
        while (row.cells.length < cols.length) { row.cells.push({}); }
        row.cells.length = cols.length;
        var box = el('div', { class: 'form-group' }, [el('label', { text: f.l })]);
        cols.forEach(function (col, i) {
            var cell = row.cells[i];
            var inner = el('div', { style: 'border-left:3px solid #555;padding-left:8px;margin-bottom:6px' }, [el('strong', { text: col.name || col.id })]);
            renderFields(inner, cell, [
                { k: 'value', t: 'text', l: 'Value' }, { k: 'note', t: 'text', l: 'Note' },
                { k: 'rating', t: 'select', l: 'Rating', options: RATINGS_OPT }], ctx, markDirty);
            box.appendChild(inner);
        });
        return box;
    }

    function clean(v) {
        if (Array.isArray(v)) { return v.map(clean); }
        if (v && typeof v === 'object') {
            var out = {};
            Object.keys(v).forEach(function (k) {
                var c = clean(v[k]);
                if (c === '' || c === undefined || c === null || (Array.isArray(c) && c.length === 0)) { return; }
                out[k] = c;
            });
            return out;
        }
        return v;
    }

    function syncEditors() {
        if (window.tinymce) { tinymce.triggerSave(); }
    }

    function renderBlocks() {
        if (window.tinymce) { tinymce.remove(); }
        pendingEditors = [];
        var host = document.getElementById('ab-blocks');
        host.textContent = '';
        if (doc.blocks.length === 0) { host.appendChild(el('p', { class: 'text-muted', text: T.empty })); }
        doc.blocks.forEach(function (block, i) {
            var spec = BLOCKS[block.type];
            var panel = el('div', { class: 'box box-default', id: 'blk-' + block.id }, [
                el('div', { class: 'box-header with-border' }, [
                    toolbar(doc.blocks, i, renderBlocks),
                    el('h3', { class: 'box-title', text: (i + 1) + '. ' + (spec ? spec.label : block.type) }),
                    el('small', { class: 'text-muted', text: '  #' + block.id })
                ])
            ]);
            var body = el('div', { class: 'box-body' });
            if (spec) {
                block.props = block.props || {};
                renderFields(body, block.props, spec.fields, { block: block }, renderBlocks);
            } else {
                body.appendChild(el('p', { class: 'text-danger', text: T.unknownBlock }));
            }
            panel.appendChild(body);
            host.appendChild(panel);
        });
        pendingEditors.forEach(initEditor);
    }

    function initEditor(p) {
        if (!window.tinymce) {
            p.node.setAttribute('aria-label', p.key);
            p.node.setAttribute('aria-describedby', 'ab-editor-warning');
            var missing = document.getElementById('ab-editor-warning');
            if (missing) { missing.textContent = T.editorUnavailable; missing.className = 'text-danger'; }
            return;
        }
        tinymce.init({
            target: p.node, menubar: false, height: 260, branding: false,
            plugins: 'link lists code',
            block_formats: 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4',
            toolbar: 'formatselect | bold italic underline | bullist numlist | blockquote code | link | removeformat',
            valid_elements: 'p,br,strong/b,em/i,u,ul,ol,li,a[href|rel],h2,h3,h4,blockquote,code,pre',
            setup: function (ed) {
                ed.on('input change keyup undo redo', function () { p.obj[p.key] = ed.getContent(); markDirty(); });
            }
        }).catch(function () {
            p.node.setAttribute('aria-label', p.key);
            p.node.setAttribute('aria-describedby', 'ab-editor-warning');
            var warning = document.getElementById('ab-editor-warning');
            if (warning) { warning.textContent = T.editorUnavailable; warning.className = 'text-danger'; }
        });
    }

    // ---- header fields
    function bindHeader(id, key, isDoc) {
        var n = document.getElementById(id);
        n.value = (isDoc ? doc[key] : state[key]) || '';
        n.addEventListener('input', function () { if (isDoc) { doc[key] = n.value; } else { state[key] = n.value; } markDirty(); });
    }
    bindHeader('ab-title', 'title'); bindHeader('ab-summary', 'summary');
    bindHeader('ab-meta-title', 'meta_title'); bindHeader('ab-meta-description', 'meta_description');
    bindHeader('ab-verified-at', 'verified_at', true);

    // ---- add block
    var sel = document.getElementById('ab-add-type');
    Object.keys(BLOCKS).forEach(function (t) { sel.appendChild(el('option', { value: t, text: BLOCKS[t].label })); });
    document.getElementById('ab-add').addEventListener('click', function () {
        var type = sel.value;
        doc.blocks.push({ id: genId(type.toLowerCase().slice(0, 8)), type: type, props: BLOCKS[type].make() });
        markDirty(); renderBlocks();
        var last = doc.blocks[doc.blocks.length - 1];
        var node = document.getElementById('blk-' + last.id);
        if (node) { node.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });

    // ---- save
    function showIssues(list, message) {
        var box = document.getElementById('ab-issues');
        box.textContent = '';
        if (!message && (!list || !list.length)) { box.style.display = 'none'; return; }
        box.style.display = 'block';
        box.appendChild(el('strong', { text: message }));
        var ul = el('ul');
        (list || []).forEach(function (i) { ul.appendChild(el('li', { text: i.path + ' — ' + i.message })); });
        box.appendChild(ul);
    }

    document.getElementById('ab-save').addEventListener('click', function () {
        syncEditors();
        var btn = this;
        btn.disabled = true;
        var submittedGeneration = dirtyGeneration;
        var cleanDocument = clean({ schema_version: 1, locale: cfg.locale, verified_at: doc.verified_at, blocks: doc.blocks });
        if (!Array.isArray(cleanDocument.blocks)) { cleanDocument.blocks = []; }
        var body = { title: state.title || '', summary: state.summary || '', meta_title: state.meta_title || '', meta_description: state.meta_description || '',
            document: cleanDocument };
        fetch(cfg.saveUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf, 'Accept': 'application/json' }, body: JSON.stringify(body) })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'HTTP ' + r.status }; }); })
            .then(function (res) {
                var s = document.getElementById('ab-status');
                if (res.ok) {
                    var localeState = document.getElementById('ab-locale-state');
                    var transitionButton = document.querySelector('form[data-article-transition] button[type="submit"]');
                    if (localeState) { localeState.textContent = res.published ? 'published' : 'draft'; }
                    if (transitionButton) { transitionButton.disabled = false; }
                }
                if (res.ok && dirtyGeneration === submittedGeneration) { dirty = false; s.textContent = T.saved; s.className = 'text-success'; showIssues(null, ''); }
                else if (res.ok) { s.textContent = T.savedWithChanges; s.className = 'text-warning'; showIssues(null, ''); }
                else { s.textContent = T.notSaved; s.className = 'text-danger'; showIssues(res.issues, res.error || T.notSaved); }
            })
            .catch(function () { showIssues(null, T.network); })
            .then(function () { btn.disabled = false; });
    });

    Array.prototype.forEach.call(document.querySelectorAll('form[data-needs-saved]'), function (form) {
        form.addEventListener('submit', function (e) { if (dirty) { e.preventDefault(); window.alert(T.saveFirst); } });
    });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    // The layout loads TinyMCE after this script, so the first render waits for the whole page.
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', renderBlocks); } else { renderBlocks(); }
}());
