(function () {
    'use strict';

    var THEME_KEY   = 'vhost-manager-theme';

    var html         = document.documentElement;
    var sidebar      = document.getElementById('sidebar');
    var overlay      = document.getElementById('sidebarOverlay');
    var themeToggle  = document.getElementById('themeToggle');
    var themeIcon    = document.getElementById('themeIcon');
    var sidebarBtn   = document.getElementById('sidebarToggle');
    var topbarBtn    = document.getElementById('topbarToggle');
    var sidebarBtnIcon = sidebarBtn ? sidebarBtn.querySelector('i') : null;
    var topbarBtnIcon = topbarBtn ? topbarBtn.querySelector('i') : null;

    /* ── Theme ────────────────────────────────────── */
    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        if (themeIcon) {
            themeIcon.className = theme === 'dark'
                ? 'fa-solid fa-sun'
                : 'fa-solid fa-moon';
        }
    }

    if (themeToggle) {
        applyTheme(localStorage.getItem(THEME_KEY) || 'dark');
        themeToggle.addEventListener('click', function () {
            applyTheme(
                (html.getAttribute('data-theme') || 'dark') === 'dark' ? 'light' : 'dark'
            );
        });
    }

    /* ── Sidebar ──────────────────────────────────── */
    function isMobile() { return window.innerWidth < 780; }

    function showOverlay() {
        if (overlay) overlay.classList.add('is-visible');
    }

    function hideOverlay() {
        if (overlay) overlay.classList.remove('is-visible');
    }

    function openMobileSidebar() {
        if (sidebar) sidebar.classList.add('is-mobile-open');
        showOverlay();
        syncToggleIcons();
    }

    function closeMobileSidebar() {
        if (sidebar) sidebar.classList.remove('is-mobile-open');
        hideOverlay();
        syncToggleIcons();
    }

    function syncToggleIcons() {
        if (topbarBtnIcon) {
            topbarBtnIcon.className = 'fa-solid fa-bars';
        }

        if (sidebarBtnIcon) {
            sidebarBtnIcon.className = isMobile() ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
        }
    }

    function toggleSidebar() {
        if (!isMobile()) {
            return;
        }

        if (sidebar && sidebar.classList.contains('is-mobile-open')) {
            closeMobileSidebar();
        } else {
            openMobileSidebar();
        }
    }

    if (!isMobile() && sidebar) {
        sidebar.classList.remove('is-collapsed');
        sidebar.classList.remove('is-mobile-open');
    }
    syncToggleIcons();

    if (sidebarBtn) sidebarBtn.addEventListener('click', toggleSidebar);
    if (topbarBtn)  topbarBtn.addEventListener('click', toggleSidebar);

    /* Overlay click closes mobile sidebar */
    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    /* Recalculate on resize */
    window.addEventListener('resize', function () {
        if (!isMobile()) {
            closeMobileSidebar();
            if (sidebar) {
                sidebar.classList.remove('is-collapsed');
            }
        }
        syncToggleIcons();
    });

    /* ── Secret Field Toggles ────────────────────── */
    function initSecretToggles() {
        var toggles = document.querySelectorAll('.secret-toggle-btn');
        if (!toggles.length) {
            return;
        }

        toggles.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var targetId = btn.getAttribute('data-secret-target');
                if (!targetId) {
                    return;
                }

                var input = document.getElementById(targetId);
                if (!input) {
                    return;
                }

                var showing = input.getAttribute('type') === 'text';
                var nextType = showing ? 'password' : 'text';
                input.setAttribute('type', nextType);

                var icon = btn.querySelector('i');
                if (icon) {
                    icon.className = showing ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
                }

                btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
                btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            });
        });
    }

    initSecretToggles();

    function initSetupIntegrationSwitcher() {
        var modeSelect = document.getElementById('proxy_mode');
        if (!modeSelect) {
            return;
        }

        var help = document.getElementById('proxy_mode_help');
        var builtinSection = document.getElementById('builtin_npm_section');
        var externalSection = document.getElementById('external_npm_section');
        var externalIds = ['npm_base_url', 'npm_identity', 'npm_secret', 'npm_forward_host', 'npm_forward_port'];

        function setPanelState(section, active) {
            if (!section) {
                return;
            }

            section.hidden = !active;

            section.querySelectorAll('input, select, textarea').forEach(function (input) {
                input.disabled = !active;

                if (input.id === 'builtin_npm_identity' || input.id === 'builtin_npm_secret') {
                    input.required = active;
                }

                if (externalIds.indexOf(input.id) !== -1) {
                    input.required = active;
                }
            });
        }

        var submitBtn = document.getElementById('integration-submit-btn');
        var btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;

        function updateProxySection() {
            var mode = modeSelect.value;

            setPanelState(builtinSection, mode === 'builtin_npm');
            setPanelState(externalSection, mode === 'external_npm');

            if (btnText) {
                btnText.textContent = mode === 'disabled' ? 'Continue' : 'Test & Continue';
            }

            if (!help) {
                return;
            }

            if (mode === 'external_npm') {
                help.textContent = 'Connect to an existing Nginx Proxy Manager running on another server.';
                return;
            }

            if (mode === 'disabled') {
                help.textContent = 'Skip proxy setup for now. You can configure it later in settings.';
                return;
            }

            help.textContent = 'Pre-configured Nginx Proxy Manager is included and will manage ports 80 and 443.';
        }

        modeSelect.addEventListener('change', updateProxySection);
        updateProxySection();
    }

    initSetupIntegrationSwitcher();

    function initBuiltinNpmEmailValidation() {
        var input = document.getElementById('builtin_npm_identity');
        var errorEl = document.getElementById('builtin_npm_identity_error');
        if (!input || !errorEl) {
            return;
        }

        function validateEmail(value) {
            var trimmed = String(value || '').trim();
            if (trimmed === '') {
                return 'NPM admin email is required.';
            }

            if (trimmed !== trimmed.toLowerCase()) {
                return 'Use lowercase only (no capital letters).';
            }

            var isValidEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed);
            if (!isValidEmail) {
                return 'Enter a valid email address.';
            }

            return '';
        }

        function refreshValidation() {
            if (input.disabled) {
                input.setCustomValidity('');
                input.classList.remove('is-error');
                errorEl.textContent = '';
                errorEl.hidden = true;
                return true;
            }

            var message = validateEmail(input.value);
            input.setCustomValidity(message);

            if (message !== '') {
                input.classList.add('is-error');
                errorEl.textContent = message;
                errorEl.hidden = false;
                return false;
            }

            input.classList.remove('is-error');
            errorEl.textContent = '';
            errorEl.hidden = true;

            return true;
        }

        input.addEventListener('input', refreshValidation);
        input.addEventListener('blur', refreshValidation);

        var form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!refreshValidation()) {
                    e.preventDefault();
                    input.reportValidity();
                }
            });
        }

        var modeSelect = document.getElementById('proxy_mode');
        if (modeSelect) {
            modeSelect.addEventListener('change', refreshValidation);
        }

        refreshValidation();
    }

    initBuiltinNpmEmailValidation();

    function initAutoOpenDialogs() {
        var dialogs = document.querySelectorAll('dialog[data-auto-open="true"]');
        if (!dialogs.length) {
            return;
        }

        dialogs.forEach(function (dlg) {
            if (typeof dlg.showModal === 'function' && !dlg.open) {
                dlg.showModal();
            }
        });
    }

    initAutoOpenDialogs();

    function initLogsLiveControls() {
        var controls = document.getElementById('logs-controls');
        var list = document.querySelector('.logs-list');
        if (!controls || !list) {
            return;
        }

        var typeInputs = controls.querySelectorAll('input[name="types[]"]');
        var sortSelect = document.getElementById('logs_sort');

        function selectedTypes() {
            var types = [];
            typeInputs.forEach(function (input) {
                if (input.checked) {
                    types.push(String(input.value || '').toUpperCase());
                }
            });

            return types;
        }

        function applyTypeFilter() {
            var types = selectedTypes();
            var items = list.querySelectorAll('.logs-item');

            items.forEach(function (item) {
                var level = String(item.getAttribute('data-level') || '').toUpperCase();
                item.hidden = types.length > 0 && types.indexOf(level) === -1;
            });
        }

        function applySort() {
            if (!sortSelect) {
                return;
            }

            var mode = sortSelect.value === 'oldest' ? 'oldest' : 'newest';
            var items = Array.prototype.slice.call(list.querySelectorAll('.logs-item'));

            items.sort(function (a, b) {
                var aEpoch = parseInt(a.getAttribute('data-epoch') || '0', 10);
                var bEpoch = parseInt(b.getAttribute('data-epoch') || '0', 10);

                if (mode === 'oldest') {
                    return aEpoch - bEpoch;
                }

                return bEpoch - aEpoch;
            });

            items.forEach(function (item) {
                list.appendChild(item);
            });
        }

        typeInputs.forEach(function (input) {
            input.addEventListener('change', applyTypeFilter);
        });

        if (sortSelect) {
            sortSelect.addEventListener('change', applySort);
        }

        applySort();
        applyTypeFilter();
    }

    initLogsLiveControls();

    function initLogsClearConfirmation() {
        var form = document.getElementById('logs-clear-form');
        var trigger = document.getElementById('logs-clear-trigger');
        var modal = document.getElementById('logs-clear-confirm-modal');
        var confirmBtn = document.getElementById('logs-clear-confirm-btn');
        if (!form || !trigger || !modal || !confirmBtn) {
            return;
        }

        trigger.addEventListener('click', function () {
            if (typeof modal.showModal === 'function') {
                modal.showModal();
                return;
            }

            if (window.confirm('Clear all system logs? This cannot be undone.')) {
                form.submit();
            }
        });

        confirmBtn.addEventListener('click', function () {
            if (modal.open && typeof modal.close === 'function') {
                modal.close();
            }
            form.submit();
        });
    }

    initLogsClearConfirmation();

    function initApacheModulesSearch() {
        var input = document.getElementById('apache-module-search');
        var statusFilter = document.getElementById('apache-module-status-filter');
        var list = document.getElementById('apache-modules-list');
        var empty = document.getElementById('apache-modules-empty');
        if (!input || !list) {
            return;
        }

        var cards = Array.prototype.slice.call(list.querySelectorAll('[data-module-card]'));

        function applyFilter() {
            var query = String(input.value || '').trim().toLowerCase();
            var status = statusFilter ? String(statusFilter.value || 'all') : 'all';
            var visibleCount = 0;

            cards.forEach(function (card) {
                var title = String(card.getAttribute('data-module-title') || '').toLowerCase();
                var description = String(card.getAttribute('data-module-description') || '').toLowerCase();
                var enabled = String(card.getAttribute('data-module-enabled') || '0') === '1';

                var matchesText = query === ''
                    || title.indexOf(query) !== -1
                    || description.indexOf(query) !== -1;

                var matchesStatus = status === 'all'
                    || (status === 'enabled' && enabled)
                    || (status === 'disabled' && !enabled);

                var visible = matchesText && matchesStatus;
                card.hidden = !visible;
                if (visible) {
                    visibleCount += 1;
                }
            });

            if (empty) {
                empty.hidden = visibleCount !== 0;
            }
        }

        input.addEventListener('input', applyFilter);
        if (statusFilter) {
            statusFilter.addEventListener('change', applyFilter);
        }
        applyFilter();
    }

    initApacheModulesSearch();

    function initPasswordPolicyLiveFeedback() {
        var passwordInputs = document.querySelectorAll('input[data-password-policy-list]');
        if (!passwordInputs.length) {
            return;
        }

        function rulePassed(rule, value) {
            if (rule === 'length') {
                return value.length >= 8;
            }
            if (rule === 'uppercase') {
                return /[A-Z]/.test(value);
            }
            if (rule === 'special') {
                return /[^a-zA-Z0-9]/.test(value);
            }

            return false;
        }

        function applyState(item, state) {
            item.classList.remove('is-pending', 'is-invalid', 'is-valid');
            item.classList.add(state);
        }

        passwordInputs.forEach(function (input) {
            var listId = input.getAttribute('data-password-policy-list');
            if (!listId) {
                return;
            }

            var list = document.getElementById(listId);
            if (!list) {
                return;
            }

            function refreshPolicy() {
                var value = String(input.value || '');
                var hasTyped = value.length > 0;
                var items = list.querySelectorAll('li[data-rule]');

                items.forEach(function (item) {
                    var rule = String(item.getAttribute('data-rule') || '');
                    if (!hasTyped) {
                        applyState(item, 'is-pending');
                        return;
                    }

                    applyState(item, rulePassed(rule, value) ? 'is-valid' : 'is-invalid');
                });
            }

            input.addEventListener('input', refreshPolicy);
            input.addEventListener('blur', refreshPolicy);
            refreshPolicy();
        });
    }

    initPasswordPolicyLiveFeedback();

    function initDocrootDetectionDialog() {
        var dialog = document.getElementById('docroot-detection-dialog');
        var keepBtn = document.getElementById('docroot-detection-keep');
        if (!dialog || !keepBtn) {
            return;
        }

        keepBtn.addEventListener('click', function () {
            if (dialog.open) {
                dialog.close();
            }
        });
    }

    initDocrootDetectionDialog();

    /* ── Confirmation Page Secret Toggles ──────── */
    function initConfirmationSecretToggles() {
        var toggles = document.querySelectorAll('.confirm-secret-toggle');
        if (!toggles.length) {
            return;
        }

        toggles.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var container = btn.closest('.confirm-secret');
                if (!container) {
                    return;
                }

                var secretValue = container.getAttribute('data-secret-value');
                if (!secretValue) {
                    return;
                }

                var textNode = container.firstChild;
                if (!textNode || textNode.nodeType !== Node.TEXT_NODE) {
                    return;
                }

                var isShowing = container.getAttribute('data-secret-visible') === 'true';
                textNode.nodeValue = isShowing ? '••••••••••' : secretValue;
                container.setAttribute('data-secret-visible', isShowing ? 'false' : 'true');

                var icon = btn.querySelector('i');
                if (icon) {
                    icon.className = isShowing ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
                }
                btn.setAttribute('aria-label', isShowing ? 'Show password' : 'Hide password');
                btn.setAttribute('aria-pressed', isShowing ? 'false' : 'true');
            });
        });
    }

    initConfirmationSecretToggles();

    /* ── Setup Wizard Form Persistence ──────────── */
    function initSetupFormPersistence() {
        var setupForms = document.querySelectorAll('form[action*="setup"]');
        if (!setupForms.length) {
            return;
        }

        var STORAGE_KEY = 'vhost-manager-setup-form-data';

        // Restore form data from localStorage when page loads
        function restoreFormData() {
            try {
                var stored = localStorage.getItem(STORAGE_KEY);
                if (!stored) {
                    return;
                }

                var data = JSON.parse(stored);
                var inputs = document.querySelectorAll(
                    'form[action*="setup"] input[name]:not([type="hidden"]), ' +
                    'form[action*="setup"] select[name]:not([disabled]), ' +
                    'form[action*="setup"] textarea[name]'
                );

                inputs.forEach(function (input) {
                    var fieldName = input.getAttribute('name');
                    if (!fieldName || !data.hasOwnProperty(fieldName)) {
                        return;
                    }

                    var value = data[fieldName];

                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = value === true || value === 'true';
                    } else if (input.type === 'password') {
                        // Don't restore password fields for security
                        return;
                    } else {
                        input.value = value;
                    }
                });
            } catch (e) {
                console.error('Failed to restore form data:', e);
            }
        }

        // Save form data to localStorage as user types/changes
        function setupAutoSave() {
            var inputs = document.querySelectorAll(
                'form[action*="setup"] input[name]:not([type="hidden"]), ' +
                'form[action*="setup"] select[name]:not([disabled]), ' +
                'form[action*="setup"] textarea[name]'
            );

            function saveFormData() {
                try {
                    var data = {};
                    inputs.forEach(function (input) {
                        var fieldName = input.getAttribute('name');
                        if (!fieldName) {
                            return;
                        }

                        if (input.type === 'checkbox' || input.type === 'radio') {
                            data[fieldName] = input.checked;
                        } else if (input.type === 'password') {
                            // Don't save password fields for security
                            return;
                        } else {
                            data[fieldName] = input.value;
                        }
                    });

                    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                } catch (e) {
                    console.error('Failed to save form data:', e);
                }
            }

            inputs.forEach(function (input) {
                input.addEventListener('input', saveFormData);
                input.addEventListener('change', saveFormData);
            });
        }

        // Clear localStorage when setup is completed
        function setupClearOnSuccess() {
            setupForms.forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    // Check for validation errors by looking for error messages
                    // If no errors, clear storage after successful submission
                    var hasErrors = document.querySelector('.flash-error, .form-error, [role="alert"][class*="error"]');
                    if (!hasErrors) {
                        try {
                            localStorage.removeItem(STORAGE_KEY);
                        } catch (e) {
                            console.error('Failed to clear form data:', e);
                        }
                    }
                });
            });
        }

        restoreFormData();
        setupAutoSave();
        setupClearOnSuccess();
    }

    initSetupFormPersistence();

}());
