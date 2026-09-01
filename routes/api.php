<?php
require_once __DIR__ . '/../config/variables.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/Controllers/helpers.php';
require_once __DIR__ . '/../api/Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/../api/Controllers/Tables/TablesController.php';
require_once __DIR__ . '/../api/Controllers/Food/FoodController.php';
require_once __DIR__ . '/../api/Controllers/Categories/CategoriesController.php';
require_once __DIR__ . '/../api/Controllers/Restaurant/RestaurantController.php';
require_once __DIR__ . '/../api/Controllers/Staff/StaffController.php';
require_once __DIR__ . '/../api/Controllers/Auth/AuthController.php';
require_once __DIR__ . '/../api/Controllers/Inventory/InventoryController.php';
require_once __DIR__ . '/../api/Controllers/RestaurantTaxSettings/RestaurantTaxSettingsController.php';
require_once __DIR__ . '/../api/Controllers/Invoices/InvoicesController.php';
require_once __DIR__ . '/../api/Controllers/FoodAddons/FoodAddonsController.php';
require_once __DIR__ . '/../api/Controllers/Discounts/DiscountsController.php';
require_once __DIR__ . '/../api/Controllers/Orders/OrdersController.php';
require_once __DIR__ . '/../api/Controllers/ActivityLogs/ActivityLogsController.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$route = $_GET['route'] ?? ($uri ?? parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$route = trim((string) $route, '/');
$segments = explode('/', $route);
if (($segments[0] ?? null) !== 'api') {
    array_unshift($segments, 'api');
}
$resource = $segments[1] ?? null;
$id = isset($segments[2]) ? (int) $segments[2] : null;
$action = $segments[3] ?? null;
$PermissionsMiddleware = new PermissionsMiddleware($conn);


if ($resource === 'uploads' && $method === 'POST') {
    $uploadType = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_POST['type'] ?? 'general')) ?: 'general';
    $allowedPermissions = match ($uploadType) {
        'staff' => ['staff.create', 'staff.update'],
        'foods' => ['foods.create', 'foods.update'],
        'website' => ['restaurant.update'],
        'website-logo' => ['restaurant.update'],
        default => null,
    };

    if ($allowedPermissions === null) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid upload type.'
        ], 422);
        exit;
    }

    if ($allowedPermissions !== []) {
        $employee = controllersHelper::currentEmployee($conn);
        $allowed = false;
        foreach ($allowedPermissions as $permission) {
            if (controllersHelper::employeeHasPermission($employee, $permission)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            jsonResponse([
                'success' => false,
                'message' => 'You do not have permission to upload this image.'
            ], 403);
            exit;
        }
    }

    $restaurantId = controllersHelper::requestRestaurantId();
    if ($restaurantId !== null) {
        guardRestaurantAccess($conn, $restaurantId, 'uploads');
    }

    jsonResponse(controllersHelper::saveUploadedImage('image', $uploadType));
    exit;
}


if ($resource === 'auth') {
    $controller = new AuthController($conn);
    $authAction = $segments[2] ?? null;

    if ($method === 'POST' && ($authAction === null || $authAction === 'login')) {
        $controller->login();
        exit;
    }


    if ($method === 'GET' && $authAction === 'is-auth') {
        jsonResponse((new AuthController($conn, true))->isAuth() ?? []);
        exit;
    }

    if ($method === 'POST' && $authAction === 'logout') {
        $controller->logout();
        exit;
    }
}

if ($resource === 'admin' && ($segments[2] ?? null) === 'context' && $method === 'GET') {
    $employee = controllersHelper::currentEmployee($conn);
    $selectedRestaurantId = controllersHelper::getRestaurantIdFromQuery();
    $isSuperAdmin = $employee !== null && controllersHelper::isSuperAdminEmployee($employee);
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
    $isBranchBrandContext = false;
    try {
        $columns = $conn->query("SHOW COLUMNS FROM restaurants")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('parent_restaurant_id', $columns, true)) {
            $stmt = $conn->prepare("
                SELECT parent_restaurant_id, branch_management_enabled
                FROM restaurants
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $activeRestaurantId]);
            $activeRestaurant = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $isBranchBrandContext = $activeRestaurant !== null
                && empty($activeRestaurant['parent_restaurant_id'])
                && (int) ($activeRestaurant['branch_management_enabled'] ?? 0) === 1;
        }
    } catch (Throwable $e) {
        $isBranchBrandContext = false;
    }
    $permissions = controllersHelper::permissionMap($employee);

    jsonResponse([
        'success' => true,
        'data' => [
            'employee' => $employee,
            'permissions' => $permissions,
            'is_super_admin' => $isSuperAdmin,
            'selected_restaurant_id' => $canUseSelectedRestaurant ? $selectedRestaurantId : null,
            'active_restaurant_id' => $activeRestaurantId,
            'is_branch_brand_context' => $isBranchBrandContext
        ]
    ]);
    exit;
}

