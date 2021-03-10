const selector = '[data-wdc-ajax-url]';

const WDCalculator = function () {
    console.log('calculator');
};

function init() {
    if (document.querySelector(selector)) WDCalculator();
}

document.addEventListener('DOMContentLoaded', function() {
    init();
});
