<?php
include(__DIR__ . '/../../../config/variables.php');

$active_page = $active_page ?? 'dashboard';
$admin_context = $admin_context ?? [];
$admin_nav = [];
$activeRestaurant = is_array($admin_context['active_restaurant'] ?? null)
    ? $admin_context['active_restaurant']
    : [];
$isBranchBrandSidebar = !empty($admin_context['is_branch_brand_context'])
    || (
        empty($activeRestaurant['parent_restaurant_id'])
        && (int) ($activeRestaurant['branch_management_enabled'] ?? 0) === 1
    );

if (!empty($admin_context['is_super_admin'])) {
    $admin_nav['restaurants'] = ['icon' => 'bi-buildings-fill', 'label' => 'Restaurants', 'permission' => 'restaurants.get'];
}

if (empty($admin_context['is_super_admin']) || !empty($admin_context['selected_restaurant_id']) || $isBranchBrandSidebar) {
    $admin_nav += [
        'dashboard' => ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard'],
        'orders' => ['icon' => 'bi-receipt', 'label' => 'Orders', 'permission' => 'orders.get'],
        'cashier' => [
            'icon' => 'bi-cash-register',
            'label' => 'Cashier',
            'url' => admin_url('tables', $admin_context),
            'children' => [
                ['key' => 'cashier_tables', 'label' => 'Tables', 'icon' => 'bi-table', 'permission' => 'tables.get', 'url' => admin_url('tables', $admin_context)],
                ['key' => 'cashier_takeaway', 'label' => 'Takeaway', 'icon' => 'bi-bag-check', 'permission' => 'orders.get', 'url' => admin_url('takeaway', $admin_context)],
            ],
        ],
        'menu' => [
            'icon' => 'bi-egg-fried',
            'label' => 'Menu Items',
            'children' => [
                ['key' => 'menu_foods', 'label' => 'Foods', 'icon' => 'bi-cup-hot', 'permission' => 'foods.get', 'url' => admin_url('menu', $admin_context, ['menu_section' => 'foods'])],
                ['key' => 'menu_addons', 'label' => 'Addons', 'icon' => 'bi-plus-square-dotted', 'permission' => 'foods.get', 'url' => admin_url('menu', $admin_context, ['menu_section' => 'addons'])],
                ['key' => 'menu_categories', 'label' => 'Categories', 'icon' => 'bi-tags', 'permission' => 'categories.get', 'url' => admin_url('menu', $admin_context, ['menu_section' => 'categories'])],
            ],
        ],
        'inventory' => ['icon' => 'bi-box-seam', 'label' => 'Inventory', 'permission' => 'inventory.get'],
        'discounts' => ['icon' => 'bi-percent', 'label' => 'Discounts', 'permission' => 'discounts.get'],
        'invoices' => ['icon' => 'bi-file-earmark-text', 'label' => 'Invoices', 'permission' => 'restaurant.update'],
        'staff' => ['icon' => 'bi-people-fill', 'label' => 'Staff', 'permission' => 'employees.get'],
        'log' => ['icon' => 'bi-chat-left-text', 'label' => 'Activity Log', 'permission' => 'logs.get'],
    ];
}