if ($resource === 'logs') {
    $controller = new ActivityLogsController($conn);

    if ($method === 'GET' && $id === null) {
        $employee = currentEmployee($conn);
        $restaurantId = ($employee !== null && !isSuperAdminEmployee($employee))
            ? (requestRestaurantId() ?: (int) ($employee['restaurant_id'] ?? 0))
            : requestRestaurantId();
        guardRestaurantAccess($conn, $restaurantId ?: null, 'logs');
        $scope = controllersHelper::activityLogScope($conn, $restaurantId ?: 0);
        $isBrandLogs = ($scope['branch_id'] === 0 && branchManagementEnabled($conn, $scope['restaurant_id']));
        if ($isBrandLogs && !isSuperAdminEmployee($employee ?? []) && controllersHelper::employeeHasPermission($employee, 'branches_logs.get')) {
            jsonResponse($controller->index());
            exit;
        }

        $PermissionsMiddleware->isQualifiedEmployee('logs.get');
        jsonResponse($controller->index());
        exit;
    }
}


if ($resource === 'restaurants') {
    $controller = new RestaurantController($conn);

    if ($id !== null && $action === 'tax-settings') {
        $taxController = new RestaurantTaxSettingsController($conn);
        $PermissionsMiddleware->isQualifiedEmployee('restaurant.update');
        guardRestaurantAccess($conn, $id, 'restaurant-tax-settings');

        if ($method === 'GET') {
            jsonResponse($taxController->show($id));
            exit;
        }

        if ($method === 'PUT') {
            $taxController->update($id);
            exit;
        }
    }

    if ($id !== null && $action === 'tax-settings-test' && $method === 'POST') {
        $taxController = new RestaurantTaxSettingsController($conn);
        $PermissionsMiddleware->isQualifiedEmployee('restaurant.update');
        guardRestaurantAccess($conn, $id, 'restaurant-tax-settings');
        $taxController->test($id);
        exit;
    }

    if ($method === 'GET' && $id === null) {
        jsonResponse($controller->index());
        exit;
    }

    if ($method === 'GET' && $id !== null && $action === null) {
        jsonResponse($controller->show($id));
        exit;
    }

    if ($method === 'GET' && $id !== null && $action === 'branches-dashboard') {
        requireBranchManagerPermission($conn, $id, 'branches_dashboard.get');
        guardRestaurantAccess($conn, $id, 'branches-dashboard');
        jsonResponse($controller->branchesDashboard($id));
        exit;
    }

    if ($method === 'POST' && $id === null) {
        $isBranchCreate = requestRestaurantId('parent_restaurant_id') !== null;
        $parentRestaurantId = requestRestaurantId('parent_restaurant_id');
        if ($isBranchCreate) {
            requireBranchManagerPermission($conn, $parentRestaurantId, 'branches.create');
        } else {
            $PermissionsMiddleware->isQualifiedEmployee('restaurants.create');
        }
        guardRestaurantAccess($conn, $isBranchCreate ? $parentRestaurantId : requestRestaurantId('id'), 'restaurants');
        $controller->store();
        exit;
    }

    if ($method === 'PUT' && $id !== null && $action === null) {
        $employee = currentEmployee($conn);
        $isSuperAdmin = $employee !== null && isSuperAdminEmployee($employee);

        if ($isSuperAdmin) {
            $PermissionsMiddleware->isQualifiedEmployee('restaurants.update');
        } elseif (isBranchRestaurant($conn, $id)) {
            requireBranchManagerPermission($conn, $id, 'branches.update');
        } else {
            $PermissionsMiddleware->isQualifiedEmployee('restaurant.update');
        }

        guardRestaurantAccess($conn, $id, 'restaurants');
        $controller->update($id);
        exit;
    }

    if ($method === 'DELETE' && $id !== null && $action === null) {
        if (isBranchRestaurant($conn, $id)) {
            requireBranchManagerPermission($conn, $id, 'branches.delete');
        } else {
            $PermissionsMiddleware->isQualifiedEmployee('restaurants.delete');
        }
        guardRestaurantAccess($conn, $id, 'restaurants');
        $controller->destroy($id);
        exit;
    }
};

