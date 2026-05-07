<?php declare(strict_types=1); ?>
<?php $fe = is_array($fieldErrors ?? null) ? $fieldErrors : []; ?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">First-time setup wizard</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Setup: DNS Integration</h1>
        <?php 
            $totalSteps = ($enableIntegrations ?? true) ? 5 : 3;
            $stepNumber = ($enableIntegrations ?? true) ? 3 : 1;
        ?>
        <p class="auth-subtitle">Step <?= $stepNumber ?> of <?= $totalSteps ?>: Configure DNS provider (optional)</p>

        <form class="form" method="post" action="/?route=setup-dns" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="dns_provider">Provider</label>
                <select class="form-select<?= !empty($fe['dns_provider']) ? ' is-error' : '' ?>" id="dns_provider" name="dns_provider">
                    <?php foreach (($dnsProviders ?? []) as $providerKey => $provider): ?>
                        <option
                            value="<?= e((string) $providerKey) ?>"
                            data-description="<?= e((string) ($provider['description'] ?? '')) ?>"
                            <?= (($selectedDnsProvider ?? '') === $providerKey) ? 'selected' : '' ?>
                        >
                            <?= e((string) ($provider['label'] ?? $providerKey)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($fe['dns_provider'])): ?><span class="form-field-error"><?= e((string) $fe['dns_provider']) ?></span><?php endif; ?>
                <span class="form-hint" id="dns_provider_hint"></span>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Custom Name</label>
                <input class="form-input<?= !empty($fe['name']) ? ' is-error' : '' ?>" id="name" type="text" name="name" value="<?= e((string) $name) ?>" placeholder="e.g. Main DNS, Production DNS">
                <?php if (!empty($fe['name'])): ?><span class="form-field-error"><?= e((string) $fe['name']) ?></span><?php else: ?><span class="form-hint">A label to identify this DNS integration.</span><?php endif; ?>
            </div>

            <div class="provider-fields" data-provider="cloudflare" id="setup-cloudflare-fields">
                <p class="form-hint" style="margin-bottom: 10px;">DNS providers are domain-based. Enable one now, then configure provider-specific settings per domain under Domains after setup.</p>
            </div>

            <div style="margin-top:20px; display:flex; gap:8px;">
                <a href="/?route=setup-proxy" class="btn btn--secondary" style="flex:1; text-align:center; text-decoration:none;">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <button class="btn btn--ghost" type="button" formnovalidate style="flex:1;" id="dns-skip-button">
                    Skip
                </button>
                <button class="btn btn--primary" type="submit" style="flex:1;" id="dns-primary-submit">
                    Enable &amp; Continue <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
(function () {
    var providerSelect = document.getElementById('dns_provider');
    var providerHint = document.getElementById('dns_provider_hint');
    var form = document.querySelector('form[action="/?route=setup-dns"]');
    var primarySubmit = document.getElementById('dns-primary-submit');
    var skipButton = document.getElementById('dns-skip-button');

    if (!providerSelect || !providerHint || !form || !primarySubmit) {
        return;
    }

    function submitWithHiddenAction(name, value) {
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = name;
        hidden.value = value;
        form.appendChild(hidden);
        form.submit();
    }

    function applyProviderState() {
        var option = providerSelect.options[providerSelect.selectedIndex];
        providerHint.textContent = option ? (option.getAttribute('data-description') || '') : '';
    }

    providerSelect.addEventListener('change', applyProviderState);
    applyProviderState();

    form.addEventListener('keydown', function (event) {
        var target = event.target;
        if (!target || target.tagName === 'TEXTAREA') {
            return;
        }

        if (event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        primarySubmit.click();
    });

    if (skipButton) {
        skipButton.addEventListener('click', function () {
            submitWithHiddenAction('skip', '1');
        });
    }
})();
</script>
