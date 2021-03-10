const selector = '[data-wdc-ajax-url]';

const WDCalculator = function () {
    document.querySelectorAll(selector).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            event.stopImmediatePropagation();

            const dateInputName = form.querySelector('input[type="date"]').getAttribute('name');

            const data = new FormData(form);
            const date = data.get(dateInputName);
            const param = {};
            param[dateInputName] = date;

            const url = new URL(form.getAttribute('data-wdc-ajax-url'));
            url.search = new URLSearchParams(param).toString();

            const parent = form.parentNode;
            const results = parent.querySelector('.wdc__results');

            const submitButton = form.querySelector('[type="submit"]');
            submitButton.setAttribute('disabled', 'disabled');
            submitButton.classList.add('loading');

             fetch(url)
                .then(response => response.json())
                .then(data => {
                    results.innerHTML = data.HTML;
                    submitButton.classList.remove('loading');
                    submitButton.removeAttribute('disabled');
                });
        });
    });
};

function init() {
    if (document.querySelectorAll(selector)) WDCalculator();
}

document.addEventListener('DOMContentLoaded', function() {
    init();
});
