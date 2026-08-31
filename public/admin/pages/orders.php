<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span>Orders</span>
    <small class="text-secondary" id="ordersPageShowing">Loading orders...</small>
  </div>
  <div class="card-body">
    <form class="row g-2 align-items-end" id="ordersFilterForm">
      <div class="col-12 col-md-2">
        <label class="form-label" for="ordersRestaurantId">Restaurant ID</label>
        <input class="form-control" id="ordersRestaurantId" type="number" min="1" placeholder="Current">
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label" for="ordersOrderId">Order ID</label>
        <input class="form-control" id="ordersOrderId" type="number" min="1" placeholder="Any">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="ordersSessionKey">Session order key</label>
        <input class="form-control" id="ordersSessionKey" type="text" placeholder="Any">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label" for="ordersCategoryDropdown">Food category</label>
        <div class="dropdown w-100">
          <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start" id="ordersCategoryDropdown" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            All categories
          </button>
          <div class="dropdown-menu w-100 p-2 orders-category-menu" id="ordersCategoryFilter" aria-labelledby="ordersCategoryDropdown">
            <div class="text-secondary small px-2 py-1">Loading categories...</div>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label" for="ordersStatusFilter">Status</label>
        <select class="form-select" id="ordersStatusFilter">
          <option value="">All status</option>
          <option value="waiting">Waiting</option>
          <option value="finished">Finished</option>
          <option value="canceled">Canceled</option>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label" for="ordersSortFilter">Sort</label>
        <select class="form-select" id="ordersSortFilter">
          <option value="newest">Newest to oldest</option>
          <option value="oldest">Oldest to newest</option>
        </select>
      </div>
      <div class="col-12 col-md-2 d-grid">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 orders-workspace">
  <div class="col-12 col-xl-7">
    <div class="card h-100">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 orders-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Table</th>
              <th>Foods</th>
              <th>Total</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="ordersPageTableBody">
            <tr><td colspan="6" class="text-center text-secondary py-4">Loading orders...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-5">
    <div class="card h-100 order-detail-card">
      <div class="card-header d-flex align-items-center justify-content-between gap-2">
        <div>
          <span id="orderDetailTitle">Order details</span>
          <small class="d-block text-secondary" id="orderDetailMeta">Select an order</small>
        </div>
        <button class="btn btn-sm btn-outline-secondary d-none" id="ordersShowAllFoodsBtn" type="button">
          <i class="bi bi-list-ul"></i> All foods
        </button>
      </div>
      <div class="card-body" id="orderDetailBody">
        <div class="text-center text-secondary py-5">Select an order to show its foods.</div>
      </div>
    </div>
  </div>
</div>
