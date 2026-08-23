<?php
// Dashboard page (body only). Rendered inside the admin layout template.
?>

<!-- Stat cards -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon" style="background:#b8541b;"><i class="bi bi-cash-coin"></i></div>
      <div>
        <div class="text-secondary small">Today's Revenue</div>
        <div class="fs-4 fw-bold">$4,286</div>
        <small class="text-success"><i class="bi bi-arrow-up"></i> 12.4%</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon" style="background:#2f9e44;"><i class="bi bi-receipt-cutoff"></i></div>
      <div>
        <div class="text-secondary small">Orders Today</div>
        <div class="fs-4 fw-bold">186</div>
        <small class="text-success"><i class="bi bi-arrow-up"></i> 5.1%</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon" style="background:#1c7ed6;"><i class="bi bi-table"></i></div>
      <div>
        <div class="text-secondary small">Tables Occupied</div>
        <div class="fs-4 fw-bold">18 / 24</div>
        <small class="text-secondary">75% occupancy</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card d-flex align-items-center gap-3">
      <div class="stat-icon" style="background:#e03131;"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="text-secondary small">New Customers</div>
        <div class="fs-4 fw-bold">32</div>
        <small class="text-danger"><i class="bi bi-arrow-down"></i> 2.3%</small>
      </div>
    </div>
  </div>
</div>

<!-- Charts -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Weekly Revenue</span>
        <select class="form-select form-select-sm w-auto">
          <option>This Week</option>
          <option>Last Week</option>
          <option>This Month</option>
        </select>
      </div>
      <div class="card-body">
        <canvas id="revenueChart" height="110"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Popular Categories</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="categoryChart" height="230"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Orders by Hour</div>
      <div class="card-body">
        <canvas id="ordersHourChart" height="180"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Top Selling Dishes</div>
      <div class="card-body">
        <canvas id="topDishesChart" height="180"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">Order Status</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="statusChart" height="180"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Orders Table -->
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>Recent Orders</span>
    <div class="d-flex gap-2">
      <input type="text" class="form-control form-control-sm table-search" placeholder="Search order / customer...">
      <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Order</button>
    </div>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Table</th>
            <th>Items</th>
            <th>Total</th>
            <th>Status</th>
            <th>Time</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody id="ordersTableBody">
          <!-- rows injected by JS -->
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
    <small class="text-secondary">Showing 8 of 186 orders</small>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item disabled"><a class="page-link" href="#">Prev</a></li>
        <li class="page-item active"><a class="page-link" href="#">1</a></li>
        <li class="page-item"><a class="page-link" href="#">2</a></li>
        <li class="page-item"><a class="page-link" href="#">3</a></li>
        <li class="page-item"><a class="page-link" href="#">Next</a></li>
      </ul>
    </nav>
  </div>
</div>

<!-- Menu + Staff row -->
<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Menu Items</span>
        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Item</button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Item</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="menuTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">Staff on Duty</div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush" id="staffList"></ul>
      </div>
    </div>
  </div>
</div>

<!-- Tables status -->
<div class="card">
  <div class="card-header">Table Status</div>
  <div class="card-body">
    <div class="row g-2" id="tablesGrid"></div>
  </div>
</div>
