<?php
/**
 * Admin view helpers (frontend only).
 * Mirrors the URL helper functions in public/client/components/translator.php
 * but scoped to the /public/admin area.
 */

function app_base_url(): string
{
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script_dir = rtrim($script_dir, '/');

    // Strip the trailing '/public/admin' so the returned base points at the
    // application root (e.g. http://localhost/Portfolio).
    if (substr($script_dir, -13) === '/public/admin') {
        return substr($script_dir, 0, -13) ?: '';
    }

    return $script_dir;
}

function admin_asset_url(string $path): string
{
    return app_base_url() . '/public/admin/assets/' . ltrim($path, '/');
}

function admin_context(PDO $conn): array
{
    require_once __DIR__ . '/../../../api/Controllers/helpers.php';

    $employee = controllersHelper::currentEmployee($conn);
    $selectedRestaurantId = filter_input(INPUT_GET, 'restaurant_id', FILTER_VALIDATE_INT);
    if ($selectedRestaurantId === false || $selectedRestaurantId === null) {
        $selectedRestaurantId = filter_input(INPUT_GET, 'branch_id', FILTER_VALIDATE_INT);
    }
    $isSuperAdmin = $employee !== null && controllersHelper::isSuperAdminEmployee($employee);
    $permissions = controllersHelper::permissionMap($employee);
    $selectedRestaurantId = ($selectedRestaurantId !== false && $selectedRestaurantId !== null && $selectedRestaurantId > 0)
        ? (int) $selectedRestaurantId
        : null;
    $canUseSelectedRestaurant = $selectedRestaurantId !== null
        && (
            $isSuperAdmin
            || controllersHelper::employeeCanAccessRestaurant($conn, $employee, $selectedRestaurantId)
        );
    $defaultRestaurantId = !empty($employee['is_owner']) || !empty($employee['is_manager']) || !empty($employee['manager_scope'])
        ? (int) ($employee['restaurant_id'] ?? 0)
        : controllersHelper::effectiveRestaurantId($employee);
    $activeRestaurantId = $canUseSelectedRestaurant
        ? $selectedRestaurantId
        : $defaultRestaurantId;
    $activeRestaurant = admin_context_restaurant($conn, $activeRestaurantId);
    $isBranchBrandContext = $activeRestaurant !== null
        && empty($activeRestaurant['parent_restaurant_id'])
        && (int) ($activeRestaurant['branch_management_enabled'] ?? 0) === 1;
    return [
        'employee' => $employee,
        'permissions' => $permissions,
        'is_super_admin' => $isSuperAdmin,
        'selected_restaurant_id' => $canUseSelectedRestaurant ? $selectedRestaurantId : null,
        'active_restaurant_id' => $activeRestaurantId,
        'active_restaurant' => $activeRestaurant,
        'is_branch_brand_context' => $isBranchBrandContext,
    ];
}

