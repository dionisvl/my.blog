/**
 * fas - полное сердце (лайк поставлен)
 * far - пустое сердце
 * @type {Likes}
 */
let Likes = class Likes {
    static toggle(element, event) {
        event.preventDefault();
        let post_id = element.dataset.post_id;
        let device_memory = navigator.deviceMemory || 0;
        let response = request('/postlike/' + post_id, 'POST', {device_memory: device_memory})

        //console.log(response);
        response
            .then(data => {
                if (data.status === 'ok') {
                    if (data.data === 'liked') {
                        this.like(post_id);
                    } else if (data.data === 'unliked') {
                        this.unlike(post_id);
                    } else {
                        console.log(data)
                    }
                } else if (data.status === 'error') {
                    console.log(data)
                } else {
                    console.log(data)
                }
            })
    }

    static like(post_id) {
        let like_button_count = document.querySelector("a[data-post_id='" + post_id + "'] .like_button_count");
        like_button_count.innerHTML = parseInt(like_button_count.innerHTML) + 1;

        let heart = document.querySelector("a[data-post_id='" + post_id + "'] .svg-inline--fa");
        heart.dataset.prefix = 'fas';
        console.log('success liked');
    }

    static unlike(post_id) {
        let like_button_count = document.querySelector("a[data-post_id='" + post_id + "'] .like_button_count");
        like_button_count.innerHTML = parseInt(like_button_count.innerHTML) - 1;

        let heart = document.querySelector("a[data-post_id='" + post_id + "'] .svg-inline--fa");
        heart.dataset.prefix = 'far';
        console.log('success unliked');
    }
};


function request(url, method, payload) {
    return fetch(url, {
        method: method,
        body: JSON.stringify(payload),
        headers: new Headers({
            'Accept': 'application/json',
            'Content-type': 'application/json',
            'X-CSRF-Token': document.querySelector("meta[name='_token']").getAttribute('content')
        })
    })
        .then(r => r.json())
        .catch(error => {
            console.error(error);
            return {status: 'error', data: 'request failed'};
        })
}

// Anti-bot counter
let ct = 0

function count_keyup() {
    const elements = document.getElementsByClassName('countMe')
    for (let i = 0; i < elements.length; i++) {
        elements[i].value = ++ct
        //console.log(elements[i].value);
    }
}
