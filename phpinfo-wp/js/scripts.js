"use strict";

function main() {

    var topButton = document.getElementById('topButton-phpinfo-WP');

    function showButton() {
        if (topButton !== null) {
            if (document.body.scrollTop > 400 || document.documentElement.scrollTop > 400) {
                topButton.style.display = "inline-flex";
            } else {
                topButton.style.display = "none";
            }
        }
    }

    window.onscroll = function () {
        showButton();
    };

    function goTop() {
        if ('scrollTo' in window) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            document.body.scrollTop = 0;
            document.documentElement.scrollTop = 0;
        }
    }

    if (topButton !== null) {
        topButton.addEventListener('click', goTop);
    }

    // Notice Isolation: Suppress 3rd-party theme & plugin banners on phpinfo() WP pages
    if (document.body.classList.contains('phpinfowp-page') || document.body.classList.contains('piwp-admin-page')) {
        var purgeForeignNotices = function() {
            var notices = document.querySelectorAll(
                '#wpbody-content > .notice, #wpbody-content > .updated, #wpbody-content > .error, #wpbody-content > .update-nag, #wpbody-content > div[class*="notice"], #wpbody-content > div[class*="update"], #wpbody-content > div[class*="alert"]'
            );
            notices.forEach(function(el) {
                if (el.classList.contains('inline') || el.closest('.phpinfowp-pro-page') || el.closest('.wrap')) {
                    return;
                }
                var cls = el.className || '';
                var id  = el.id || '';
                if (cls.indexOf('phpinfowp') === -1 && cls.indexOf('piwp') === -1 && id.indexOf('phpinfowp') === -1 && id.indexOf('piwp') === -1) {
                    el.style.setProperty('display', 'none', 'important');
                }
            });
        };
        purgeForeignNotices();
        document.addEventListener('DOMContentLoaded', purgeForeignNotices);
        window.addEventListener('load', purgeForeignNotices);
    }

}

main();

// Generic Custom Confirmation Dialog Box System
(function () {
    var modalEl = null;
    var currentResolver = null;

    function getOrCreateModal() {
        if (modalEl && document.body.contains(modalEl)) return modalEl;

        var el = document.getElementById('phpinfowp-confirm-modal');
        if (el) {
            modalEl = el;
            return modalEl;
        }

        el = document.createElement('div');
        el.id = 'phpinfowp-confirm-modal';
        el.className = 'phpinfowp-confirm-modal';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-hidden', 'true');

        el.innerHTML =
            '<div class="phpinfowp-confirm-backdrop"></div>' +
            '<div class="phpinfowp-confirm-dialog">' +
                '<div class="phpinfowp-confirm-header">' +
                    '<div class="phpinfowp-confirm-icon-wrap is-danger" id="piwp-confirm-icon-wrap">' +
                        '<span class="dashicons dashicons-warning" id="piwp-confirm-icon"></span>' +
                    '</div>' +
                    '<div class="phpinfowp-confirm-title-area">' +
                        '<h3 class="phpinfowp-confirm-title" id="piwp-confirm-title">Are you sure?</h3>' +
                        '<p class="phpinfowp-confirm-message" id="piwp-confirm-message"></p>' +
                    '</div>' +
                '</div>' +
                '<div class="phpinfowp-confirm-footer">' +
                    '<button type="button" class="phpinfowp-confirm-btn-cancel" id="piwp-confirm-cancel">Cancel</button>' +
                    '<button type="button" class="phpinfowp-confirm-btn-ok" id="piwp-confirm-ok">Confirm</button>' +
                '</div>' +
            '</div>';

        document.body.appendChild(el);
        modalEl = el;

        function close(confirmed) {
            modalEl.classList.remove('is-open');
            modalEl.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (currentResolver) {
                var res = currentResolver;
                currentResolver = null;
                res(confirmed);
            }
        }

        modalEl.querySelector('.phpinfowp-confirm-backdrop').addEventListener('click', function () {
            close(false);
        });
        modalEl.querySelector('#piwp-confirm-cancel').addEventListener('click', function () {
            close(false);
        });
        modalEl.querySelector('#piwp-confirm-ok').addEventListener('click', function () {
            close(true);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modalEl && modalEl.classList.contains('is-open')) {
                close(false);
            }
        });

        return modalEl;
    }

    window.phpinfowpConfirm = function (opts) {
        if (typeof opts === 'string') {
            opts = { message: opts };
        }
        opts = opts || {};
        var title       = opts.title || 'Please Confirm';
        var message     = opts.message || 'Are you sure you want to proceed?';
        var confirmText = opts.confirmText || 'Confirm';
        var cancelText  = opts.cancelText || 'Cancel';
        var isDanger    = opts.isDanger !== false;
        var iconClass   = opts.icon || (isDanger ? 'dashicons-warning' : 'dashicons-info');

        var modal = getOrCreateModal();
        modal.querySelector('#piwp-confirm-title').textContent = title;
        modal.querySelector('#piwp-confirm-message').textContent = message;

        var okBtn = modal.querySelector('#piwp-confirm-ok');
        okBtn.textContent = confirmText;
        if (isDanger) {
            okBtn.className = 'phpinfowp-confirm-btn-ok';
        } else {
            okBtn.className = 'phpinfowp-confirm-btn-ok is-primary';
        }

        modal.querySelector('#piwp-confirm-cancel').textContent = cancelText;

        var iconWrap = modal.querySelector('#piwp-confirm-icon-wrap');
        iconWrap.className = isDanger ? 'phpinfowp-confirm-icon-wrap is-danger' : 'phpinfowp-confirm-icon-wrap is-info';
        modal.querySelector('#piwp-confirm-icon').className = 'dashicons ' + iconClass;

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        okBtn.focus();

        return new Promise(function (resolve) {
            currentResolver = resolve;
        });
    };

    // Global click listener for [data-confirm] buttons and links
    document.addEventListener('click', function (e) {
        var target = e.target && e.target.closest ? e.target.closest('[data-confirm]') : null;
        if (!target) return;

        var msg = target.getAttribute('data-confirm');
        if (!msg) return;

        e.preventDefault();
        e.stopPropagation();

        var title       = target.getAttribute('data-confirm-title') || 'Please Confirm';
        var confirmText = target.getAttribute('data-confirm-btn') || 'Confirm';
        var isDanger    = target.getAttribute('data-confirm-danger') !== 'false';
        var form        = target.closest('form');

        window.phpinfowpConfirm({
            title: title,
            message: msg,
            confirmText: confirmText,
            isDanger: isDanger
        }).then(function (confirmed) {
            if (!confirmed) return;

            if (target.tagName === 'A') {
                window.location.href = target.href;
            } else if (target.type === 'submit' && form) {
                if (target.name) {
                    var hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = target.name;
                    hidden.value = target.value || '1';
                    form.appendChild(hidden);
                }
                form.submit();
            } else if (form) {
                form.submit();
            } else if (typeof target.onclick === 'function') {
                target.onclick();
            }
        });
    }, true);

    // Global submit listener for forms with [data-confirm]
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.hasAttribute('data-confirm')) return;
        if (form.getAttribute('data-piwp-confirmed') === 'true') {
            form.removeAttribute('data-piwp-confirmed');
            return;
        }

        var msg = form.getAttribute('data-confirm');
        if (!msg) return;

        e.preventDefault();
        e.stopPropagation();

        var title       = form.getAttribute('data-confirm-title') || 'Please Confirm';
        var confirmText = form.getAttribute('data-confirm-btn') || 'Confirm';
        var isDanger    = form.getAttribute('data-confirm-danger') !== 'false';

        window.phpinfowpConfirm({
            title: title,
            message: msg,
            confirmText: confirmText,
            isDanger: isDanger
        }).then(function (confirmed) {
            if (confirmed) {
                form.setAttribute('data-piwp-confirmed', 'true');
                form.submit();
            }
        });
    }, true);
})();

