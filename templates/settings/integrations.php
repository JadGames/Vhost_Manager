<?php declare(strict_types=1); ?>

<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title">Integrations</h1>
        <p class="page-description">Configure reverse proxy and DNS provider integrations.</p>
    </div>
</div>

<?php
    $allProviders     = $providers ?? [];
    $proxyProviders   = array_filter($allProviders, static fn ($p) => $p['category'] === 'proxy');
    $dnsProviders     = array_filter($allProviders, static fn ($p) => $p['category'] === 'dns');
    $proxyList        = $proxyIntegrations ?? [];
    $dnsList          = $dnsIntegrations ?? [];
?>

<!-- ── Reverse Proxy Section ── -->
<div class="integrations-section">
    <div class="integrations-section__header">
        <div class="integrations-section__title-wrap">
            <div class="integrations-section__icon"><i class="fa-solid fa-network-wired"></i></div>
            <div>
                <div class="integrations-section__title">Reverse Proxy</div>
                <div class="integrations-section__description">Manage proxy hosts and SSL certificates via a proxy provider.</div>
            </div>
        </div>
    </div>

    <div class="integration-tiles-grid">
        <?php foreach ($proxyList as $int): ?>
            <?php
                $pInfo = $allProviders[$int['provider'] ?? ''] ?? null;
                $icon  = $pInfo ? $pInfo['icon'] : 'fa-network-wired';
            ?>
            <div class="integration-tile"
                 data-open-edit="<?= e((string) ($int['id'] ?? '')) ?>"
                 title="Edit <?= e((string) ($int['name'] ?? '')) ?>">
                <div class="integration-tile__header">
                    <div class="integration-tile__icon">
                        <i class="fa-solid <?= e($icon) ?>"></i>
                    </div>
                    <span class="integration-tile__badge"><?= e(ucfirst((string) ($int['provider'] ?? ''))) ?></span>
                </div>
                <div>
                    <div class="integration-tile__name"><?= e((string) ($int['name'] ?? '')) ?></div>
                    <div class="integration-tile__provider"><?= e((string) ($pInfo['label'] ?? ($int['provider'] ?? ''))) ?></div>
                </div>
                <div class="integration-tile__actions">
                    <button class="btn btn--ghost btn--sm"
                            type="button"
                            data-test-integration="<?= e((string) ($int['id'] ?? '')) ?>">
                        <i class="fa-solid fa-bolt"></i> Test
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Add Proxy Integration tile -->
        <button class="integration-tile integration-tile--add"
                type="button"
                data-open-add-modal="proxy"
                aria-label="Add reverse proxy integration">
            <div class="integration-tile__add-icon"><i class="fa-solid fa-plus"></i></div>
            <div class="integration-tile__add-label">Add Integration</div>
            <div class="integration-tile__add-hint">Reverse Proxy provider</div>
        </button>
    </div>
</div>

<!-- ── DNS Section ── -->
<div class="integrations-section">
    <div class="integrations-section__header">
        <div class="integrations-section__title-wrap">
            <div class="integrations-section__icon"><i class="fa-solid fa-cloud"></i></div>
            <div>
                <div class="integrations-section__title">DNS</div>
                <div class="integrations-section__description">Automatically create and manage DNS records via your DNS provider.</div>
            </div>
        </div>
    </div>

    <div class="integration-tiles-grid">
        <?php foreach ($dnsList as $int): ?>
            <?php
                $pInfo = $allProviders[$int['provider'] ?? ''] ?? null;
                $icon  = $pInfo ? $pInfo['icon'] : 'fa-cloud';
            ?>
            <div class="integration-tile"
                 data-open-edit="<?= e((string) ($int['id'] ?? '')) ?>"
                 title="Edit <?= e((string) ($int['name'] ?? '')) ?>">
                <div class="integration-tile__header">
                    <div class="integration-tile__icon">
                        <i class="fa-solid <?= e($icon) ?>"></i>
                    </div>
                    <span class="integration-tile__badge"><?= e(ucfirst((string) ($int['provider'] ?? ''))) ?></span>
                </div>
                <div>
                    <div class="integration-tile__name"><?= e((string) ($int['name'] ?? '')) ?></div>
                    <div class="integration-tile__provider"><?= e((string) ($pInfo['label'] ?? ($int['provider'] ?? ''))) ?></div>
                </div>
                <div class="integration-tile__actions">
                    <button class="btn btn--ghost btn--sm"
                            type="button"
                            data-test-integration="<?= e((string) ($int['id'] ?? '')) ?>">
                        <i class="fa-solid fa-bolt"></i> Test
                    </button>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Add DNS Integration tile -->
        <button class="integration-tile integration-tile--add"
                type="button"
                data-open-add-modal="dns"
                aria-label="Add DNS integration">
            <div class="integration-tile__add-icon"><i class="fa-solid fa-plus"></i></div>
            <div class="integration-tile__add-label">Add Integration</div>
            <div class="integration-tile__add-hint">DNS provider</div>
        </button>
    </div>
