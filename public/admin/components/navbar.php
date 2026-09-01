<?php
/** @var array $admin_context Injected by public/admin/view.php before include. */
$page_title = $page_title ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? "Welcome back, here's what's happening today";
$admin_context = $admin_context ?? [];
?>

<div class="topbar d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div class="d-flex align-items-center gap-3">
    <button class="btn btn-outline-secondary toggle-btn" id="toggleSidebar" type="button" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
    <div>
      <div class="section-title mb-0"><?= htmlspecialchars($page_title); ?></div>
      <small class="text-secondary"><?= htmlspecialchars($page_subtitle); ?></small>
    </div>
  </div>
  <div class="d-flex align-items-center gap-3 header-actions">
    <div class="header-search" id="headerSearchWrap">
      <div class="input-group">
        <button class="input-group-text bg-white border-end-0" type="button" id="headerSearchIcon" aria-label="Focus search"><i class="bi bi-search"></i></button>
        <input type="text" class="form-control border-start-0" id="headerSearchInput" placeholder="Search sections...">
      </div>
      <div class="header-search-results d-none" id="headerSearchResults"></div>
    </div>
    <div class="dropdown">
      <button class="btn btn-icon header-bell" type="button" id="headerBellBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <span class="notification-dot"></span>
      </button>
      <div class="dropdown-menu dropdown-menu-end header-notifications" aria-labelledby="headerBellBtn">
        <div class="dropdown-header">Notifications</div>
        <div class="px-3 py-2 small text-secondary" id="headerNotificationText">Live dashboard is ready.</div>
        <a class="dropdown-item" href="<?= htmlspecialchars(admin_url('orders', $admin_context)); ?>"><i class="bi bi-receipt me-2"></i>Open orders</a>
      </div>
    </div>
    <?php
    $profEmp = is_array($admin_context['employee'] ?? null) ? $admin_context['employee'] : [];
    $profSuper = !empty($admin_context['is_super_admin']) || !empty($profEmp['is_superadmin']);
    if ($profSuper) { $profRole = 'Super Admin'; }
    elseif (!empty($profEmp['is_owner'])) { $profRole = 'Owner'; }
    elseif (!empty($profEmp['is_manager'])) { $profRole = 'Manager'; }
    else { $profRole = 'Employee'; }

    require_once __DIR__ . '/../../../api/Controllers/helpers.php';
    $profDefs = controllersHelper::permissionRoleDefinitions();
    if (!empty($profEmp['is_owner'])) { $profPerms = null; }
    elseif ($profSuper) { $profPerms = $profDefs['is_superadmin'] ?? []; }
    elseif (!empty($profEmp['is_manager'])) { $profPerms = $profDefs['is_manager'] ?? []; }
    else { $profPerms = $profDefs['is_employee'] ?? []; }

    $profName = trim((string) ($profEmp['name'] ?? ''));
    $profInitials = $profName !== '' ? strtoupper(mb_substr($profName, 0, 1)) . (count(explode(' ', $profName)) > 1 ? strtoupper(mb_substr((explode(' ', $profName)[1] ?? ''), 0, 1)) : '') : '?';
    $profPfp = (string) ($profEmp['pfp'] ?? '');
    ?>
    <div class="dropdown profile-dropdown">
      <button class="btn btn-icon profile-avatar-trigger" type="button" id="profileMenuBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Profile">
        <span class="avatar-sm profile-avatar"><?= $profPfp !== '' ? '<img src="' . htmlspecialchars($profPfp, ENT_QUOTES) . '" alt="' . htmlspecialchars($profName, ENT_QUOTES) . '">' : htmlspecialchars($profInitials, ENT_QUOTES); ?></span>
      </button>
      <div class="dropdown-menu dropdown-menu-end profile-dropdown-menu" aria-labelledby="profileMenuBtn">
        <div class="dropdown-header profile-header">
          <div class="fw-semibold text-truncate"><?= htmlspecialchars($profName !== '' ? $profName : 'Admin'); ?></div>
          <span class="badge profile-role-badge bg-primary-subtle text-primary border border-primary-subtle"><?= htmlspecialchars($profRole); ?></span>
        </div>
        <div class="dropdown-divider"></div>
        <div class="profile-permissions">
          <?php if ($profPerms === null): ?>
            <div class="px-3 py-2 small text-secondary">
              <span class="badge bg-success-subtle text-success border border-success-subtle">Full access</span>
              <span class="d-block mt-1">Owner has no specific permission list; can edit everything in his own restaurant / brand.</span>
            </div>
          <?php else: ?>
            <div class="px-3 pt-2 small fw-semibold text-secondary text-uppercase">Permissions (<?= htmlspecialchars($profRole); ?>)</div>
            <?php foreach ($profPerms as $profKey => $profLabel): ?>
              <?php $profAllowed = admin_can($admin_context, $profKey); ?>
              <div class="profile-perm-row">
                <span class="profile-perm-label text-truncate" title="<?= htmlspecialchars($profLabel, ENT_QUOTES); ?>"><?= htmlspecialchars($profLabel, ENT_QUOTES); ?></span>
                <span class="badge profile-perm-badge <?= $profAllowed ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>"><?= $profAllowed ? 'Allowed' : 'Not allowed'; ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
