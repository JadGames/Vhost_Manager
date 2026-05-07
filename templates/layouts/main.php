<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VHost Manager</title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset_url('/assets/favicon/apple-touch-icon.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset_url('/assets/favicon/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset_url('/assets/favicon/favicon-16x16.png')) ?>">
    <link rel="shortcut icon" href="<?= e(asset_url('/assets/favicon/favicon.ico')) ?>">
    <link rel="manifest" href="<?= e(asset_url('/assets/favicon/site.webmanifest')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/app.css')) ?>">
    <script src="<?= e(asset_url('/assets/theme-init.js')) ?>"></script>
</head>
<body>
<?php if (empty($username)): ?>

    <div class="auth-wrap">
        <?php if (!empty($flash) && is_array($flash)): ?>
            <div class="auth-card" style="margin-bottom: 0; padding-bottom: 12px;">
                <div class="alert alert--<?= e((string) ($flash['type'] ?? 'error')) ?>">
                    <i class="fa-solid <?= ($flash['type'] ?? '') === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                    <?= e((string) ($flash['message'] ?? '')) ?>
                </div>
            </div>
        <?php endif; ?>
        <?php include __DIR__ . '/../' . $contentTemplate; ?>
        <div class="app-version app-version--auth" aria-label="Application version">
            Version: <?= e((string) $appVersion) ?>
        </div>
    </div>

