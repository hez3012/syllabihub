import './bootstrap';
import './sidebar';
import './chatbot';

import * as bootstrap from 'bootstrap';

// Syllabus PDF preview toggle (courses.show) — the iframe starts with no
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

// Submit-button loading state — every form gets its primary submit
// button disabled + spinner-labeled the moment it's submitted, so a
// slow request can't be double-clicked and there's visible feedback
// that something is happening. Skipped for forms with data-no-loading
// (none currently) in case a future form needs to opt out.
document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) {
        return;
    }

    const button = form.querySelector('button[type="submit"]');
    if (!button || button.disabled) {
        return;
    }

    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="btn-spinner"></span>Please wait…';
});

// Toast notifications — success toasts auto-dismiss after 5s, error
// toasts stay until manually closed since they need to actually be read.
document.querySelectorAll('.toast-item').forEach(function (toast) {
    const dismiss = () => {
        toast.classList.add('toast-hide');
        setTimeout(() => toast.remove(), 250);
    };

    toast.querySelector('.toast-close')?.addEventListener('click', dismiss);

    if (toast.classList.contains('toast-success')) {
        setTimeout(dismiss, 5000);
    }
});
