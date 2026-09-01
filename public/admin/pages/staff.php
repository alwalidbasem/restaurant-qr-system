<?php
/** @var array $admin_context Injected by public/admin/view.php before include. */
$permissions = controllersHelper::permissionDefinitions();
$canCreateStaff = admin_can($admin_context, 'staff.create');
$canUpdateStaff = admin_can($admin_context, 'staff.update');
$canDeleteStaff = admin_can($admin_context, 'staff.delete');
$isSuperAdmin = !empty($admin_context['is_super_admin']);
$isManagersPage = ($active_page ?? '') === 'managers';
$currentAdminEmployee = is_array($admin_context['employee'] ?? null) ? $admin_context['employee'] : [];

if ($isManagersPage && !$isSuperAdmin && empty($currentAdminEmployee['is_owner'])) {
    $canCreateStaff = false;
    $canUpdateStaff = false;
    $canDeleteStaff = false;
}

$permissionGroups = [];
$visiblePermissionKeys = [];

foreach ($permissions as $key => $label) {
    [$group, $action] = explode('.', $key, 2);

    if ($group === 'restaurants' && !$isSuperAdmin) {
        continue;
    }

    if (!$isManagersPage && in_array($group, ['branches', 'branches_dashboard', 'branches_logs', 'managers', 'managers_log'], true)) {
        continue;
    }

    $groupLabel = [
        'restaurants' => 'Restaurants',
        'restaurant' => 'Restaurant Settings',
        'staff' => 'Staff',
        'inventory' => 'Inventory',
        'orders' => 'Orders',
        'foods' => 'Foods',
        'categories' => 'Categories',
        'tables' => 'Tables',
        'logs' => 'Activity Logs',
        'auth' => 'Login Records',
        'branches' => 'Branches',
        'branches_logs' => 'Branch Manager Logs',
        'managers_log' => 'Manager Logs',
        'dashboard' => 'Dashboard',
        'branches_dashboard' => 'Branches Dashboard',
        'managers' => 'Managers Management',
    ][$group] ?? ucfirst($group);

    $permissionGroups[$group]['label'] = $groupLabel;
    $permissionGroups[$group]['permissions'][$action] = [
        'key' => $key,
        'label' => $label,
    ];
    $visiblePermissionKeys[] = $key;
}

$renderPermissionGroups = function (array $groupKeys, int &$groupIndex, string $parentId) use ($permissionGroups): string {
    $html = '';
    foreach ($groupKeys as $groupKey) {
        $group = $permissionGroups[$groupKey] ?? null;
        if (!$group) {
            continue;
        }
        $readPermission = $group['permissions']['get'] ?? null;
        $accordionId = 'permGroup' . $groupIndex++;
        $readInputId = $accordionId . 'Read';
        $html .= '<div class="accordion-item permission-group" data-permission-group="' . htmlspecialchars($groupKey, ENT_QUOTES) . '">';
        $html .= '<h2 class="accordion-header permission-heading">';
        if ($readPermission) {
            $html .= '<input class="form-check-input staff-permission permission-read" id="' . $readInputId . '" type="checkbox" value="' . htmlspecialchars($readPermission['key'], ENT_QUOTES) . '" data-permission="' . htmlspecialchars($readPermission['key'], ENT_QUOTES) . '">';
        }
        $html .= '<button class="accordion-button collapsed permission-group-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#' . $accordionId . '" aria-expanded="false" aria-controls="' . $accordionId . '">';
        $html .= '<span>' . htmlspecialchars($group['label']) . '</span>';
        $html .= '</button></h2>';
        $html .= '<div id="' . $accordionId . '" class="accordion-collapse collapse" data-bs-parent="#' . $parentId . '">';
        $html .= '<div class="accordion-body"><div class="permission-actions">';
        foreach (['create' => 'Create', 'update' => 'Update', 'delete' => 'Delete'] as $actionKey => $actionLabel) {
            if (!isset($group['permissions'][$actionKey])) {
                continue;
            }
            $item = $group['permissions'][$actionKey];
            $html .= '<label class="permission-action"><input class="form-check-input staff-permission permission-child" type="checkbox" value="' . htmlspecialchars($item['key'], ENT_QUOTES) . '" data-permission="' . htmlspecialchars($item['key'], ENT_QUOTES) . '"><span>' . htmlspecialchars($actionLabel) . '</span></label>';
        }
        $html .= '</div></div></div></div>';
    }
    return $html;
};

