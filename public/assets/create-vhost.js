(function () {
    'use strict';

    var form = document.getElementById('create-vhost-form');
    if (!form) {
        return;
    }

    var baseDomain = String(form.getAttribute('data-base-domain') || '').trim().toLowerCase();
    var defaultBase = String(form.getAttribute('data-default-base') || '/var/www').trim();
    var subdomainInput = document.getElementById('subdomain');
    var domainInput = document.getElementById('domain');
    var domainHidden = document.getElementById('domain-hidden');
    var docrootBaseInput = document.getElementById('docroot_base');
    var aliasInput = document.getElementById('alias');
    var docrootPreview = document.getElementById('docroot-preview');
    var fqdnPreview = document.getElementById('fqdn-preview');
    var defaultProjectPlaceholder = aliasInput ? String(aliasInput.getAttribute('placeholder') || 'example-project') : 'example-project';
    var cloudflareToggle = document.getElementById('create_cloudflare');
    var npmToggle = document.getElementById('create_npm');
    var npmFieldset = document.getElementById('npm-options-fieldset');

    var sslEnabled = document.getElementById('npm_ssl_enabled');
    var sslForced = document.getElementById('npm_ssl_forced');
    var http2 = document.getElementById('npm_http2_support');
    var hsts = document.getElementById('npm_hsts_enabled');
    var hstsSubdomains = document.getElementById('npm_hsts_subdomains');
    var certId = document.getElementById('npm_certificate_id');

    var dialog = document.getElementById('create-vhost-confirm');
    var confirmFqdn = document.getElementById('confirm-fqdn');
    var confirmAlias = document.getElementById('confirm-alias');
    var confirmDocroot = document.getElementById('confirm-docroot');
    var confirmIntegrations = document.getElementById('confirm-integrations');
    var confirmNpmSsl = document.getElementById('confirm-npm-ssl');
    var confirmCreate = document.getElementById('confirm-create');
    var confirmCancel = document.getElementById('confirm-cancel');

    var approved = false;

    function normalizeLabel(value) {
        return String(value || '').trim().toLowerCase().replace(/^\.+|\.+$/g, '');
    }

    function computeProjectName() {
        return aliasInput ? normalizeLabel(aliasInput.value) : '';
    }

    function computeFolderName(fqdn) {
        return computeProjectName() || fqdn;
    }

    function computeDocroot(fqdn) {
        var folderName = computeFolderName(fqdn);
        var base = docrootBaseInput && docrootBaseInput.value.trim() ? docrootBaseInput.value.trim() : defaultBase;
        return folderName ? base.replace(/\/+$/, '') + '/' + folderName : '';
    }

    function computeFqdn() {
        if (subdomainInput && baseDomain) {
            var left = normalizeLabel(subdomainInput.value);
            return left ? left + '.' + baseDomain : '';
        }

        if (domainInput) {
            return normalizeLabel(domainInput.value);
        }

        return '';
    }

    function updatePreview() {
        var fqdn = computeFqdn();
        var projectName = computeProjectName();
        if (fqdnPreview) {
            fqdnPreview.textContent = fqdn || '-';
            fqdnPreview.classList.toggle('is-empty', !fqdn);
        }

        if (domainHidden) {
            domainHidden.value = fqdn;
        }

        if (docrootPreview) {
            docrootPreview.textContent = computeDocroot(fqdn) || '-';
        }

        if (aliasInput && !projectName && fqdn) {
            aliasInput.setAttribute('placeholder', fqdn);
        } else if (aliasInput && !projectName) {
            aliasInput.setAttribute('placeholder', defaultProjectPlaceholder);
        }
    }

    function collectIntegrationSummary() {
        var parts = [];
        if (cloudflareToggle && cloudflareToggle.checked) {
            parts.push('Cloudflare');
        }
        if (npmToggle && npmToggle.checked) {
            parts.push('NPM');
        }

        return parts.length ? parts.join(', ') : 'Apache only';
    }

    function setSslControlState() {
        var npmEnabled = !npmToggle || !!npmToggle.checked;

        if (npmFieldset) {
            npmFieldset.classList.toggle('is-disabled', !npmEnabled);
        }

        if (!sslEnabled) {
            return;
        }

        sslEnabled.disabled = !npmEnabled;
        var enabled = npmEnabled && !!sslEnabled.checked;
        if (sslForced) {
            sslForced.disabled = !enabled;
        }
        if (http2) {
            http2.disabled = !enabled;
        }
        if (hsts) {
            hsts.disabled = !enabled;
        }
        if (hstsSubdomains) {
            hstsSubdomains.disabled = !enabled || !hsts.checked;
        }
        if (certId) {
            certId.disabled = !enabled;
        }
    }

    function collectSslSummary() {
        if (npmToggle && !npmToggle.checked) {
            return 'not creating NPM host';
        }

        if (!sslEnabled) {
            return 'not configured';
        }

        if (!sslEnabled.checked) {
            return 'disabled';
        }

        var parts = ['enabled'];
        if (certId && certId.value.trim()) {
            parts.push('cert #' + certId.value.trim());
        }
        if (sslForced && sslForced.checked) {
            parts.push('force SSL');
        }
        if (http2 && http2.checked) {
            parts.push('HTTP/2');
        }
        if (hsts && hsts.checked) {
            parts.push('HSTS');
        }
        if (hstsSubdomains && hstsSubdomains.checked) {
            parts.push('HSTS subdomains');
        }

        return parts.join(', ');
    }

    function showFallbackConfirm() {
        var fqdn = computeFqdn();
        var projectName = computeProjectName();
        var docroot = computeDocroot(fqdn);
        var integrations = collectIntegrationSummary();
        var sslSummary = collectSslSummary();
        var message = 'FQDN: ' + fqdn + '\nProject Name: ' + (projectName || '-') + '\nDocroot: ' + docroot + '\nIntegrations: ' + integrations + '\nNPM SSL: ' + sslSummary + '\n\nProceed?';

        return window.confirm(message);
    }

    updatePreview();
    setSslControlState();

    if (subdomainInput) {
        subdomainInput.addEventListener('input', updatePreview);
        subdomainInput.addEventListener('blur', updatePreview);
    }

    if (domainInput) {
        domainInput.addEventListener('input', updatePreview);
        domainInput.addEventListener('blur', updatePreview);
    }

    if (aliasInput) {
        aliasInput.addEventListener('input', updatePreview);
        aliasInput.addEventListener('blur', updatePreview);
    }

    if (docrootBaseInput) {
        docrootBaseInput.addEventListener('change', updatePreview);
    }

    if (cloudflareToggle) {
        cloudflareToggle.addEventListener('change', updatePreview);
    }

    if (npmToggle) {
        npmToggle.addEventListener('change', function () {
            setSslControlState();
            updatePreview();
        });
    }

    if (sslEnabled) {
        sslEnabled.addEventListener('change', function () {
            setSslControlState();
        });
    }

    if (hsts) {
        hsts.addEventListener('change', function () {
            setSslControlState();
        });
    }

    form.addEventListener('submit', function (event) {
        updatePreview();

        var fqdn = computeFqdn();
        if (!fqdn) {
            return;
        }

        if (approved) {
            approved = false;
            return;
        }

        if (!dialog || typeof dialog.showModal !== 'function') {
            if (!showFallbackConfirm()) {
                event.preventDefault();
            }
            return;
        }

        event.preventDefault();
        var projectName = computeProjectName();
        var docroot = computeDocroot(fqdn);
        var integrations = collectIntegrationSummary();
        var sslSummary = collectSslSummary();

        if (confirmFqdn) {
            confirmFqdn.textContent = fqdn;
        }
        if (confirmAlias) {
            confirmAlias.textContent = projectName || '-';
        }
        if (confirmDocroot) {
            confirmDocroot.textContent = docroot;
        }
        if (confirmIntegrations) {
            confirmIntegrations.textContent = integrations;
        }
        if (confirmNpmSsl) {
            confirmNpmSsl.textContent = sslSummary;
        }

        dialog.showModal();
    });

    if (confirmCreate) {
        confirmCreate.addEventListener('click', function () {
            approved = true;
            if (dialog && dialog.open) {
                dialog.close();
            }
            form.requestSubmit();
        });
    }

    if (confirmCancel) {
        confirmCancel.addEventListener('click', function () {
            if (dialog && dialog.open) {
                dialog.close();
            }
        });
    }
})();
