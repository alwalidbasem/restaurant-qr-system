<?php
$page_title    = $page_title    ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? "Welcome back, here's what's happening today";
?>


<div class="topbar d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div class="d-flex align-items-center gap-3">
    <button class="btn btn-outline-secondary toggle-btn" id="toggleSidebar" type="button" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
    <div>
      <div class="section-title mb-0"><?= htmlspecialchars($page_title); ?></div>
      <small class="text-secondary"><?= htmlspecialchars($page_subtitle); ?></small>
    </div>
  </div>
  <div class="d-flex align-items-center gap-3">
    <div class="input-group" style="width:230px;">
      <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
      <input type="text" class="form-control border-start-0" placeholder="Search...">
    </div>
    <i class="bi bi-bell fs-5 text-secondary position-relative">
      <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.5rem;">•</span>
    </i>
    <div class="avatar-sm">AK</div>
  </div>
</div>