function admin_context_restaurant(PDO $conn, int $restaurantId): ?array
{
    if ($restaurantId <= 0) {
        return null;
    }

    try {
        $columns = $conn->query("SHOW COLUMNS FROM restaurants")->fetchAll(PDO::FETCH_COLUMN);
        $branchColumns = in_array('parent_restaurant_id', $columns, true)
            ? 'parent_restaurant_id, branch_management_enabled, branch_limit'
            : 'NULL AS parent_restaurant_id, 0 AS branch_management_enabled, 0 AS branch_limit';
        $stmt = $conn->prepare("
            SELECT id, name, main_code, $branchColumns
            FROM restaurants
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $restaurantId]);
        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

        return $restaurant ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function admin_can(array $context, string $permission): bool
{
    if (!empty($context['is_super_admin'])) {
        if (in_array($permission, ['restaurants.create', 'restaurants.get', 'restaurants.update', 'restaurants.delete'], true)) {
            return !empty($context['permissions'][$permission]);
        }
        return true;
    }

    $employee = is_array($context['employee'] ?? null) ? $context['employee'] : [];
    if (!empty($employee['is_owner'])) {
        return !isset((controllersHelper::permissionRoleDefinitions()['is_superadmin'] ?? [])[$permission]);
    }

    if (!empty($employee['is_manager'])) {
        return !empty($context['permissions'][$permission]);
    }

    return !empty($context['permissions'][$permission]);
}

function admin_page_allowed(array $context, string $page): bool
{
    $activeRestaurant = is_array($context['active_restaurant'] ?? null) ? $context['active_restaurant'] : [];
    $isBranchBrandContext = !empty($context['is_branch_brand_context'])
        || (
            empty($activeRestaurant['parent_restaurant_id'])
            && (int) ($activeRestaurant['branch_management_enabled'] ?? 0) === 1
        );

    $employee = is_array($context['employee'] ?? null) ? $context['employee'] : [];
    $isOwner = !empty($employee['is_owner']);
    $brandPages = !empty($context['is_super_admin']) || $isOwner
        ? ['dashboard', 'restaurants', 'managers', 'log', 'logout']
        : ['dashboard', 'restaurants', 'log', 'logout'];
    if ($isBranchBrandContext && !in_array($page, $brandPages, true)) {
        return false;
    }

    if ($page === 'dashboard' && $isBranchBrandContext) {
        return admin_can($context, 'branches_dashboard.get');
    }
    if (
        $page === 'restaurants'
        && empty($context['is_super_admin'])
        && !empty($activeRestaurant['parent_restaurant_id'])
    ) {
        return false;
    }

    if ($page === 'menu') {
        $section = strtolower((string) ($_GET['menu_section'] ?? 'foods'));
        $required_menu = [
            'foods' => 'foods.get',
            'addons' => 'foods.get',
            'categories' => 'categories.get',
        ];

        return admin_can($context, $required_menu[$section] ?? 'foods.get');
    }

    $required = [
        'restaurants' => 'restaurants.get',
        'dashboard' => 'dashboard.get',
        'orders' => 'orders.get',
        'takeaway' => 'orders.get',
        'tables' => 'tables.get',
        'inventory' => 'inventory.get',
        'discounts' => 'discounts.get',
        'invoices' => 'restaurant.update',
        'staff' => 'staff.get',
        'managers' => 'staff.get',
        'log' => 'logs.get',
        'settings' => 'restaurant.update',
    ];

    if (!isset($required[$page])) {
        return true;
    }

    if ($page === 'restaurants') {
        if ($isBranchBrandContext) {
            return admin_can($context, 'restaurants.get') || admin_can($context, 'branches.get');
        }

        return admin_can($context, 'restaurants.get') || admin_can($context, 'restaurant.update');
    }

    if ($page === 'log' && $isBranchBrandContext) {
        return admin_can($context, 'logs.get') || admin_can($context, 'branches_logs.get');
    }

    return admin_can($context, $required[$page]);
}

function admin_default_page(array $context): string
{
    $activeRestaurant = is_array($context['active_restaurant'] ?? null) ? $context['active_restaurant'] : [];
    $isBranchBrandContext = !empty($context['is_branch_brand_context'])
        || (
            empty($activeRestaurant['parent_restaurant_id'])
            && (int) ($activeRestaurant['branch_management_enabled'] ?? 0) === 1
        );

    if (!empty($context['is_super_admin']) && empty($context['selected_restaurant_id'])) {
        return 'restaurants';
    }

    $pages = $isBranchBrandContext
        ? ['dashboard', 'restaurants', 'log']
        : ['dashboard', 'orders', 'tables', 'menu', 'inventory', 'discounts', 'invoices', 'staff', 'log', 'settings'];

    foreach ($pages as $page) {
        if (admin_page_allowed($context, $page)) {
            return $page;
        }
    }

    return 'logout';
}

function admin_url(string $page, array $context = [], array $extra = []): string
{
    $params = array_merge(['page' => $page], $extra);

    if (!empty($context['selected_restaurant_id'])) {
        $params['restaurant_id'] = (int) $context['selected_restaurant_id'];
    }

    return '?' . http_build_query($params);
}
