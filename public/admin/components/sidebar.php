<?php
include(__DIR__ . '/../../../config/variables.php');

$active_page = $active_page ?? 'dashboard';
$admin_nav = [
    'dashboard' => ['icon' => 'bi-grid-1x2-fill',     'label' => 'Dashboard'],
    'orders'    => ['icon' => 'bi-receipt',           'label' => 'Orders'],
    'menu'      => ['icon' => 'bi-egg-fried',         'label' => 'Menu Items'],
    'tables'    => ['icon' => 'bi-table',             'label' => 'Tables'],
    'staff'     => ['icon' => 'bi-people-fill',       'label' => 'Staff'],
    'reservations' => ['icon' => 'bi-calendar-check', 'label' => 'Reservations'],
];
?>
<div class="sidebar" id="sidebar">
  <a class="brand" href="/?page=dashboard"><i class="bi bi-egg-fried"></i> Savora <span class="fw-light">Admin</span></a>

  <ul class="nav flex-column mt-2">
    <?php foreach ($admin_nav as $key => $item): ?>
      <?php $is_active = ($active_page === $key) ? ' active' : ''; ?>
      <li class="nav-item">
        <a class="nav-link<?= $is_active; ?>" href="?page=<?= $key; ?>">
          <i class="bi <?= $item['icon']; ?>"></i> <?= $item['label']; ?>
        </a>
      </li>
    <?php endforeach; ?>

    <li class="nav-item mt-3"><a class="nav-link" href="?page=settings"><i class="bi bi-gear-fill"></i> Settings</a></li>
    <li class="nav-item"><a class="nav-link" href="?page=logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
  </ul>

  <div class="sidebar-footer">
    &copy; 2026 Savora Restaurant
  </div>
</div>

<div class="overlay" id="overlay"></div>