if ($isBranchBrandSidebar) {
    $admin_nav = !empty($admin_context['is_super_admin'])
        ? [
            'restaurants_index' => ['icon' => 'bi-shop', 'label' => 'Restaurants', 'url' => '?page=restaurants'],
            'dashboard' => ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard'],
            'restaurants' => ['icon' => 'bi-buildings-fill', 'label' => 'Branches'],
            'managers' => ['icon' => 'bi-person-gear', 'label' => 'Managers'],
            'log' => ['icon' => 'bi-chat-left-text', 'label' => 'Logs'],
        ]
        : [
            'dashboard' => ['icon' => 'bi-grid-1x2-fill', 'label' => 'Dashboard'],
            'restaurants' => ['icon' => 'bi-buildings-fill', 'label' => 'Branches', 'permission' => 'branches.get'],
            'log' => ['icon' => 'bi-chat-left-text', 'label' => 'Managers Logs', 'permission' => 'branches_logs.get'],
        ];
}
?>
<script>
  window.ADMIN_NAV = <?= json_encode(array_values(array_map(
      static fn (string $key, array $item): array => [
          'key' => $key,
          'label' => $item['label'],
          'icon' => $item['icon'],
          'url' => $item['url'] ?? admin_url($key, $admin_context),
          'permission' => $item['permission'] ?? null,
          'children' => array_values(array_filter(
              $item['children'] ?? [],
              static fn (array $child): bool => !isset($child['permission']) || admin_can($admin_context, $child['permission'])
          )),
      ],
      array_keys(array_filter(
          $admin_nav,
          static fn (array $item): bool => !isset($item['permission']) || admin_can($admin_context, $item['permission'])
      )),
      array_values(array_filter(
          $admin_nav,
          static fn (array $item): bool => !isset($item['permission']) || admin_can($admin_context, $item['permission'])
      ))
  )), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<div class="sidebar" id="sidebar">
  <a class="brand" href="<?= htmlspecialchars(admin_url(!empty($admin_context['is_super_admin']) && empty($admin_context['selected_restaurant_id']) ? 'restaurants' : 'dashboard', $admin_context)); ?>"><i class="bi bi-egg-fried"></i> Savora <span class="fw-light">Admin</span></a>

  <ul class="nav flex-column mt-2">
    <?php foreach ($admin_nav as $key => $item): ?>
      <?php
        $visible_children = array_values(array_filter(
            $item['children'] ?? [],
            static fn (array $child): bool => !isset($child['permission']) || admin_can($admin_context, $child['permission'])
        ));
        if (!empty($item['children']) && empty($visible_children)) continue;
        if (empty($item['children']) && isset($item['permission']) && !admin_can($admin_context, $item['permission'])) continue;
      ?>
      <?php
        $active_child_keys = array_map(static fn (array $child): string => (string) $child['key'], $visible_children);
        $active_child_pages = array_map(
            static fn (array $child): string => strtolower((string) parse_url($child['url'] ?? '', PHP_URL_QUERY)),
            $visible_children
        );
        $is_dropdown_active = !empty($visible_children) && (
            $active_page === $key
            || in_array($active_page, array_map(static fn (array $child): string => str_replace(['cashier_', 'menu_'], '', (string) $child['key']), $visible_children), true)
            || in_array('page=' . $active_page, array_map(static fn (string $query): string => strtok($query, '&') ?: $query, $active_child_pages), true)
        );
        $is_active = ($active_page === $key || $is_dropdown_active) ? ' active' : '';
        $collapse_id = 'sidebarDropdown' . preg_replace('/[^a-zA-Z0-9]/', '', ucfirst($key));
      ?>
      <li class="nav-item">
        <?php if (!empty($item['children'])): ?>
          <?php $menu_section = $_GET['menu_section'] ?? 'foods'; ?>
          <button class="nav-link sidebar-dropdown-toggle<?= $is_active; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapse_id, ENT_QUOTES); ?>" aria-expanded="<?= $is_active ? 'true' : 'false'; ?>" aria-controls="<?= htmlspecialchars($collapse_id, ENT_QUOTES); ?>">
            <span><i class="bi <?= $item['icon']; ?>"></i> <?= $item['label']; ?></span>
            <i class="bi bi-chevron-down sidebar-dropdown-arrow"></i>
          </button>
          <div class="collapse sidebar-submenu<?= $is_active ? ' show' : ''; ?>" id="<?= htmlspecialchars($collapse_id, ENT_QUOTES); ?>">
            <?php foreach ($visible_children as $child): ?>
              <?php
                $section = str_replace('menu_', '', $child['key']);
                $child_page = str_replace('cashier_', '', $child['key']);
                $child_active = (
                    ($active_page === 'menu' && $key === 'menu' && $menu_section === $section)
                    || ($key === 'cashier' && $active_page === $child_page)
                ) ? ' active' : '';
              ?>
              <a class="nav-link sidebar-submenu-link<?= $child_active; ?>" href="<?= htmlspecialchars($child['url']); ?>">
                <i class="bi <?= $child['icon']; ?>"></i> <?= $child['label']; ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <a class="nav-link<?= $is_active; ?>" href="<?= htmlspecialchars($item['url'] ?? admin_url($key, $admin_context)); ?>">
            <i class="bi <?= $item['icon']; ?>"></i> <?= $item['label']; ?>
          </a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>

    <?php if (!$isBranchBrandSidebar && admin_can($admin_context, 'restaurant.update') && (empty($admin_context['is_super_admin']) || !empty($admin_context['selected_restaurant_id']))): ?>
      <li class="nav-item mt-3"><a class="nav-link" href="<?= htmlspecialchars(admin_url('settings', $admin_context)); ?>"><i class="bi bi-gear-fill"></i> Settings</a></li>
    <?php endif; ?>
    <li class="nav-item"><a class="nav-link" href="?page=logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
  </ul>

  <div class="sidebar-footer">
    &copy; 2026 Savora Restaurant
  </div>
</div>

<div class="overlay" id="overlay"></div>
