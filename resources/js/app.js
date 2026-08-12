import './bootstrap';

import * as bootstrap from 'bootstrap';

// Syllabus PDF preview toggle (subjects.show) — the iframe starts with no
// src at all, so the file is never fetched until the faculty member
// explicitly asks to see it. Event delegation on document so this also
// covers any rows rendered after page load, not just the ones present now.
document.addEventListener('click', function (event) {
    const button = event.target.closest('.js-syllabus-preview-toggle');
    if (!button) {
        return;
    }

    const iframe = document.getElementById(button.dataset.target);
    if (!iframe) {
        return;
    }

    if (!iframe.getAttribute('src')) {
        iframe.setAttribute('src', button.dataset.src);
    }

    const nowHidden = iframe.classList.toggle('d-none');
    button.querySelector('.js-syllabus-preview-label').textContent = nowHidden ? 'Preview' : 'Hide Preview';
    button.querySelector('.bi').className = nowHidden ? 'bi bi-eye' : 'bi bi-eye-slash';
});