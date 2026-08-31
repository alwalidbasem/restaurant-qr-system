<?php
$canCreate = admin_can($admin_context, 'restaurants.create');
$canUpdate = admin_can($admin_context, 'restaurants.update');
$canDelete = admin_can($admin_context, 'restaurants.delete');
$canCreateBranch = admin_can($admin_context, 'branches.create');
$canUpdateBranch = admin_can($admin_context, 'branches.update');
$canDeleteBranch = admin_can($admin_context, 'branches.delete');
$canAdd = $canCreate || $canCreateBranch;
?>
<script>
  window.RESTAURANT_PERMISSIONS = <?= json_encode([
    'create' => $canCreate,
    'update' => $canUpdate,
    'delete' => $canDelete,
    'branches_create' => $canCreateBranch,
    'branches_update' => $canUpdateBranch,
    'branches_delete' => $canDeleteBranch
  ], JSON_UNESCAPED_SLASHES); ?>;
</script>

<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span>Restaurants & Branches</span>
    <?php if ($canAdd): ?>
      <button class="btn btn-primary btn-sm" type="button" id="restaurantAddBtn" data-bs-toggle="modal" data-bs-target="#restaurantModal">
        <i class="bi bi-plus-lg"></i> Add
      </button>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Brand / Branch</th>
            <th>Owner / Manager</th>
            <th>Status</th>
            <th>Allowed Branches</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody id="restaurantsTableBody">
          <tr><td colspan="5" class="text-center text-secondary py-4">Loading restaurants...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="restaurantModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="restaurantForm">
      <div class="modal-header">
        <h1 class="modal-title fs-6" id="restaurantModalTitle">Add Restaurant</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="restaurantFormAlert"></div>
        <input type="hidden" id="restaurantId">
        <input type="hidden" id="restaurantParentId">
        <div class="row g-3">
          <div class="col-md-6" id="restaurantNameWrap">
            <label class="form-label" for="restaurantName" id="restaurantNameLabel">Restaurant Name</label>
            <input class="form-control" id="restaurantName" required>
          </div>
          <div class="col-md-6" id="restaurantCodeWrap">
            <label class="form-label" for="restaurantCode">Restaurant Code</label>
            <input class="form-control" id="restaurantCode" required>
          </div>
          <div class="col-12" id="restaurantLocationWrap">
            <label class="form-label" for="restaurantLocation">Location</label>
            <input class="form-control" id="restaurantLocation" required>
          </div>
          <div class="col-md-6 d-none" id="restaurantBranchLimitWrap">
            <label class="form-label" for="restaurantBranchLimit">Allowed Branches</label>
            <input class="form-control" id="restaurantBranchLimit" type="number" min="0" value="0">
          </div>
          <div class="col-12 form-check form-switch ms-2" id="restaurantBranchEnabledWrap">
            <input class="form-check-input" id="restaurantBranchEnabled" type="checkbox">
            <label class="form-check-label" for="restaurantBranchEnabled">Branch management enabled</label>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="restaurantManager" id="restaurantManagerLabel">Manager Phone</label>
            <input class="form-control" id="restaurantManager" required>
          </div>
          <div class="col-md-6" id="restaurantActiveUntilWrap">
            <label class="form-label" for="restaurantActiveUntil">Active Until</label>
            <input class="form-control" id="restaurantActiveUntil" type="date" required>
          </div>
          <div class="col-12" id="restaurantDetailsWrap">
            <label class="form-label" for="restaurantDetails">Details</label>
            <textarea class="form-control" id="restaurantDetails" rows="3" required></textarea>
          </div>
          <div class="col-12">
            <label class="form-label" for="restaurantBranchSettings">Branch Settings</label>
            <textarea class="form-control" id="restaurantBranchSettings" rows="3" placeholder='{"opening_hours":"10:00-23:00"}'></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>
