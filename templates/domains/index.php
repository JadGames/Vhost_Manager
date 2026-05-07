<?php declare(strict_types=1); ?>

<?php
$cfEnabled = !empty($cfEnabled);
$enableIntegrations = !empty($enableIntegrations);
$domains = is_array($domains ?? null) ? $domains : [];
$hasCloudflare = $cfEnabled && $enableIntegrations;
?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Domains</h1>
        <p class="page-description">Manage per-domain integration settings used by domain workflows.</p>
    </div>
    <div class="page-header-right">
        <button class="btn btn--primary" type="button" id="addDomainBtn">
            <i class="fa-solid fa-plus"></i>
            Add Domain
        </button>
    </div>
</div>

<!-- Domains Grid -->
<section class="form-card settings-card" style="max-width: 1000px;">
    <h2 class="settings-title">Saved Domains</h2>
    
    <?php if ($domains === []): ?>
        <p class="form-hint">No domains added yet. Click "Add Domain" to get started.</p>
    <?php else: ?>
        <div class="domains-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px;">
            <?php foreach ($domains as $row): 
                $hasCf = $hasCloudflare && is_array($row['cloudflare'] ?? null);
            ?>
                <div class="domain-tile" style="
                    background: #f9f9f9;
                    border: 1px solid #e0e0e0;
                    border-radius: 8px;
                    padding: 16px;
                    position: relative;
                ">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                        <i class="fa-solid fa-globe" style="color: #666;"></i>
                        <span style="font-weight: 500; color: #333; flex: 1;">
                            <?= e((string) ($row['domain'] ?? '')) ?>
                        </span>
                        <?php if ($hasCf): ?>
                            <i class="fa-solid fa-cloud" title="Cloudflare configured" style="color: #f39c12; font-size: 16px;"></i>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasCf): ?>
                        <div style="font-size: 12px; color: #999; margin-bottom: 12px;">
                            <i class="fa-solid fa-check-circle" style="color: #27ae60; margin-right: 4px;"></i>
                            Cloudflare configured
                        </div>
                    <?php else: ?>
                        <div style="font-size: 12px; color: #999; margin-bottom: 12px;">
                            <i class="fa-solid fa-circle-xmark" style="color: #e74c3c; margin-right: 4px;"></i>
                            No Cloudflare
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; gap: 6px;">
                        <button class="btn btn--secondary btn-sm edit-domain-btn" data-domain="<?= e((string) ($row['domain'] ?? '')) ?>" type="button" title="Edit">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                        <button class="btn btn--danger btn-sm delete-domain-btn" data-domain="<?= e((string) ($row['domain'] ?? '')) ?>" type="button" title="Delete">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Add/Edit Domain Modal -->
<div id="domainModal" style="
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    overflow-y: auto;
" onclick="if (event.target === this) closeDomainModal()">
    <div style="
        background: white;
        margin: 40px auto;
        padding: 24px;
        border-radius: 8px;
        max-width: 600px;
        width: 90%;
    ">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 id="modalTitle" style="margin: 0; font-size: 20px;">Add Domain</h2>
            <button type="button" onclick="closeDomainModal()" style="
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #999;
            ">×</button>
        </div>

        <form id="domainForm" method="post" action="/?route=domains-save" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="edit_mode" id="editMode" value="0">
            <input type="hidden" name="original_domain" id="originalDomain">

            <div class="form-group">
                <label class="form-label" for="domain">Domain</label>
                <input class="form-input" id="domain" type="text" name="domain" placeholder="example.com" required>
            </div>

            <?php if ($cfEnabled): ?>
                <fieldset class="form-fieldset">
                    <legend class="form-legend"><i class="fa-solid fa-cloud"></i> Cloudflare (Per Domain)</legend>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="cf_zone_id">Zone ID</label>
                            <input class="form-input" id="cf_zone_id" type="text" name="cf_zone_id" placeholder="32-char zone id">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="cf_api_token">API Token</label>
                            <div class="secret-input-wrap">
                                <input class="form-input" id="cf_api_token" type="password" name="cf_api_token" autocomplete="off" spellcheck="false">
                                <button class="secret-toggle-btn" type="button" data-secret-target="cf_api_token" aria-controls="cf_api_token" aria-label="Show secret" aria-pressed="false">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="cf_record_ip">Record IP</label>
                            <input class="form-input" id="cf_record_ip" type="text" name="cf_record_ip" placeholder="1.2.3.4">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="cf_ttl">TTL</label>
                            <input class="form-input" id="cf_ttl" type="number" name="cf_ttl" min="1" max="86400" value="120">
                        </div>
                    </div>

                    <label class="form-check">
                        <input type="checkbox" id="cf_proxied" name="cf_proxied" value="1" checked>
                        Proxied records by default
                    </label>
                </fieldset>
            <?php endif; ?>

            <div class="btn-group" style="margin-top: 20px; gap: 10px;">
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Domain
                </button>
                <button class="btn btn--secondary" type="button" onclick="closeDomainModal()">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.domains-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

