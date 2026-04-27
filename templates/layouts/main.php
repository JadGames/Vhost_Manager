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

                <div class="nav-group">
                    <a href="/?route=settings"
                       class="nav-item <?= in_array($currentRoute, $settingsRoutes, true) ? 'is-active' : '' ?>"
                       title="Settings">
                        <i class="fa-solid fa-gear nav-icon"></i>
                        <span class="nav-label">Settings</span>
                    </a>
                    <div class="nav-submenu">
                        <a href="/?route=settings-users"
                           class="nav-submenu-item <?= $currentRoute === 'settings-users' ? 'is-active' : '' ?>">Users</a>
                        <a href="/?route=settings-apache-modules"
                           class="nav-submenu-item <?= $currentRoute === 'settings-apache-modules' ? 'is-active' : '' ?>">Apache Modules</a>
                        <a href="/?route=settings-integrations"
                           class="nav-submenu-item <?= in_array($currentRoute, ['settings-integrations', 'settings-cloudflare', 'settings-cloudflare-domains', 'settings-npm', 'settings-npm-ssl'], true) ? 'is-active' : '' ?>">Integrations</a>
                        <a href="/?route=logs"
                           class="nav-submenu-item <?= $currentRoute === 'logs' ? 'is-active' : '' ?>">Logs</a>
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
