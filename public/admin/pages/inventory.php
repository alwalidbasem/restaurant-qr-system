<?php
$canCreateInventory = admin_can($admin_context, 'inventory.create');
$canUpdateInventory = admin_can($admin_context, 'inventory.update');
$canDeleteInventory = admin_can($admin_context, 'inventory.delete');
?>
<script>
  window.INVENTORY_PAGE = <?= json_encode([
      'can_create' => $canCreateInventory,
      'can_update' => $canUpdateInventory,
      'can_delete' => $canDeleteInventory,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <span>Inventory</span>
      <small class="d-block text-secondary" id="inventoryShowing">Loading stock...</small>
    </div>
    <?php if ($canCreateInventory): ?>
      <button class="btn btn-primary btn-sm" id="inventoryAddBtn" type="button">
        <i class="bi bi-plus-lg"></i> Add item
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="stat-card">
      <div class="text-secondary small">Stock items</div>
      <div class="fs-4 fw-bold" id="inventoryStatItems">0</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="text-secondary small">Low stock</div>
      <div class="fs-4 fw-bold" id="inventoryStatLow">0</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="text-secondary small">Waste movements</div>
      <div class="fs-4 fw-bold" id="inventoryStatWaste">0</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">Stock by Item</div>
      <div class="card-body">
        <canvas id="inventoryStockChart" height="160"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">Movement Types</div>
      <div class="card-body">
        <canvas id="inventoryMovementChart" height="160"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card h-100">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Item</th>
              <th>Qty</th>
              <th>Linked foods</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="inventoryTableBody">
            <tr><td colspan="4" class="text-center text-secondary py-4">Loading inventory...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card h-100">
      <div class="card-header">Recent Movements</div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush" id="inventoryMovementsList"></ul>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="inventoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" id="inventoryForm">
      <div class="modal-header">
        <h1 class="modal-title fs-6" id="inventoryFormTitle">Add Inventory Item</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="inventoryId">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="inventoryName">Name</label>
            <input class="form-control" id="inventoryName" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="inventoryUnit">Type</label>
            <select class="form-select" id="inventoryUnit">
              <option value="pcs">PCS</option>
              <option value="kgs">KGS</option>
              <option value="liters">Liters</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="inventoryQuantity">How many?</label>
            <input class="form-control" id="inventoryQuantity" type="number" min="0" step="0.001" required>
          </div>
          <div class="col-md-2">
            <label class="form-label" for="inventoryRestaurantId">Restaurant</label>
            <input class="form-control" id="inventoryRestaurantId" type="number" min="1" required>
          </div>
        </div>
        <hr>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="fw-bold">Linked with foods</div>
          <button class="btn btn-sm btn-outline-primary" id="inventoryAddLinkBtn" type="button">
            <i class="bi bi-plus-lg"></i> Link
          </button>
        </div>
        <div id="inventoryLinks"></div>
        <div class="alert alert-danger d-none mt-3 mb-0" id="inventoryFormAlert"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="inventoryMovementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="inventoryMovementForm">
      <div class="modal-header">
        <h1 class="modal-title fs-6" id="inventoryMovementTitle">Inventory Movement</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="movementInventoryId">
        <label class="form-label" for="movementType">Type</label>
        <select class="form-select mb-3" id="movementType">
          <option value="purchase">Add stock</option>
          <option value="waste">Waste</option>
          <option value="adjustment">Decrease stock</option>
        </select>
        <label class="form-label" for="movementQuantity">Quantity</label>
        <input class="form-control mb-3" id="movementQuantity" type="number" min="0.001" step="0.001" required>
        <label class="form-label" for="movementReason">Reason</label>
        <input class="form-control" id="movementReason" placeholder="Required for waste">
        <div class="alert alert-danger d-none mt-3 mb-0" id="inventoryMovementAlert"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save movement</button>
      </div>
    </form>
  </div>
</div>