</div>

<!-- ══ ADD INTEGRATION MODAL ══ -->
<dialog id="add-integration-modal" aria-modal="true" aria-labelledby="add-modal-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="add-modal-title">Add Integration</p>
            <p class="dialog-subtitle" id="add-modal-subtitle">Choose a provider to configure.</p>
        </div>
        <button class="dialog-close-btn" type="button" id="add-modal-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <form class="form" method="post" action="/?route=settings-integrations-action" autocomplete="off" id="add-integration-form">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="add">

            <div class="form-group">
                <label class="form-label" for="add_name">Custom Name</label>
                <input class="form-input" id="add_name" type="text" name="name" placeholder="e.g. Main NPM, Production CF" required>
                <span class="form-hint">A label to identify this integration — you can add multiple of the same provider.</span>
            </div>

            <div class="form-group">
                <label class="form-label" for="add_provider">Provider</label>
                <select class="form-select" id="add_provider" name="provider" required>
                    <option value="">— Select a provider —</option>
                    <?php foreach ($allProviders as $key => $p): ?>
                        <option value="<?= e($key) ?>" data-category="<?= e($p['category']) ?>">
                            <?= e($p['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Dynamic provider fields -->
            <?php foreach ($allProviders as $key => $p): ?>
                <div class="provider-fields" data-provider="<?= e($key) ?>">
                    <?php foreach ($p['fields'] as $field): ?>
                        <div class="form-group" style="margin-bottom: 14px;">
                            <label class="form-label" for="add_<?= e($key) ?>_<?= e($field['name']) ?>">
                                <?= e($field['label']) ?>
                                <?php if ($field['required']): ?><span style="color:var(--danger)"> *</span><?php endif; ?>
                            </label>
                            <?php if ($field['type'] === 'checkbox'): ?>
                                <label class="form-check">
                                    <input type="checkbox"
                                           id="add_<?= e($key) ?>_<?= e($field['name']) ?>"
                                           name="settings[<?= e($field['name']) ?>]"
                                           value="1"
                                           <?= $field['default'] === '1' ? 'checked' : '' ?>>
                                    <?= e($field['label']) ?>
                                </label>
                            <?php elseif ($field['type'] === 'password'): ?>
                                <div class="secret-input-wrap">
                                    <input class="form-input"
                                           id="add_<?= e($key) ?>_<?= e($field['name']) ?>"
                                           type="password"
                                           name="settings[<?= e($field['name']) ?>]"
                                           placeholder="<?= e($field['placeholder']) ?>"
                                           autocomplete="off"
                                           spellcheck="false">
                                    <button class="secret-toggle-btn" type="button"
                                            data-secret-target="add_<?= e($key) ?>_<?= e($field['name']) ?>"
                                            aria-label="Show secret">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <input class="form-input"
                                       id="add_<?= e($key) ?>_<?= e($field['name']) ?>"
                                       type="<?= e($field['type']) ?>"
                                       name="settings[<?= e($field['name']) ?>]"
                                       value="<?= e($field['default']) ?>"
                                       placeholder="<?= e($field['placeholder']) ?>"
                                       <?= $field['required'] ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="dialog-footer">
                <button class="btn btn--ghost" type="button" id="add-modal-cancel">Cancel</button>
                <button class="btn btn--primary" type="submit" id="add-modal-submit" disabled>
                    <i class="fa-solid fa-plus"></i> Add Integration
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- ══ EDIT INTEGRATION MODAL ══ -->
<dialog id="edit-integration-modal" aria-modal="true" aria-labelledby="edit-modal-title">
    <div class="dialog-header">
        <div class="dialog-header-text">
            <p class="dialog-title" id="edit-modal-title">Edit Integration</p>
            <p class="dialog-subtitle" id="edit-modal-subtitle"></p>
        </div>
        <button class="dialog-close-btn" type="button" id="edit-modal-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="dialog-body">
        <form class="form" method="post" action="/?route=settings-integrations-action" autocomplete="off" id="edit-integration-form">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label class="form-label" for="edit_name">Custom Name</label>
                <input class="form-input" id="edit_name" type="text" name="name" required>
            </div>

            <div id="edit-provider-fields">
                <!-- Populated by JS -->
            </div>

            <div class="dialog-footer">
                <button class="btn btn--danger btn--sm" type="button" id="edit-modal-delete">
                    <i class="fa-solid fa-trash"></i> Remove
                </button>
                <div style="flex:1"></div>
                <button class="btn btn--ghost" type="button" id="edit-modal-cancel">Cancel</button>
                <button class="btn btn--primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
            </div>
        </form>

        <!-- Delete sub-form -->
        <form method="post" action="/?route=settings-integrations-action" id="edit-delete-form" style="display:none">
            <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
            <input type="hidden" name="intent" value="delete">
            <input type="hidden" name="id" id="edit_delete_id">
        </form>
    </div>
</dialog>

<!-- All integration data for JS -->
<script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
var VHM_INTEGRATIONS = <?= json_encode(array_map(static function (array $i): array {
    // Don't expose secret values to JS
    $safe = $i;
    $safe['settings'] = array_map(static function ($v) { return ''; }, $i['settings'] ?? []);
    return $safe;
}, $integrations ?? []), JSON_UNESCAPED_SLASHES) ?: '[]' ?>;

var VHM_PROVIDERS = <?= json_encode(array_map(static function (array $p): array {
    // Strip field defaults — only need field metadata for building forms
    return ['label' => $p['label'], 'category' => $p['category'], 'icon' => $p['icon'],
            'fields' => array_map(static fn ($f) => ['name' => $f['name'], 'label' => $f['label'], 'type' => $f['type'], 'required' => $f['required'], 'placeholder' => $f['placeholder']], $p['fields'])];
}, $allProviders), JSON_UNESCAPED_SLASHES) ?: '{}' ?>;

(function () {
    var addModal   = document.getElementById('add-integration-modal');
    var editModal  = document.getElementById('edit-integration-modal');
    var addForm    = document.getElementById('add-integration-form');
    var addProvider = document.getElementById('add_provider');
    var addSubmit  = document.getElementById('add-modal-submit');
    var addSubtitle = document.getElementById('add-modal-subtitle');

    if (!addModal || !editModal || !addForm || !addProvider || !addSubmit || !addSubtitle) {
        return;
    }

    function openDialog(dialogEl) {
        if (!dialogEl) {
            return;
        }

        if (typeof dialogEl.showModal === 'function') {
            dialogEl.showModal();
            return;
        }

        dialogEl.setAttribute('open', 'open');
    }

    function closeDialog(dialogEl) {
        if (!dialogEl) {
            return;
        }

        if (typeof dialogEl.close === 'function') {
            dialogEl.close();
            return;
        }

        dialogEl.removeAttribute('open');
    }

    // ── Provider dropdown filtering for add modal ──
    function filterAddProviders(category) {
        Array.from(addProvider.options).forEach(function (opt) {
            if (!opt.value) { opt.hidden = false; return; }
            opt.hidden = category ? opt.dataset.category !== category : false;
        });
        addProvider.value = '';
        showProviderFields(null);
        addSubmit.disabled = true;
    }

    function showProviderFields(providerKey) {
        document.querySelectorAll('#add-integration-form .provider-fields').forEach(function (el) {
            el.classList.toggle('is-active', el.dataset.provider === providerKey);
            // disable/enable required inputs inside hidden sections
            el.querySelectorAll('input, select').forEach(function (inp) {
                if (el.dataset.provider !== providerKey) {
                    inp.removeAttribute('required');
                } else {
                    var prov = VHM_PROVIDERS[providerKey];
                    if (prov) {
                        prov.fields.forEach(function (f) {
                            if (f.name === inp.name.replace('settings[', '').replace(']', '') && f.required) {
                                inp.setAttribute('required', '');
                            }
                        });
                    }
                }
            });
        });
    }

    addProvider.addEventListener('change', function () {
        var key = addProvider.value;
        showProviderFields(key || null);
        addSubmit.disabled = !key;
    });

    // ── Open Add modal ──
    document.addEventListener('click', function (event) {
        var addBtn = event.target.closest('[data-open-add-modal]');
        if (addBtn) {
            var category = addBtn.getAttribute('data-open-add-modal');
            filterAddProviders(category);
            addForm.reset();
            var label = category === 'proxy' ? 'Reverse Proxy' : 'DNS';
            addSubtitle.textContent = 'Select a ' + label + ' provider to configure.';
            addSubmit.disabled = true;
            openDialog(addModal);
            return;
        }

        var editTile = event.target.closest('[data-open-edit]');
        if (editTile) {
            if (event.target.closest('button')) {
                return;
            }

            var id = editTile.getAttribute('data-open-edit');
            var integration = VHM_INTEGRATIONS.find(function (i) { return i.id === id; });
            if (!integration) {
                return;
            }

            document.getElementById('edit_id').value = id;
            document.getElementById('edit_delete_id').value = id;
            document.getElementById('edit_name').value = integration.name;
            document.getElementById('edit-modal-subtitle').textContent =
                (VHM_PROVIDERS[integration.provider] ? VHM_PROVIDERS[integration.provider].label : integration.provider);

            // Build provider fields dynamically
            var container = document.getElementById('edit-provider-fields');
            var prov = VHM_PROVIDERS[integration.provider];
            container.innerHTML = '';
            if (prov) {
                prov.fields.forEach(function (field) {
                    var value = (integration.settings && integration.settings[field.name]) || '';
                    var fg = document.createElement('div');
                    fg.className = 'form-group';
                    fg.style.marginBottom = '14px';

                    var labelNode = document.createElement('label');
                    labelNode.className = 'form-label';
                    labelNode.htmlFor = 'edit_field_' + field.name;
                    labelNode.textContent = field.label;
                    if (field.required) {
                        var star = document.createElement('span');
                        star.style.color = 'var(--danger)';
                        star.textContent = ' *';
                        labelNode.appendChild(star);
                    }
                    fg.appendChild(labelNode);

                    if (field.type === 'checkbox') {
                        var lbl = document.createElement('label');
                        lbl.className = 'form-check';
                        var cb = document.createElement('input');
                        cb.type = 'checkbox';
                        cb.id = 'edit_field_' + field.name;
                        cb.name = 'settings[' + field.name + ']';
                        cb.value = '1';
                        cb.checked = value === '1';
                        lbl.appendChild(cb);
                        lbl.appendChild(document.createTextNode(' ' + field.label));
                        fg.appendChild(lbl);
                    } else if (field.type === 'password') {
                        var wrap = document.createElement('div');
                        wrap.className = 'secret-input-wrap';
                        var inp = document.createElement('input');
                        inp.className = 'form-input';
                        inp.id = 'edit_field_' + field.name;
                        inp.type = 'password';
                        inp.name = 'settings[' + field.name + ']';
                        inp.placeholder = field.placeholder || '(leave blank to keep current)';
                        inp.autocomplete = 'off';
                        inp.spellcheck = false;
                        var toggleBtn = document.createElement('button');
                        toggleBtn.type = 'button';
                        toggleBtn.className = 'secret-toggle-btn';
                        toggleBtn.setAttribute('aria-label', 'Show secret');
                        toggleBtn.innerHTML = '<i class="fa-solid fa-eye"></i>';
                        toggleBtn.addEventListener('click', function () {
                            inp.type = inp.type === 'password' ? 'text' : 'password';
                        });
                        wrap.appendChild(inp);
                        wrap.appendChild(toggleBtn);
                        fg.appendChild(wrap);
                        var hint = document.createElement('span');
                        hint.className = 'form-hint';
                        hint.textContent = 'Leave blank to keep the current value.';
                        fg.appendChild(hint);
                    } else {
                        var fieldInput = document.createElement('input');
                        fieldInput.className = 'form-input';
                        fieldInput.id = 'edit_field_' + field.name;
                        fieldInput.type = field.type;
                        fieldInput.name = 'settings[' + field.name + ']';
                        fieldInput.value = value;
                        fieldInput.placeholder = field.placeholder || '';
                        if (field.required) {
                            fieldInput.required = true;
                        }
                        fg.appendChild(fieldInput);
                    }

                    container.appendChild(fg);
                });
            }

            openDialog(editModal);
        }
    });

    // ── Close Add modal ──
    document.getElementById('add-modal-close').addEventListener('click', function () { closeDialog(addModal); });
    document.getElementById('add-modal-cancel').addEventListener('click', function () { closeDialog(addModal); });
    addModal.addEventListener('click', function (e) { if (e.target === addModal) closeDialog(addModal); });

    // ── Close Edit modal ──
    document.getElementById('edit-modal-close').addEventListener('click', function () { closeDialog(editModal); });
    document.getElementById('edit-modal-cancel').addEventListener('click', function () { closeDialog(editModal); });
    editModal.addEventListener('click', function (e) { if (e.target === editModal) closeDialog(editModal); });

    // ── Delete button ──
    document.getElementById('edit-modal-delete').addEventListener('click', function () {
        if (confirm('Remove this integration?')) {
            document.getElementById('edit-delete-form').submit();
        }
    });

    // ── Test connection ──
    document.querySelectorAll('[data-test-integration]').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            event.stopPropagation();
            var id = btn.getAttribute('data-test-integration');
            var originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            fetch('/?route=settings-integrations-test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(
                    document.querySelector('#add-integration-form input[name=csrf_token]').value
                )
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                btn.innerHTML = data.ok
                    ? '<i class="fa-solid fa-circle-check" style="color:var(--accent)"></i> OK'
                    : '<i class="fa-solid fa-circle-xmark" style="color:var(--danger)"></i> Failed';
                setTimeout(function () { btn.innerHTML = originalHtml; }, 3000);
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        });
    });
}());
</script>