$excludedGroups = ['restaurants', 'dashboard', 'branches_dashboard', 'branches_logs'];
$gIndex = 0;
if ($isManagersPage) {
    $mainGroupKeys = ['branches', 'managers'];
    $subGroupKeys = array_values(array_filter(array_keys($permissionGroups), static fn ($k) => !in_array($k, array_merge($excludedGroups, ['branches']), true)));
    $mainAccordionHtml = $renderPermissionGroups($mainGroupKeys, $gIndex, 'staffPermissionAccordion');
    $subAccordionHtml = $renderPermissionGroups($subGroupKeys, $gIndex, 'staffSubPermissionAccordion');
} else {
    $mainGroupKeys = array_values(array_filter(array_keys($permissionGroups), static fn ($k) => !in_array($k, $excludedGroups, true)));
    $mainAccordionHtml = $renderPermissionGroups($mainGroupKeys, $gIndex, 'staffPermissionAccordion');
    $subAccordionHtml = '';
}
?>

<script>
  window.STAFF_PERMISSION_KEYS = <?= json_encode(array_keys($permissions), JSON_UNESCAPED_SLASHES); ?>;
  window.STAFF_VISIBLE_PERMISSION_KEYS = <?= json_encode($visiblePermissionKeys, JSON_UNESCAPED_SLASHES); ?>;
  window.STAFF_PAGE = <?= json_encode([
      'mode' => $isManagersPage ? 'managers' : 'staff',
      'title' => $isManagersPage ? 'Managers' : 'Staff',
      'singular' => $isManagersPage ? 'Manager' : 'Staff Member',
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="row g-3">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><?= $isManagersPage ? 'Managers' : 'Staff'; ?></span>
        <div class="d-flex align-items-center gap-2">
          <input class="form-control form-control-sm table-search" id="staffSearch" placeholder="Search staff...">
          <?php if ($canCreateStaff): ?>
            <button class="btn btn-primary btn-sm" type="button" id="staffAddBtn" data-bs-toggle="modal" data-bs-target="#staffModal">
              <i class="bi bi-plus-lg"></i> Add
            </button>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Details</th>
                <?php if ($isSuperAdmin): ?>
                  <th>Hidden Details</th>
                <?php endif; ?>
                <th>Salary</th>
                <th>Branch</th>
                <th>Permissions</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="staffTableBody">
              <tr>
                <td colspan="<?= $isSuperAdmin ? 8 : 7; ?>" class="text-center text-secondary py-4">Loading <?= $isManagersPage ? 'managers' : 'staff'; ?>...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($canCreateStaff || $canUpdateStaff): ?>
<div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content admin-form-modal" id="staffForm" autocomplete="off">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6" id="staffFormTitle">Add <?= $isManagersPage ? 'Manager' : 'Staff Member'; ?></h1>
          <div class="modal-subtitle">Profile details, login access, and permissions.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="staffFormAlert"></div>
        <input type="hidden" id="staffId">
        <input type="hidden" id="staffPfp">

        <div class="modal-form-grid">
          <div class="modal-media-panel">
            <div class="image-upload-preview" id="staffPfpPreview">
              <i class="bi bi-person"></i>
            </div>
            <label class="form-label" for="staffPfpFile">Profile Image</label>
            <input class="form-control" id="staffPfpFile" type="file" accept="image/*">
            <div class="form-text">Images are scanned, compressed, and saved as WEBP. Max 5MB.</div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="staffName">Name</label>
              <input class="form-control" id="staffName" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="staffUsername">Username</label>
              <input class="form-control" id="staffUsername" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="staffPassword">Password</label>
              <input class="form-control" id="staffPassword" type="password" minlength="8">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="staffSalary">Monthly Salary</label>
              <input class="form-control" id="staffSalary" type="number" min="0" step="0.01" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="staffRestaurantId">Restaurant ID</label>
              <input class="form-control" id="staffRestaurantId" type="number" min="1" required readonly>
            </div>
            <div class="col-md-6 d-none" id="staffBranchWrap">
              <label class="form-label" for="staffBranchId">Branch</label>
              <select class="form-select" id="staffBranchId">
                <option value="">Select branch</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="staffDetails">Details</label>
              <textarea class="form-control" id="staffDetails" rows="2"></textarea>
            </div>
            <?php if ($isSuperAdmin): ?>
              <div class="col-12">
                <label class="form-label" for="staffHiddenDetails">Hidden Details</label>
                <textarea class="form-control" id="staffHiddenDetails" rows="2"></textarea>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="modal-section mt-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label mb-0">Permission Management</label>
            <button class="btn btn-outline-secondary btn-sm" type="button" id="staffSelectAllPermissions">
              <i class="bi bi-check2-square"></i> Toggle all
            </button>
          </div>
          <div class="general-permissions mb-3">
            <?php foreach (['dashboard' => 'Dashboard', 'branches_dashboard' => 'Branches Dashboard', 'branches_logs' => 'Branches Logs'] as $flatGroup => $flatLabel): ?>
              <?php if (empty($permissionGroups[$flatGroup]['permissions']['get'])) continue; ?>
              <?php $flatItem = $permissionGroups[$flatGroup]['permissions']['get']; ?>
              <label class="permission-action">
                <input class="form-check-input staff-permission" type="checkbox" value="<?= htmlspecialchars($flatItem['key'], ENT_QUOTES); ?>" data-permission="<?= htmlspecialchars($flatItem['key'], ENT_QUOTES); ?>">
                <span><?= htmlspecialchars($flatLabel); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="accordion permission-accordion" id="staffPermissionAccordion">
            <?= $mainAccordionHtml; ?>
          </div>
        </div>

        <?php if ($isManagersPage): ?>
        <div class="modal-section mt-4">
          <label class="form-label mb-2">Branches Permissions</label>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="managerScope" id="managerScopeAll" value="all" checked>
            <label class="form-check-label" for="managerScopeAll">Manager for all branches</label>
          </div>
          <div class="alert alert-warning mt-2 mb-2 py-2 d-none" id="managerScopeWarning">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> This manager can now edit everything in any restaurant.
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="managerScope" id="managerScopeSome" value="some">
            <label class="form-check-label" for="managerScopeSome">Manager for some branches</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="managerScope" id="managerScopeNone" value="none">
            <label class="form-check-label" for="managerScopeNone">Cannot edit any branch</label>
          </div>
          <div class="text-secondary small mt-1">He can only access the Brand panel and cannot access any restaurant panel.</div>
          <div class="mt-2 d-none" id="managerBranchWrap">
            <div class="accordion" id="managerBranchAccordion">
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#managerBranchCollapse" aria-expanded="false" aria-controls="managerBranchCollapse">
                    <span>Select Branches</span>
                  </button>
                </h2>
                <div id="managerBranchCollapse" class="accordion-collapse collapse" data-bs-parent="#managerBranchAccordion">
                  <div class="accordion-body">
                    <div id="managerBranchList" class="manager-branch-list"></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-3 d-none" id="managerSubPermissions">
              <label class="form-label mb-2">Restaurant Permissions</label>
              <div class="accordion permission-accordion" id="staffSubPermissionAccordion">
                <?= $subAccordionHtml; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit" id="staffSubmit">
          <i class="bi bi-save"></i> Save <?= $isManagersPage ? 'Manager' : 'Staff Member'; ?>
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
