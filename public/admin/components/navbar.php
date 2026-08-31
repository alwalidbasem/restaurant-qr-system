<?php
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
    <div class="avatar-sm">AK</div>
  </div>
</div>