if ($resource === 'invoices') {
    $controller = new InvoicesController($conn);

    if ($method === 'GET' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('restaurant.update');
        jsonResponse($controller->index());
        exit;
    }

    if ($id !== null && $action === null && $method === 'GET') {
        $PermissionsMiddleware->isQualifiedEmployee('restaurant.update');
        guardExistingResourceAccess($conn, 'invoices', $id);
        jsonResponse($controller->show($id));
        exit;
    }

    if ($id !== null && $action === 'retry' && $method === 'POST') {
        $PermissionsMiddleware->isQualifiedEmployee('restaurant.update');
        guardExistingResourceAccess($conn, 'invoices', $id);
        $controller->retry($id);
        exit;
    }
}


if ($resource === 'staff') {
    $controller = new StaffController($conn);

    if ($method === 'GET' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('staff.get');
        jsonResponse($controller->index());
        exit;
    }

    if ($method === 'GET' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('staff.get');
        guardExistingResourceAccess($conn, 'staff', $id);
        jsonResponse($controller->show($id));
        exit;
    }

    if ($method === 'POST' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('staff.create');
        guardRestaurantAccess($conn, requestRestaurantId(), 'staff');
        $controller->store();
        exit;
    }

    if ($method === 'PUT' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('staff.update');
        guardExistingResourceAccess($conn, 'staff', $id);
        guardRequestedRestaurantAccess($conn, 'staff');
        $controller->update($id);
        exit;
    }

    if ($method === 'DELETE' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('staff.delete');
        guardExistingResourceAccess($conn, 'staff', $id);
        $controller->destroy($id);
        exit;
    }
}


if ($resource === 'inventory') {
    $controller = new InventoryController($conn);

    if ($method === 'GET' && ($segments[2] ?? null) === 'movements') {
        $PermissionsMiddleware->isQualifiedEmployee('inventory.get');
        jsonResponse($controller->movements());
        exit;
    }

    if ($method === 'GET' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('inventory.get');
        jsonResponse($controller->index());
        exit;
    }

    if ($method === 'GET' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('inventory.get');
        jsonResponse($controller->show($id));
        exit;
    }

    if ($method === 'POST' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('inventory.create');
        guardRestaurantAccess($conn, requestRestaurantId(), 'inventory');
        $controller->store();
        exit;
    }

    if ($method === 'PUT' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('inventory.update');
        guardExistingResourceAccess($conn, 'inventory', $id);
        guardRequestedRestaurantAccess($conn, 'inventory');
        $controller->update($id);
        exit;
    }

    if ($method === 'POST' && $id !== null && $action === 'movement') {
        $PermissionsMiddleware->isQualifiedEmployee('inventory.update');
        guardExistingResourceAccess($conn, 'inventory', $id);
        $controller->movement($id);
        exit;
    }

    if ($method === 'DELETE' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('inventory.delete');
        guardExistingResourceAccess($conn, 'inventory', $id);
        $controller->destroy($id);
        exit;
    }
}


if ($resource === 'food-addons') {
    $controller = new FoodAddonsController($conn);

    if ($method === 'GET' && $id === null) {
        jsonResponse($controller->index());
        exit;
    }

    if ($method === 'GET' && $id !== null && $action === null) {
        jsonResponse($controller->show($id));
        exit;
    }

    if ($method === 'POST' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('foods.create');
        guardRestaurantAccess($conn, requestRestaurantId(), 'food-addons');
        $controller->store();
        exit;
    }

    if ($method === 'PUT' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('foods.update');
        guardExistingResourceAccess($conn, 'food-addons', $id);
        guardRequestedRestaurantAccess($conn, 'food-addons');
        $controller->update($id);
        exit;
    }

    if ($method === 'DELETE' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('foods.delete');
        guardExistingResourceAccess($conn, 'food-addons', $id);
        $controller->destroy($id);
        exit;
    }
}