// Sidenav Height Synchronization: Ensure #wpbody-content is tall enough for sidebar cards without adding excess whitespace
(function () {
    function syncSidebarHeight() {
        if (!document.body || !document.body.classList.contains('phpinfowp-has-sidenav')) return;
        var sidebar = document.querySelector('.phpinfowp-sidebar-container');
        var wpbody  = document.getElementById('wpbody-content');
        if (sidebar && wpbody) {
            var sidebarHeight = Math.ceil(sidebar.getBoundingClientRect().height) + 18;
            if (sidebarHeight > 0) {
                wpbody.style.minHeight = sidebarHeight + 'px';
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncSidebarHeight);
    } else {
        syncSidebarHeight();
    }
    window.addEventListener('load', syncSidebarHeight);
    window.addEventListener('resize', syncSidebarHeight);
})();

// Live Scanning Banner System for Fast Scans (2-8s)
(function () {
    var hideTimeout = null;

    window.phpinfowpShowScanBanner = function (title, subtitle) {
        if (hideTimeout) {
            clearTimeout(hideTimeout);
            hideTimeout = null;
        }

        var banner = document.getElementById('phpinfowp-scan-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'phpinfowp-scan-banner';
            banner.className = 'phpinfowp-scan-banner';
            banner.setAttribute('role', 'status');
            banner.setAttribute('aria-live', 'polite');
            banner.innerHTML = '<span class="dashicons dashicons-update phpinfowp-spin phpinfowp-scan-banner-icon"></span>' +
                '<span class="phpinfowp-scan-banner-title"></span>' +
                '<span class="phpinfowp-scan-banner-sep"></span>' +
                '<span class="phpinfowp-scan-banner-sub"></span>';
            document.body.appendChild(banner);
        }

        var titleEl = banner.querySelector('.phpinfowp-scan-banner-title');
        var subEl   = banner.querySelector('.phpinfowp-scan-banner-sub');
        var sepEl   = banner.querySelector('.phpinfowp-scan-banner-sep');

        titleEl.innerHTML = title || 'Scanning in progress...';

        if (subtitle) {
            subEl.innerHTML = subtitle;
            subEl.style.display = 'inline';
            sepEl.style.display = 'inline-block';
        } else {
            subEl.style.display = 'none';
            sepEl.style.display = 'none';
        }

        // Force reflow for smooth entry animation
        void banner.offsetWidth;
        banner.classList.add('is-active');
    };

    window.phpinfowpHideScanBanner = function () {
        var banner = document.getElementById('phpinfowp-scan-banner');
        if (banner) {
            banner.classList.remove('is-active');
        }
    };
})();
