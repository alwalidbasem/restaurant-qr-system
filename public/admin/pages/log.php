<?php
$restaurantId = (int) ($admin_context['active_restaurant_id'] ?? 0);
$permissions = require __DIR__ . '/../../../api/Middleware/permissions_config/definitions.php';
$brandLogMode = !empty($admin_context['is_branch_brand_context']);
?>
<script>
  window.LOG_PAGE = <?= json_encode([
      'restaurant_id' => $restaurantId,
      'permissions' => $permissions,
      'brand_mode' => $brandLogMode,
      'default_permissions' => $brandLogMode ? [
          'auth.login',
          'auth.logout',
          'branches.create',
          'branches.update',
          'branches.delete',
          'employees.create',
          'employees.update',
          'employees.delete',
      ] : [],
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="card log-card">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <span>Activity Log</span>
      <small class="d-block text-secondary"><?= $brandLogMode ? 'Manager changes, branch edits, and login records' : 'Live restaurant edits and staff actions'; ?></small>
    </div>
    <span class="dashboard-live-pill"><i class="bi bi-broadcast"></i> Live</span>
  </div>
  <div class="card-body border-bottom">
    <div class="row g-2 align-items-end">
      <div class="col-lg-4">
        <label class="form-label" for="logPermissionDropdown">Permission filter</label>
        <div class="dropdown w-100">
          <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start log-filter-toggle" id="logPermissionDropdown" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            All permissions
          </button>
          <div class="dropdown-menu w-100 p-2 log-filter-menu" id="logPermissionFilter" aria-labelledby="logPermissionDropdown">
            <div class="text-secondary small px-2 py-1">Loading permissions...</div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <label class="form-label" for="logStaffDropdown">Staff filter</label>
        <div class="dropdown w-100">
          <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start log-filter-toggle" id="logStaffDropdown" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            All staff
          </button>
          <div class="dropdown-menu w-100 p-2 log-filter-menu" id="logStaffFilter" aria-labelledby="logStaffDropdown">
            <div class="text-secondary small px-2 py-1">Loading staff...</div>
          </div>
        </div>
      </div>
      <div class="col-lg-3">
        <label class="form-label" for="logRangeFilter">Time range</label>
        <select class="form-select" id="logRangeFilter">
          <option value="1h">Last hour</option>
          <option value="24h" selected>Last 24 hours</option>
          <option value="week">Last week</option>
          <option value="month">Last month</option>
          <option value="3months">Last 3 months</option>
        </select>
      </div>
      <div class="col-lg-1">
        <button class="btn btn-primary w-100" type="button" id="logApplyFilters"><i class="bi bi-funnel"></i></button>
      </div>
    </div>
  </div>
  <div class="log-stream" id="logStream">
    <button class="btn btn-outline-secondary btn-sm log-load-more" type="button" id="logLoadMore">Load previous 25</button>
    <div id="logMessages"></div>
  </div>
</div>

<div class="modal fade" id="logInfoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content log-info-modal">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6" id="logInfoTitle">Log Information</h1>
          <div class="modal-subtitle" id="logInfoSubtitle"></div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="logInfoBody"></div>
    </div>
  </div>
</div>