if ($resource === 'discounts') {
    $controller = new DiscountsController($conn);

    if ($method === 'GET' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('discounts.get');
        jsonResponse($controller->index());
        exit;
    }

    if ($method === 'GET' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('discounts.get');
        guardExistingResourceAccess($conn, 'discounts', $id);
        jsonResponse($controller->show($id));
        exit;
    }

    if ($method === 'POST' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('discounts.create');
        guardRestaurantAccess($conn, requestRestaurantId(), 'discounts');
        $controller->store();
        exit;
    }

    if ($method === 'PUT' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('discounts.update');
        guardExistingResourceAccess($conn, 'discounts', $id);
        guardRequestedRestaurantAccess($conn, 'discounts');
        $controller->update($id);
        exit;
    }

    if ($method === 'DELETE' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('discounts.delete');
        guardExistingResourceAccess($conn, 'discounts', $id);
        $controller->destroy($id);
        exit;
    }
}


if ($resource === 'orders') {
    $controller = new OrdersController($conn);

    if ($method === 'GET' && $id === null) {
        jsonResponse($controller->index());
        exit;
    }

    if ($method === 'GET' && $id !== null && $action === null) {
        jsonResponse($controller->show($id));
        exit;
    }
    if ($method === 'POST' && $id === null) {
        $controller->store();
        exit;
    }

    if ($method === 'PUT' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('orders.update');
        guardExistingResourceAccess($conn, 'orders', $id);
        guardRequestedRestaurantAccess($conn, 'orders');
        $controller->update($id);
        exit;
    }

    if ($method === 'PATCH' && $id !== null && $action === 'takeaway-status') {
        $PermissionsMiddleware->isQualifiedEmployee('orders.update');
        guardExistingResourceAccess($conn, 'orders', $id);
        $controller->updateTakeawayStatus($id);
        exit;
    }

    if ($method === 'PATCH' && $id !== null && $action === 'takeaway-payment') {
        $PermissionsMiddleware->isQualifiedEmployee('orders.update');
        guardExistingResourceAccess($conn, 'orders', $id);
        $controller->updateTakeawayPayment($id);
        exit;
    }

    if ($method === 'DELETE' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('orders.delete');
        guardExistingResourceAccess($conn, 'orders', $id);
        $controller->destroy($id);
        exit;
    }
}

if ($resource === 'order-foods') {
    $controller = new OrdersController($conn);

    if ($method === 'PATCH' && $id !== null && $action === 'status') {
        $PermissionsMiddleware->isQualifiedEmployee('orders.update');
        guardExistingResourceAccess($conn, 'order-foods', $id);
        $controller->updateFoodStatus($id);
        exit;
    }
}


if ($resource === 'tables') {
    $controller = new TableController($conn);

    // GET /api/tables
    if ($method === 'GET' && $id === null) {
        jsonResponse($controller->index());
        exit;
    }


    // GET /api/tables/{id}
    if ($method === 'GET' && $id !== null && $action === null) {
        jsonResponse($controller->show($id));
        exit;
    }


    // POST /api/tables
    if ($method === 'POST' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('tables.create');
        guardRestaurantAccess($conn, requestRestaurantId(), 'tables');
        $controller->store();
        exit;
    }


    // PUT /api/tables/{id}
    if ($method === 'PUT' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('tables.update');
        guardExistingResourceAccess($conn, 'tables', $id);
        guardRequestedRestaurantAccess($conn, 'tables');
        $controller->update($id);
        exit;
    }


    // DELETE /api/tables/{id}
    if ($method === 'DELETE' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('tables.delete');
        guardExistingResourceAccess($conn, 'tables', $id);
        $controller->destroy($id);
        exit;
    }


    // PATCH /api/tables/{id}/status
    if (
        $method === 'PATCH' &&
        $id !== null &&
        $action === 'status'
    ) {

        $PermissionsMiddleware->isQualifiedEmployee('tables.update');
        guardExistingResourceAccess($conn, 'tables', $id);
        $controller->updateStatus($id);
        exit;
    }
}