<?php else: ?>

    <?php
        $currentRoute = (string) ($_GET['route'] ?? 'overview');
        $ctxDomain    = !empty($_GET['domain']) ? ' · ' . e((string) $_GET['domain']) : '';
        $pageTitles   = [
            'overview'                    => 'Overview',
            'domains'                     => 'Domains',
            'vhosts'                      => 'Virtual Hosts',
            'dashboard'                   => 'Virtual Hosts',
            'create-vhost'                => 'Create Virtual Host',
            'edit-vhost'                  => 'Edit Virtual Host',
            'delete-vhost'                => 'Delete Virtual Host',
            'logs'                        => 'System Logs',
            'settings'                    => 'Settings Overview',
            'settings-users'              => 'Users',
            'settings-integrations'       => 'Integrations',
            'settings-cloudflare'         => 'Cloudflare Settings',
            'settings-cloudflare-domains' => 'Cloudflare Domains',
            'settings-npm'                => 'NPM Settings',
            'settings-npm-ssl'            => 'NPM SSL Settings',
            'settings-apache-modules'     => 'Apache Modules',
        ];
        $pageTitle = e($pageTitles[$currentRoute] ?? ucwords(str_replace(['-', '_'], ' ', $currentRoute)));
        if ($currentRoute === 'edit-vhost' || $currentRoute === 'delete-vhost') {
            $pageTitle .= $ctxDomain;
        }

        $settingsRoutes = ['settings', 'settings-users', 'settings-integrations',
            'settings-cloudflare', 'settings-cloudflare-domains',
            'settings-npm', 'settings-npm-ssl', 'settings-apache-modules', 'logs'];
        $vhostRoutes = ['vhosts', 'dashboard', 'create-vhost', 'edit-vhost', 'delete-vhost'];
    ?>

    <div class="layout">

        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">

            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="logo-icon"><i class="fa-solid fa-server"></i></div>
                    <span class="sidebar-logo-text">VHost Manager</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>

            <nav class="sidebar-nav" aria-label="Main navigation">

                <a href="/?route=overview"
                   class="nav-item <?= $currentRoute === 'overview' ? 'is-active' : '' ?>"
                   title="Overview">
                    <i class="fa-solid fa-gauge nav-icon"></i>
                    <span class="nav-label">Overview</span>
                </a>

                <a href="/?route=domains"
                   class="nav-item <?= $currentRoute === 'domains' ? 'is-active' : '' ?>"
                   title="Domains">
                    <i class="fa-solid fa-globe nav-icon"></i>
                    <span class="nav-label">Domains</span>
                </a>

                <a href="/?route=vhosts"
                   class="nav-item <?= in_array($currentRoute, $vhostRoutes, true) ? 'is-active' : '' ?>"
                   title="Vhosts">
                    <i class="fa-solid fa-server nav-icon"></i>
                    <span class="nav-label">Vhosts</span>
                </a>

                <div class="nav-group <?= in_array($currentRoute, $settingsRoutes, true) ? 'is-open' : '' ?>" id="navGroupSettings">
                    <button type="button"
                            class="nav-item nav-item--toggle <?= in_array($currentRoute, $settingsRoutes, true) ? 'is-active' : '' ?>"
                            id="navSettingsToggle"
                            data-settings-url="/?route=settings"
                            title="Settings"
                            aria-expanded="<?= in_array($currentRoute, $settingsRoutes, true) ? 'true' : 'false' ?>">
                        <i class="fa-solid fa-gear nav-icon"></i>
                        <span class="nav-label">Settings</span>
                        <?php if ($isAdmin && $pendingModuleRequests > 0): ?>
                            <span class="notif-dot notif-dot--nav" title="<?= $pendingModuleRequests ?> module request(s)"></span>
                        <?php endif; ?>
                        <i class="fa-solid fa-chevron-down nav-chevron"></i>
                    </button>
                    <div class="nav-submenu">
                        <?php if ($isAdmin): ?>
                        <a href="/?route=settings-users"
                           class="nav-submenu-item <?= $currentRoute === 'settings-users' ? 'is-active' : '' ?>">Users</a>
                        <?php endif; ?>
                        <a href="/?route=settings-apache-modules"
                           class="nav-submenu-item <?= $currentRoute === 'settings-apache-modules' ? 'is-active' : '' ?>">
                            Apache Modules
                            <?php if ($isAdmin && $pendingModuleRequests > 0): ?>
                                <span class="notif-dot notif-dot--nav-sub" title="<?= $pendingModuleRequests ?> pending"></span>
                            <?php endif; ?>
                        </a>
                        <?php if ($enableIntegrations ?? true): ?>
                        <a href="/?route=settings-integrations"
                           class="nav-submenu-item <?= in_array($currentRoute, ['settings-integrations', 'settings-cloudflare', 'settings-cloudflare-domains', 'settings-npm', 'settings-npm-ssl'], true) ? 'is-active' : '' ?>">Integrations</a>
                        <?php endif; ?>
                        <?php if ($isAdmin): ?>
                        <a href="/?route=logs"
                           class="nav-submenu-item <?= $currentRoute === 'logs' ? 'is-active' : '' ?>">Logs</a>
                        <?php endif; ?>
                    </div>
                </div>

            </nav>

            <div class="sidebar-footer">
                <div class="user-card">
                    <div class="user-avatar"><?= strtoupper(substr((string) ($displayName ?? $username), 0, 1)) ?></div>
                    <div class="user-info">
                        <div class="user-name"><?= e((string) ($displayName ?? $username)) ?></div>
                        <div class="user-role"><?= e((string) ($accountRole ?? 'User')) ?></div>
                    </div>
                </div>
                <div class="sidebar-actions">
                    <div class="notifications-wrap" id="notificationsWrap">
                        <button class="icon-btn" id="notificationsToggle" title="Notifications" aria-label="Notifications" aria-expanded="false">
                            <i class="fa-solid fa-bell"></i>
                            <?php if (($unreadNotifications ?? 0) > 0): ?>
                                <span class="notif-dot notif-dot--btn" id="notificationsDot"></span>
                            <?php else: ?>
                                <span class="notif-dot notif-dot--btn" id="notificationsDot" hidden></span>
                            <?php endif; ?>
                        </button>
                        <div class="notifications-panel" id="notificationsPanel" hidden>
                            <div class="notifications-panel__header">
                                <span>Notifications</span>
                                <button type="button" class="btn btn--ghost btn--sm notifications-clear-all" id="notificationsClearAll">Clear all</button>
                            </div>
                            <div class="notifications-panel__list" id="notificationsList">
                                <?php if (!empty($notifications)): ?>
                                    <?php foreach ($notifications as $note): ?>
                                        <div class="notifications-item <?= !empty($note['is_read']) ? 'is-read' : 'is-unread' ?>">
                                            <button type="button" class="notifications-item__close" data-notification-clear="<?= (int) ($note['id'] ?? 0) ?>" aria-label="Clear notification">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                            <div class="notifications-item__message"><?= e((string) ($note['message'] ?? '')) ?></div>
                                            <div class="notifications-item__time"><?= e((string) ($note['created_at'] ?? '')) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="notifications-empty">No notifications yet.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <button class="icon-btn" id="themeToggle" title="Toggle theme" aria-label="Toggle theme">
                        <i class="fa-solid fa-sun" id="themeIcon"></i>
                    </button>
                    <form method="post" action="/?route=logout">
                        <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                        <button class="icon-btn icon-btn--danger" type="submit" title="Logout" aria-label="Logout">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </button>
                    </form>
                </div>
                <div class="app-version app-version--sidebar" aria-label="Application version">
                    Version: <?= e((string) $appVersion) ?>
                </div>
            </div>

        </aside>

        <!-- Mobile overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main -->
        <div class="main-wrapper" id="mainWrapper">

            <header class="topbar">
                <button class="topbar-toggle" id="topbarToggle" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-breadcrumb"><?= $pageTitle ?></div>
                <div class="topbar-right">
                    <?php if (in_array($currentRoute, ['vhosts', 'dashboard'], true)): ?>
                        <a href="/?route=create-vhost" class="btn btn--primary btn--sm">
                            <i class="fa-solid fa-plus"></i>
                            <span>New VHost</span>
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <main class="main-content">
                <script nonce="<?= e((string) ($cspNonce ?? '')) ?>">
                    window.VHM_NOTIFICATIONS_POLL_SECONDS = <?= (int) ($notificationPollSeconds ?? 120) ?>;
                    window.VHM_CSRF_TOKEN = <?= json_encode((string) ($csrfToken ?? '')) ?>;
                </script>
                <?php if (!empty($flash) && is_array($flash)): ?>
                    <div class="alert alert--<?= e((string) ($flash['type'] ?? 'error')) ?>">
                        <i class="fa-solid <?= ($flash['type'] ?? '') === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                        <?= e((string) ($flash['message'] ?? '')) ?>
                    </div>
                <?php endif; ?>

                <?php include __DIR__ . '/../' . $contentTemplate; ?>
            </main>

            <?php if (is_array($docrootDetection ?? null)): ?>
                <?php
                    $detectedNewBases     = is_array($docrootDetection['new_bases'] ?? null) ? $docrootDetection['new_bases'] : [];
                    $detectedAllowedBases = is_array($docrootDetection['allowed_bases'] ?? null) ? $docrootDetection['allowed_bases'] : [];
                    $detectedDefaultBase  = (string) ($docrootDetection['default_base'] ?? '');
                ?>
                <?php if ($detectedNewBases !== [] && $detectedAllowedBases !== []): ?>
                    <dialog id="docroot-detection-dialog" data-auto-open="true">
                        <div class="dialog-header">
                            <div class="dialog-header-text">
                                <p class="dialog-title">New Docroot Base Detected</p>
                                <p class="dialog-subtitle">Vhost Manager detected new document-root base path(s) from compose:</p>
                            </div>
                        </div>
                        <div class="dialog-body">
                            <ul class="password-policy" style="margin-top: 0; margin-bottom: 12px;">
                                <?php foreach ($detectedNewBases as $base): ?>
                                    <li class="is-valid"><?= e((string) $base) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <form class="form" method="post" action="/?route=settings-docroot-detection-action" autocomplete="off">
                                <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                                <input type="hidden" name="intent" value="set-default-base">
                                <div class="form-group" style="margin-bottom: 12px;">
                                    <label class="form-label" for="docroot_detect_default_base">Default Docroot Base</label>
                                    <select class="form-select" id="docroot_detect_default_base" name="default_docroot_base">
                                        <?php foreach ($detectedAllowedBases as $base): ?>
                                            <?php $base = (string) $base; ?>
                                            <option value="<?= e($base) ?>" <?= $base === $detectedDefaultBase ? 'selected' : '' ?>>
                                                <?= e($base) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="btn-group">
                                    <button class="btn btn--primary" type="submit">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                        Update Default Base
                                    </button>
                                    <button class="btn btn--ghost" id="docroot-detection-keep" type="button">Keep Current</button>
                                </div>
                            </form>
                            <form method="post" action="/?route=settings-docroot-detection-action" autocomplete="off" style="margin-top: 10px;">
                                <input type="hidden" name="csrf_token" value="<?= e((string) $csrfToken) ?>">
                                <input type="hidden" name="intent" value="disable-notifications">
                                <button class="btn btn--ghost" type="submit">Disable future detection prompts</button>
                            </form>
                        </div>
                    </dialog>
                <?php endif; ?>
            <?php endif; ?>

        </div><!-- /.main-wrapper -->

    </div><!-- /.layout -->

<?php endif; ?>

<script src="<?= e(asset_url('/assets/app.js')) ?>"></script>
</body>
</html>
