<?php
/** @var array $admin_context Injected by public/admin/view.php before include. */
$restaurantId = (int) ($admin_context['active_restaurant_id'] ?? 0);
$canCreateDiscounts = admin_can($admin_context, 'discounts.create');
$canUpdateDiscounts = admin_can($admin_context, 'discounts.update');
$canDeleteDiscounts = admin_can($admin_context, 'discounts.delete');
?>
<script>
  window.DISCOUNTS_PAGE = <?= json_encode([
      'restaurant_id' => $restaurantId,
      'can_create' => $canCreateDiscounts,
      'can_update' => $canUpdateDiscounts,
      'can_delete' => $canDeleteDiscounts,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="row g-3">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span>Discounts</span>
        <div class="d-flex align-items-center gap-2">
          <input class="form-control form-control-sm table-search" id="discountSearch" placeholder="Search discounts...">
          <?php if ($canCreateDiscounts): ?>
            <button class="btn btn-primary btn-sm" type="button" id="discountAddBtn" data-bs-toggle="modal" data-bs-target="#discountModal">
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
                <th>Discount</th>
                <th>Value</th>
                <th>Target</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="discountTableBody">
              <tr><td colspan="5" class="text-center text-secondary py-4">Loading discounts...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($canCreateDiscounts || $canUpdateDiscounts): ?>
<div class="modal fade" id="discountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content admin-form-modal" id="discountForm" autocomplete="off">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6" id="discountModalTitle">Add Discount</h1>
          <div class="modal-subtitle">Apply offers to menu items, categories, addons, or the full menu.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="discountFormAlert"></div>
        <input type="hidden" id="discountId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="discountName">Name</label>
            <input class="form-control" id="discountName" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="discountType">Type</label>
            <select class="form-select" id="discountType">
              <option value="percentage">Percentage</option>
              <option value="fixed">Fixed Amount</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="discountValue">Value</label>
            <input class="form-control" id="discountValue" type="number" min="0.001" step="0.001" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="discountTargetType">Apply To</label>
            <select class="form-select" id="discountTargetType">
              <option value="food">Food</option>
              <option value="category">Category</option>
              <option value="addon">Food Addon</option>
              <option value="full_menu_with_addons">Full menu (Addons included)</option>
              <option value="full_menu_without_addons">Full menu (Without Addons)</option>
            </select>
          </div>
          <div class="col-md-6" id="discountTargetGroup">
            <label class="form-label" for="discountTargetId">Target</label>
            <select class="form-select" id="discountTargetId"></select>
          </div>
          <div class="col-12">
            <div class="modal-switch-row">
              <label class="form-check-label" for="discountActive">Active</label>
              <input class="form-check-input modal-switch-control" type="checkbox" role="switch" id="discountActive" checked>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-save"></i> Save Discount
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