if ($resource === 'menu-foods') {
    $controller = new FoodController($conn);

    // GET /api/menu-foods
    if ($method === 'GET' && $id === null) {
        jsonResponse($controller->index());
        exit;
    }

    // GET /api/menu-foods/{id}
    if ($method === 'GET' && $id !== null && $action === null) {
        jsonResponse($controller->show($id));
        exit;
    }

    // POST /api/menu-foods
    if ($method === 'POST' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('foods.create');
        guardRestaurantAccess($conn, requestRestaurantId(), 'menu-foods');
        $controller->store();
        exit;
    }

    // PUT /api/menu-foods/{id}
    if ($method === 'PUT' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('foods.update');
        guardExistingResourceAccess($conn, 'menu-foods', $id);
        guardRequestedRestaurantAccess($conn, 'menu-foods');
        $controller->update($id);
        exit;
    }

    // DELETE /api/menu-foods/{id}
    if ($method === 'DELETE' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('foods.delete');
        guardExistingResourceAccess($conn, 'menu-foods', $id);
        $controller->destroy($id);
        exit;
    }
}


if ($resource === 'menu-categories') {
    $controller = new CategoriesController($conn);

    // GET /api/menu-categories
    if ($method === 'GET' && $id === null) {
        jsonResponse($controller->index());
        exit;
    }

    // GET /api/menu-categories/{id}
    if ($method === 'GET' && $id !== null && $action === null) {
        jsonResponse($controller->show($id));
        exit;
    }

    // POST /api/menu-categories
    if ($method === 'POST' && $id === null) {
        $PermissionsMiddleware->isQualifiedEmployee('categories.create');
        guardRestaurantAccess($conn, requestRestaurantId(), 'menu-categories');
        $controller->store();
        exit;
    }

    // PUT /api/menu-categories/{id}
    if ($method === 'PUT' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('categories.update');
        guardExistingResourceAccess($conn, 'menu-categories', $id);
        guardRequestedRestaurantAccess($conn, 'menu-categories');
        $controller->update($id);
        exit;
    }

    // DELETE /api/menu-categories/{id}
    if ($method === 'DELETE' && $id !== null && $action === null) {
        $PermissionsMiddleware->isQualifiedEmployee('categories.delete');
        guardExistingResourceAccess($conn, 'menu-categories', $id);
        $controller->destroy($id);
        exit;
    }
};




response404();

function requestRestaurantId(string $field = 'restaurant_id'): ?int
{
    return controllersHelper::requestRestaurantId($field);
}

function guardExistingResourceAccess(PDO $conn, string $resource, int $id): void
{
    $restaurantId = resourceRestaurantId($conn, $resource, $id);

    if ($restaurantId === null) {
        jsonResponse([
            'success' => false,
            'message' => 'Resource not found.'
        ], 404);
        exit;
    }

    guardRestaurantAccess($conn, $restaurantId, $resource);
}

function guardRestaurantAccess(PDO $conn, ?int $restaurantId, string $resource): void
{
    $employee = currentEmployee($conn);

    if ($employee === null) {
        jsonResponse([
            'success' => false,
            'message' => 'API-KEY is required.'
        ], 401);
        exit;
    }

    if (isSuperAdminEmployee($employee)) {
        return;
    }

    if ($restaurantId === null) {
        jsonResponse([
            'success' => false,
            'message' => 'Restaurant ID is required.'
        ], 422);
        exit;
    }

    if (!controllersHelper::employeeCanAccessRestaurant($conn, $employee, $restaurantId)) {
        jsonResponse([
            'success' => false,
            'message' => 'Permission denied: cannot change another restaurant data.',
            'resource' => $resource
        ], 403);
        exit;
    }
}

function guardRequestedRestaurantAccess(PDO $conn, string $resource): void
{
    $restaurantId = requestRestaurantId();

    if ($restaurantId !== null) {
        guardRestaurantAccess($conn, $restaurantId, $resource);
    }
}

