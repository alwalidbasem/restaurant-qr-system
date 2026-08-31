<?php
$permissions = require __DIR__ . '/../../../api/Middleware/permissions_config/definitions.php';
$canCreateStaff = admin_can($admin_context, 'employees.create');
$canUpdateStaff = admin_can($admin_context, 'employees.update');
$canDeleteStaff = admin_can($admin_context, 'employees.delete');
$isSuperAdmin = !empty($admin_context['is_super_admin']);
$isManagersPage = ($active_page ?? '') === 'managers';
$permissionGroups = [];
$visiblePermissionKeys = [];

foreach ($permissions as $key => $label) {
    [$group, $action] = explode('.', $key, 2);

    if ($group === 'restaurants' && !$isSuperAdmin) {
        continue;
    }

    $groupLabel = [
        'restaurants' => 'Restaurants',
        'restaurant' => 'Restaurant Settings',
        'employees' => 'Employees',
        'inventory' => 'Inventory',
        'orders' => 'Orders',
        'foods' => 'Foods',
        'categories' => 'Categories',
        'tables' => 'Tables',
        'logs' => 'Activity Logs',
    ][$group] ?? ucfirst($group);

    $permissionGroups[$group]['label'] = $groupLabel;
    $permissionGroups[$group]['permissions'][$action] = [
        'key' => $key,
        'label' => $label,
    ];
    $visiblePermissionKeys[] = $key;
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
                <th>Role</th>
                <th>Salary</th>
                <th>Branch</th>
                <th>Permissions</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="staffTableBody">
              <tr>
                <td colspan="7" class="text-center text-secondary py-4">Loading <?= $isManagersPage ? 'managers' : 'staff'; ?>...</td>
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
              <label class="form-label" for="staffRole">Role</label>
              <select class="form-select" id="staffRole">
                <option value="delivery_manager">Delivery Manager</option>
                <option value="cashier">Cashier</option>
                <option value="chef">Chef</option>
                <option value="inventory_manager">Inventory Manager</option>
                <option value="manager">Manager</option>
                <option value="owner">Owner</option>
              </select>
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
              <label class="form-label" for="staffDescription">Description</label>
              <textarea class="form-control" id="staffDescription" rows="2"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-section mt-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <label class="form-label mb-0">Permission Management</label>
            <button class="btn btn-outline-secondary btn-sm" type="button" id="staffSelectAllPermissions">
              <i class="bi bi-check2-square"></i> Toggle all
            </button>
          </div>
          <div class="accordion permission-accordion" id="staffPermissionAccordion">
            <?php $groupIndex = 0; ?>
            <?php foreach ($permissionGroups as $groupKey => $group): ?>
              <?php
                $readPermission = $group['permissions']['get'] ?? null;
                $accordionId = 'permGroup' . $groupIndex++;
                $readInputId = $accordionId . 'Read';
              ?>
              <div class="accordion-item permission-group" data-permission-group="<?= htmlspecialchars($groupKey, ENT_QUOTES); ?>">
                <h2 class="accordion-header permission-heading">
                  <?php if ($readPermission): ?>
                    <input class="form-check-input staff-permission permission-read" id="<?= $readInputId; ?>" type="checkbox" value="<?= htmlspecialchars($readPermission['key'], ENT_QUOTES); ?>" data-permission="<?= htmlspecialchars($readPermission['key'], ENT_QUOTES); ?>">
                  <?php endif; ?>
                  <button class="accordion-button collapsed permission-group-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $accordionId; ?>" aria-expanded="false" aria-controls="<?= $accordionId; ?>">
                    <span><?= htmlspecialchars($group['label']); ?></span>
                  </button>
                </h2>
                <div id="<?= $accordionId; ?>" class="accordion-collapse collapse" data-bs-parent="#staffPermissionAccordion">
                  <div class="accordion-body">
                    <div class="permission-actions">
                      <?php foreach (['create' => 'Create', 'update' => 'Update', 'delete' => 'Delete'] as $actionKey => $actionLabel): ?>
                        <?php if (!isset($group['permissions'][$actionKey])) continue; ?>
                        <?php $item = $group['permissions'][$actionKey]; ?>
                        <label class="permission-action">
                          <input class="form-check-input staff-permission permission-child" type="checkbox" value="<?= htmlspecialchars($item['key'], ENT_QUOTES); ?>" data-permission="<?= htmlspecialchars($item['key'], ENT_QUOTES); ?>">
                          <span><?= htmlspecialchars($actionLabel); ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
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
