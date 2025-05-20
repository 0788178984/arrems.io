// This file contains JavaScript functionality specific to the help page.
// It handles interactive elements such as expanding FAQs.

document.addEventListener('DOMContentLoaded', function() {
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        question.addEventListener('click', function() {
            const answer = item.querySelector('.faq-answer');
            answer.classList.toggle('active');
            const isActive = answer.classList.contains('active');
            question.setAttribute('aria-expanded', isActive);
        });
    });
});