function isBranchRestaurant(PDO $conn, int $restaurantId): bool
{
    try {
        $columns = $conn->query("SHOW COLUMNS FROM restaurants")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('parent_restaurant_id', $columns, true)) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT parent_restaurant_id
            FROM restaurants
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $restaurantId]);
        $parentId = $stmt->fetchColumn();

        return $parentId !== false && (int) $parentId > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function requireBranchManagerPermission(PDO $conn, ?int $restaurantId, string $permission): void
{
    global $PermissionsMiddleware;

    $employee = currentEmployee($conn);
    if ($employee !== null && isSuperAdminEmployee($employee)) {
        return;
    }

    if ($employee !== null && controllersHelper::employeeHasPermission($employee, $permission)) {
        return;
    }

    $brandId = branchBrandId($conn, (int) ($restaurantId ?? 0));
    if (
        $brandId > 0
        && (!empty($employee['is_owner']) || !empty($employee['is_manager']) || !empty($employee['manager_scope']))
        && controllersHelper::employeeCanAccessRestaurant($conn, $employee, $brandId)
        && branchManagementEnabled($conn, $brandId)
        && controllersHelper::employeeHasPermission($employee, $permission)
    ) {
        return;
    }

    $PermissionsMiddleware->isQualifiedEmployee($permission);
}

function branchBrandId(PDO $conn, int $restaurantId): int
{
    if ($restaurantId <= 0) {
        return 0;
    }

    try {
        $columns = $conn->query("SHOW COLUMNS FROM restaurants")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('parent_restaurant_id', $columns, true)) {
            return $restaurantId;
        }

        $stmt = $conn->prepare("
            SELECT id, parent_restaurant_id
            FROM restaurants
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $restaurantId]);
        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$restaurant) {
            return 0;
        }

        return !empty($restaurant['parent_restaurant_id'])
            ? (int) $restaurant['parent_restaurant_id']
            : (int) $restaurant['id'];
    } catch (Throwable $e) {
        return $restaurantId;
    }
}

function branchManagementEnabled(PDO $conn, int $restaurantId): bool
{
    if ($restaurantId <= 0) {
        return false;
    }

    try {
        $columns = $conn->query("SHOW COLUMNS FROM restaurants")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('branch_management_enabled', $columns, true)) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT branch_management_enabled
            FROM restaurants
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $restaurantId]);

        return (int) $stmt->fetchColumn() === 1;
    } catch (Throwable $e) {
        return false;
    }
}

function currentEmployee(PDO $conn): ?array
{
    $auth = new AuthController($conn, true);
    $response = $auth->isAuth();
    $employee = $response['data']['employee'] ?? null;

    return is_array($employee) ? $employee : null;
}

function isSuperAdminEmployee(array $employee): bool
{
    return controllersHelper::isSuperAdminEmployee($employee);
}

function resourceRestaurantId(PDO $conn, string $resource, int $id): ?int
{
    $map = [
        'staff' => ['table' => 'staff', 'key' => 'id'],
        'inventory' => ['table' => 'inventory', 'key' => 'id'],
        'food-addons' => ['table' => 'food_addons', 'key' => 'id'],
        'discounts' => ['table' => 'discounts', 'key' => 'id'],
        'invoices' => ['table' => 'invoices', 'key' => 'id'],
        'order-foods' => ['table' => 'order_foods', 'key' => 'id'],
        'orders' => ['table' => 'orders', 'key' => 'order_id'],
        'tables' => ['table' => 'tables', 'key' => 'id'],
        'menu-foods' => ['table' => 'menu_foods', 'key' => 'id'],
        'menu-categories' => ['table' => 'menu_categories', 'key' => 'id'],
        'restaurants' => ['table' => 'restaurants', 'key' => 'id'],
    ];

    if (!isset($map[$resource])) {
        throw new InvalidArgumentException('Unsupported resource guard.');
    }

    if ($resource === 'restaurants') {
        return $id;
    }

    $table = $map[$resource]['table'];
    $key = $map[$resource]['key'];

    $stmt = $conn->prepare("
        SELECT restaurant_id
        FROM `$table`
        WHERE `$key` = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $restaurantId = $stmt->fetchColumn();

    return $restaurantId !== false ? (int) $restaurantId : null;
}

function jsonResponse(array $response, int $statusCode = 200): void
{
    controllersHelper::jsonResponse($response, $statusCode);
}

function response404(): void
{
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'API route not found.'
    ]);

    exit;
}
