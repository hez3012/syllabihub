// SyllabiHub Sidebar — collapse/expand, mobile open/close, localStorage state.
// See SyllabiHub — Frontend Redesign Specification §2.

document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sh-sidebar');
    const overlay = document.getElementById('sh-overlay');
    const collapseBtn = document.getElementById('sh-sidebar-collapse');
    const hamburger = document.getElementById('sh-topbar-hamburger');
    const STORAGE_KEY = 'sh-sidebar-collapsed';

    if (!sidebar) return;

    // --- Desktop: collapse/expand via localStorage ---
    function isDesktop() {
        return window.innerWidth > 1024;
    }

    function applyCollapsedState() {
        if (!isDesktop()) return;
        const collapsed = localStorage.getItem(STORAGE_KEY) === 'true';
        sidebar.classList.toggle('is-collapsed', collapsed);
        if (collapseBtn) {
            collapseBtn.querySelector('i').className = collapsed
                ? 'bi bi-list'
                : 'bi bi-list';
        }
    }

    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            const isCollapsed = sidebar.classList.toggle('is-collapsed');
            localStorage.setItem(STORAGE_KEY, isCollapsed);
            collapseBtn.querySelector('i').className = isCollapsed
                ? 'bi bi-list'
                : 'bi bi-list';
        });
    }

    // Apply on load (before any paint)
    applyCollapsedState();
    window.addEventListener('resize', applyCollapsedState);

    // --- Mobile: hamburger opens sidebar as overlay ---
    function isMobile() {
        return window.innerWidth <= 767;
    }

    function openMobileSidebar() {
        sidebar.classList.add('is-mobile-open');
        overlay.classList.add('is-visible');
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        sidebar.classList.remove('is-mobile-open');
        overlay.classList.remove('is-visible');
        document.body.style.overflow = '';
        setTimeout(function () {
            overlay.style.display = '';
        }, 250);
    }

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            if (sidebar.classList.contains('is-mobile-open')) {
                closeMobileSidebar();
            } else {
                openMobileSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    // Close mobile sidebar on window resize to desktop
    window.addEventListener('resize', function () {
        if (!isMobile() && sidebar.classList.contains('is-mobile-open')) {
            closeMobileSidebar();
        }
    });
});
