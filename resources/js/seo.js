document.addEventListener('DOMContentLoaded', function () {
    var faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(function (item) {
        var heading = item.querySelector('h3');
        if (!heading) return;

        heading.style.cursor = 'pointer';
        heading.addEventListener('click', function () {
            var answers = item.querySelectorAll('.faq-short-answer, .faq-full-answer');
            answers.forEach(function (el) {
                el.style.display = el.style.display === 'none' ? '' : 'none';
            });
        });
    });
});
