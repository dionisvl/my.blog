class LazyImageLoader {
    constructor(options = {}) {
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                    this.observer.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            rootMargin: options.rootMargin || '50px',
            threshold: options.threshold || 0.01,
        });

        document.querySelectorAll('img.lazy-real[data-src]').forEach((img) => {
            this.observer.observe(img);
        });
    }

    loadImage(img) {
        const src = img.dataset.src;
        const srcset = img.dataset.srcset;
        const placeholder = img.parentElement?.querySelector('.lazy-placeholder');

        // Pre-check cache BEFORE setting src on tempImg
        const cacheTest = new Image();
        cacheTest.src = src;

        const isCached = cacheTest.complete && cacheTest.naturalWidth > 0;

        if (isCached) {
            // Image in cache - show instantly without animation
            img.src = src;
            if (srcset) {
                img.srcset = srcset;
            }
            img.removeAttribute('data-src');
            img.removeAttribute('data-srcset');
            img.style.opacity = '1';
            img.style.transition = 'none';
            placeholder?.remove();
            return;
        }

        // Not cached - proceed with normal lazy load animation
        const tempImg = new Image();

        tempImg.onload = () => {
            img.src = src;
            if (srcset) {
                img.srcset = srcset;
            }
            img.removeAttribute('data-src');
            img.removeAttribute('data-srcset');

            requestAnimationFrame(() => {
                img.classList.add('loaded');
                placeholder?.classList.add('fade-out');
            });

            placeholder?.addEventListener('transitionend', () => {
                placeholder.remove();
            }, { once: true });
        };

        tempImg.onerror = () => {
            img.classList.add('load-error');
            placeholder?.remove();
        };

        tempImg.src = src;
        if (srcset) {
            tempImg.srcset = srcset;
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.lazyImageLoader = new LazyImageLoader();
    });
} else {
    window.lazyImageLoader = new LazyImageLoader();
}