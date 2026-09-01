<?php
/** @var array $admin_context Injected by public/admin/view.php before include. */
// Dashboard page (body only). Rendered inside the admin layout template.
$branch_dashboard_only = !empty($admin_context['is_branch_brand_context']);
?>

<div class="dashboard-hero mb-3">
  <div>
    <div class="dashboard-kicker"><?= $branch_dashboard_only ? 'Branches overview' : 'Live overview'; ?></div>
    <h1><?= $branch_dashboard_only ? 'Brand Dashboard' : 'Restaurant Dashboard'; ?></h1>
    <p><?= $branch_dashboard_only ? 'Branch profitability, salaries, and inventory losses in one workspace.' : 'Revenue, orders, tables, menu movement, and staff activity in one workspace.'; ?></p>
  </div>
  <a class="btn btn-primary" href="<?= $branch_dashboard_only ? '?page=restaurants' : '?page=orders'; ?>"><i class="bi <?= $branch_dashboard_only ? 'bi-buildings' : 'bi-lightning-charge'; ?>"></i> <?= $branch_dashboard_only ? 'Open Branches' : 'Open Live Orders'; ?></a>
</div>

<div class="row g-3 mb-3 dashboard-stats<?= $branch_dashboard_only ? ' d-none' : ''; ?>">
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-revenue">
      <div class="stat-card-top">
        <span class="stat-icon"><i class="bi bi-cash-coin"></i></span>
        <span class="stat-chip">Today</span>
      </div>
      <div>
        <div class="stat-label">Today's Revenue</div>
        <div class="stat-value" id="statRevenue">0.00</div>
        <small class="text-secondary" id="statRevenueMeta">Live from orders</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-orders">
      <div class="stat-card-top">
        <span class="stat-icon"><i class="bi bi-receipt-cutoff"></i></span>
        <span class="stat-chip">Live</span>
      </div>
      <div>
        <div class="stat-label">Orders Today</div>
        <div class="stat-value" id="statOrders">0</div>
        <small class="text-secondary" id="statOrdersMeta">Orders loaded</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-tables">
      <div class="stat-card-top">
        <span class="stat-icon"><i class="bi bi-table"></i></span>
        <span class="stat-chip">Floor</span>
      </div>
      <div>
        <div class="stat-label">Tables Occupied</div>
        <div class="stat-value" id="statTables">0 / 0</div>
        <small class="text-secondary" id="statTablesMeta">Table usage</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card stat-card-staff">
      <div class="stat-card-top">
        <span class="stat-icon"><i class="bi bi-people-fill"></i></span>
        <span class="stat-chip">Team</span>
      </div>
      <div>
        <div class="stat-label">Staff</div>
        <div class="stat-value" id="statStaff">0</div>
        <small class="text-secondary">Active records</small>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3<?= $branch_dashboard_only ? ' d-none' : ''; ?>">
  <div class="col-lg-8">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <div class="chart-title">Weekly Revenue</div>
          <small class="text-secondary">Revenue distributed by day</small>
        </div>
        <span class="dashboard-live-pill"><i class="bi bi-broadcast"></i> Live</span>
      </div>
      <div class="card-body">
        <div class="chart-shell chart-shell-tall">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header">
        <div class="chart-title">Popular Categories</div>
        <small class="text-secondary">Items sold by category</small>
      </div>
      <div class="card-body">
        <div class="chart-shell">
          <canvas id="categoryChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3<?= $branch_dashboard_only ? ' d-none' : ''; ?>">
  <div class="col-lg-6">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header">
        <div class="chart-title">Profit Without Salary</div>
        <small class="text-secondary">Order profit before staff salary cost</small>
      </div>
      <div class="card-body">
        <div class="chart-shell chart-shell-small">
          <canvas id="profitOnlyChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header">
        <div class="chart-title">Profit With Salary</div>
        <small class="text-secondary">Order profit after estimated daily salary cost</small>
      </div>
      <div class="card-body">
        <div class="chart-shell chart-shell-small">
          <canvas id="profitSalaryChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3 d-none" id="branchesDashboardSection">
  <div class="col-12">
    <div class="card dashboard-table-card">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <div class="chart-title">Branches Dashboard</div>
          <small class="text-secondary">Profitability and inventory losses across branches</small>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="?page=restaurants"><i class="bi bi-buildings"></i> Manage Branches</a>
      </div>
      <div class="card-body">
        <div class="row g-3 mb-3" id="branchHighlights"></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Branch</th>
                <th>Location</th>
                <th>Profit</th>
                <th>Salary</th>
                <th>Profit After Salary</th>
                <th>Inventory Loss</th>
              </tr>
            </thead>
            <tbody id="branchesDashboardBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3<?= $branch_dashboard_only ? ' d-none' : ''; ?>">
  <div class="col-lg-4">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header">
        <div class="chart-title">Orders by Hour</div>
        <small class="text-secondary">Kitchen demand pattern</small>
      </div>
      <div class="card-body">
        <div class="chart-shell chart-shell-small">
          <canvas id="ordersHourChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header">
        <div class="chart-title">Top Selling Dishes</div>
        <small class="text-secondary">Best moving menu items</small>
      </div>
      <div class="card-body">
        <div class="chart-shell chart-shell-small">
          <canvas id="topDishesChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card dashboard-chart-card h-100">
      <div class="card-header">
        <div class="chart-title">Order Status</div>
        <small class="text-secondary">Current order lifecycle</small>
      </div>
      <div class="card-body">
        <div class="chart-shell chart-shell-small">
          <canvas id="statusChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tables status -->
<div class="card dashboard-table-card<?= $branch_dashboard_only ? ' d-none' : ''; ?>">
  <div class="card-header">Table Status</div>
  <div class="card-body">
    <div class="row g-2" id="tablesGrid"></div>
  </div>
</div>
