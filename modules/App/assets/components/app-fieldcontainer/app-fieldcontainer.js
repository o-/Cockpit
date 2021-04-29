const fn = e => {

    let closest = e.target.closest('app-fieldcontainer');
    let containers = document.querySelectorAll('app-fieldcontainer');

    containers.forEach(container => {

        if (container !== closest) {
            container.removeAttribute('active')
        }
    });
};


document.addEventListener('click', fn);
document.addEventListener('focusin', fn);

customElements.define('app-fieldcontainer', class extends HTMLElement {

    constructor() {
        super();
    }

    connectedCallback() {

        this.addEventListener('click', e => this.setAttribute('active','true'));
        this.addEventListener('focusin', e => this.setAttribute('active','true'));
    }

    disconnectedCallback() {

    }
});