.domain-tile {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 16px;
    transition: all 0.2s;
}

.domain-tile:hover {
    border-color: #007bff;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.1);
}

.btn-sm {
    padding: 6px 10px;
    font-size: 12px;
    min-width: auto;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
    }

    .domains-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addDomainBtn = document.getElementById('addDomainBtn');
    const editButtons = document.querySelectorAll('.edit-domain-btn');
    const deleteButtons = document.querySelectorAll('.delete-domain-btn');

    if (addDomainBtn) {
        addDomainBtn.addEventListener('click', function() {
            resetDomainForm();
            openDomainModal('Add Domain');
        });
    }

    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const domain = this.getAttribute('data-domain');
            loadDomainData(domain);
        });
    });

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const domain = this.getAttribute('data-domain');
            if (confirm('Are you sure you want to delete this domain?\n\n' + domain)) {
                deleteDomain(domain);
            }
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDomainModal();
        }
    });
});

function openDomainModal(title) {
    const modal = document.getElementById('domainModal');
    document.getElementById('modalTitle').textContent = title;
    modal.style.display = 'flex';
    modal.style.alignItems = 'center';
}

function closeDomainModal() {
    const modal = document.getElementById('domainModal');
    modal.style.display = 'none';
}

function resetDomainForm() {
    const form = document.getElementById('domainForm');
    form.reset();
    document.getElementById('editMode').value = '0';
    document.getElementById('originalDomain').value = '';
    document.getElementById('domain').disabled = false;
    
    // Clear Cloudflare fields if they exist
    if (document.getElementById('cf_zone_id')) {
        document.getElementById('cf_zone_id').value = '';
        document.getElementById('cf_api_token').value = '';
        document.getElementById('cf_record_ip').value = '';
        document.getElementById('cf_ttl').value = '120';
        document.getElementById('cf_proxied').checked = true;
    }
}

function loadDomainData(domain) {
    fetch('/?route=domains-edit&domain=' + encodeURIComponent(domain))
        .then(resp => resp.json())
        .then(data => {
            if (!data.ok) {
                alert('Error loading domain: ' + (data.message || 'Unknown error'));
                return;
            }

            // Populate form
            document.getElementById('domain').value = data.domain;
            document.getElementById('domain').disabled = true;
            document.getElementById('editMode').value = '1';
            document.getElementById('originalDomain').value = data.domain;

            // Fill Cloudflare fields if available
            if (document.getElementById('cf_zone_id')) {
                document.getElementById('cf_zone_id').value = data.cf_zone_id || '';
                document.getElementById('cf_api_token').value = data.cf_api_token || '';
                document.getElementById('cf_record_ip').value = data.cf_record_ip || '';
                document.getElementById('cf_ttl').value = data.cf_ttl || 120;
                document.getElementById('cf_proxied').checked = !!data.cf_proxied;
            }

            openDomainModal('Edit Domain');
        })
        .catch(err => {
            console.error('Error loading domain:', err);
            alert('Failed to load domain data');
        });
}

function deleteDomain(domain) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/?route=domains-delete';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = document.querySelector('input[name="csrf_token"]').value;
    
    const domainInput = document.createElement('input');
    domainInput.type = 'hidden';
    domainInput.name = 'domain';
    domainInput.value = domain;
    
    form.appendChild(csrfInput);
    form.appendChild(domainInput);
    document.body.appendChild(form);
    form.submit();
}
</script>
