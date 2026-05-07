<?php declare(strict_types=1); ?>
<?php $fe = is_array($fieldErrors ?? null) ? $fieldErrors : []; ?>
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-brand-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
        <div class="auth-brand-name">VHost Manager</div>
        <div class="auth-brand-tagline">First-time setup wizard</div>
    </div>

    <div class="auth-box">
        <h1 class="auth-title">Setup: Add Domains</h1>
        <?php 
            $totalSteps = ($enableIntegrations ?? true) ? 5 : 3;
            $stepNumber = ($enableIntegrations ?? true) ? 4 : 2;
        ?>
        <p class="auth-subtitle">Step <?= $stepNumber ?> of <?= $totalSteps ?>: Add one or more domains (optional)</p>

        <form class="form" method="post" action="/?route=setup-domain" autocomplete="off" id="domain-form">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="domain_input">Domain</label>
                <div class="app-url-input-row">
                    <input class="form-input" id="domain_input" type="text" placeholder="example.com" autocomplete="off" spellcheck="false">
                    <button class="btn btn--ghost btn--sm" type="button" id="add-domain-btn">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                <span class="form-hint">Enter domain names to manage with Vhost Manager.</span>
            </div>

            <!-- Domains list -->
            <div id="domains-list-container" style="margin-top: 16px; margin-bottom: 16px;">
                <div id="domains-list" style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php foreach (($setupDomains ?? []) as $domainName): ?>
                        <div class="domain-tile" data-domain="<?= e((string) $domainName) ?>">
                            <span><?= e((string) $domainName) ?></span>
                            <button type="button" class="domain-tile-btn delete-domain-btn" title="Remove"><i class="fa-solid fa-trash-can"></i></button>
                            <button type="button" class="domain-tile-btn edit-domain-btn" title="Edit"><i class="fa-solid fa-pencil"></i></button>
                            <input type="hidden" name="domains[]" value="<?= e((string) $domainName) ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($setupDomains)): ?>
                    <p id="no-domains-msg" style="color: var(--text-3); font-size: 0.9rem;">No domains added yet.</p>
                <?php endif; ?>
            </div>

            <div style="margin-top:20px; display:flex; gap:8px;">
                <a href="/?route=<?= ($enableIntegrations ?? true) ? 'setup-dns' : 'setup' ?>" class="btn btn--secondary" style="flex:1; text-align:center; text-decoration:none;">
                    <i class="fa-solid fa-arrow-left"></i> Back
                </a>
                <button class="btn btn--ghost" type="button" style="flex:1;" id="domain-skip-btn">
                    Skip
                </button>
                <button class="btn btn--primary" type="submit" style="flex:1;">
                    Continue <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.domain-tile {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: var(--bg-2);
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.875rem;
}
.domain-tile-btn {
    padding: 2px 4px;
    background: none;
    border: none;
    color: var(--text-3);
    cursor: pointer;
    font-size: 0.75rem;
    transition: color 0.2s;
}
.domain-tile-btn:hover {
    color: var(--text-1);
}
</style>

<script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
(function () {
    var form = document.getElementById('domain-form');
    var input = document.getElementById('domain_input');
    var addBtn = document.getElementById('add-domain-btn');
    var skipBtn = document.getElementById('domain-skip-btn');
    var domainsList = document.getElementById('domains-list');
    var noDomainMsg = document.getElementById('no-domains-msg');

    function updateDomainsList() {
        var tiles = domainsList.querySelectorAll('.domain-tile');
        if (tiles.length === 0 && noDomainMsg) {
            noDomainMsg.style.display = 'block';
        } else if (noDomainMsg) {
            noDomainMsg.style.display = 'none';
        }
    }

    function addDomain() {
        var domain = input.value.trim().toLowerCase();
        if (!domain) {
            window.alert('Please enter a domain name');
            return;
        }

        var domainRegex = /^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/;
        if (!domainRegex.test(domain)) {
            window.alert('Please enter a valid domain name (e.g., example.com)');
            return;
        }

        var existing = domainsList.querySelector('[data-domain="' + domain + '"]');
        if (existing) {
            window.alert('Domain already added');
            return;
        }

        var tile = document.createElement('div');
        tile.className = 'domain-tile';
        tile.setAttribute('data-domain', domain);
        tile.innerHTML = 
            '<span>' + domain + '</span>' +
            '<button type="button" class="domain-tile-btn delete-domain-btn" title="Remove"><i class="fa-solid fa-trash-can"></i></button>' +
            '<button type="button" class="domain-tile-btn edit-domain-btn" title="Edit"><i class="fa-solid fa-pencil"></i></button>' +
            '<input type="hidden" name="domains[]" value="' + domain + '">';

        var deleteBtn = tile.querySelector('.delete-domain-btn');
        deleteBtn.addEventListener('click', function (e) {
            e.preventDefault();
            tile.remove();
            updateDomainsList();
        });

        var editBtn = tile.querySelector('.edit-domain-btn');
        editBtn.addEventListener('click', function (e) {
            e.preventDefault();
            input.value = domain;
            tile.remove();
            input.focus();
            updateDomainsList();
        });

        domainsList.appendChild(tile);
        input.value = '';
        input.focus();
        updateDomainsList();
    }

    if (addBtn) {
        addBtn.addEventListener('click', function (e) {
            e.preventDefault();
            addDomain();
        });
    }

    if (input) {
        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addDomain();
            }
        });
    }

    if (skipBtn && form) {
        skipBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'skip';
            hidden.value = '1';
            form.appendChild(hidden);
            form.submit();
        });
    }

    updateDomainsList();
})();
</script>
