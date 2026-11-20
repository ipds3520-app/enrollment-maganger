(function () {
    'use strict';

    var root = document.documentElement;
    var themeStorageKey = 'em-theme';

    function currentTheme() {
        return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(theme, persist) {
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme;

        document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
            var nextLabel = theme === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
            button.setAttribute('aria-label', nextLabel);
            button.setAttribute('title', nextLabel);
        });

        if (persist) {
            try {
                localStorage.setItem(themeStorageKey, theme);
            } catch (error) {
                // Storage may be unavailable (private mode); theme still applies for this page.
            }
        }
    }

    applyTheme(currentTheme(), false);

    document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark', true);
        });
    });

    window.addEventListener('storage', function (event) {
        if (event.key === themeStorageKey && (event.newValue === 'light' || event.newValue === 'dark')) {
            applyTheme(event.newValue, false);
        }
    });

    function copyFromElement(input) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(input.value);
        }

        input.focus();
        input.select();

        return document.execCommand('copy')
            ? Promise.resolve()
            : Promise.reject(new Error('Copy is not supported'));
    }

    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        var input = document.querySelector(button.getAttribute('data-copy-target'));
        var label = button.querySelector('[data-copy-label]');

        if (!input || !label) {
            return;
        }

        var originalLabel = label.textContent;
        var doneLabel = button.getAttribute('data-copy-done') || 'Tersalin';

        function flashLabel(text) {
            label.textContent = text;
            window.setTimeout(function () {
                label.textContent = originalLabel;
            }, 2000);
        }

        button.addEventListener('click', function () {
            copyFromElement(input).then(function () {
                flashLabel(doneLabel);
            }).catch(function () {
                flashLabel('Gagal menyalin');
            });
        });
    });

    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    var sidebarOverlay = document.querySelector('[data-sidebar-overlay]');
    var sidebarClose = document.querySelector('[data-sidebar-close]');

    function setSidebar(open) {
        if (!sidebar) {
            return;
        }

        document.body.classList.toggle('sidebar-open', open);

        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        if (sidebarOverlay) {
            sidebarOverlay.hidden = !open;
        }

        if (open) {
            var firstLink = sidebar.querySelector('.nav-item');
            if (firstLink) {
                firstLink.focus({ preventScroll: true });
            }
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            setSidebar(!document.body.classList.contains('sidebar-open'));
        });
    }

    [sidebarOverlay, sidebarClose].forEach(function (element) {
        if (element) {
            element.addEventListener('click', function () {
                setSidebar(false);
                if (sidebarToggle) {
                    sidebarToggle.focus({ preventScroll: true });
                }
            });
        }
    });

    var profile = document.querySelector('[data-profile]');
    var profileToggle = document.querySelector('[data-profile-toggle]');
    var profileMenu = document.querySelector('[data-profile-menu]');

    function closeProfileMenu() {
        if (profileMenu) {
            profileMenu.hidden = true;
        }
        if (profileToggle) {
            profileToggle.setAttribute('aria-expanded', 'false');
        }
    }

    if (profileToggle && profileMenu) {
        profileToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var willOpen = profileMenu.hidden;
            profileMenu.hidden = !willOpen;
            profileToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

            if (willOpen) {
                var firstItem = profileMenu.querySelector('.profile-menu-item');
                if (firstItem) {
                    firstItem.focus({ preventScroll: true });
                }
            }
        });

        document.addEventListener('click', function (event) {
            if (profile && !profile.contains(event.target)) {
                closeProfileMenu();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (profileMenu && !profileMenu.hidden) {
            closeProfileMenu();
            if (profileToggle) {
                profileToggle.focus({ preventScroll: true });
            }
        }

        if (document.body.classList.contains('sidebar-open')) {
            setSidebar(false);
            if (sidebarToggle) {
                sidebarToggle.focus({ preventScroll: true });
            }
        }
    });

    var desktopQuery = window.matchMedia('(min-width: 901px)');

    function handleViewportChange(event) {
        if (event.matches && document.body.classList.contains('sidebar-open')) {
            setSidebar(false);
        }
    }

    if (typeof desktopQuery.addEventListener === 'function') {
        desktopQuery.addEventListener('change', handleViewportChange);
    }
})();
