<?php
require_once __DIR__ . '/../config/variables.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/Controllers/TablesController.php';
require_once __DIR__ . '/../api/Controllers/FoodController.php';
require_once __DIR__ . '/../api/Controllers/CategoriesController.php';
require_once __DIR__ . '/../api/Controllers/RestaurantController.php';
require_once __DIR__ . '/../api/Controllers/EmployeeController.php';
require_once __DIR__ . '/../api/Controllers/AuthController.php';
require_once __DIR__ . '/../api/Controllers/InventoryController.php';
require_once __DIR__ . '/../api/Controllers/FoodAddonsController.php';
require_once __DIR__ . '/../api/Controllers/OrdersController.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$route = $_GET['route'] ?? $uri;
$route = trim((string) $route, '/');
$segments = explode('/', $route);
if (($segments[0] ?? null) !== 'api') {
    array_unshift($segments, 'api');
}
$resource = $segments[1] ?? null;
$id = isset($segments[2]) ? (int) $segments[2] : null;
$action = $segments[3] ?? null;


if ($resource === 'auth') {
    $controller = new AuthController($conn);
    $authAction = $segments[2] ?? null;

    if ($method === 'POST' && $authAction === 'login') {
        $controller->login();
        exit;
    }

    if ($method === 'GET' && $authAction === 'me') {
        $controller->me();
        exit;
    }

    if ($method === 'POST' && $authAction === 'logout') {
        $controller->logout();
        exit;
    }

    if ($method === 'POST' && $authAction === 'verify-password') {
        $controller->verifyPassword();
        exit;
    }

    if ($method === 'POST' && $authAction === 'change-password') {
        $controller->changePassword();
        exit;
    }

    if ($method === 'POST' && $authAction === 'reset-password') {
        $controller->resetPassword();
        exit;
    }
}


if ($resource === 'restaurants') {
    routeCrud(new RestaurantController($conn), $method, $id, $action);
}


if ($resource === 'employees') {
    routeCrud(new EmployeeController($conn), $method, $id, $action);
}


if ($resource === 'inventory') {
    routeCrud(new InventoryController($conn), $method, $id, $action);
}


if ($resource === 'food-addons') {
    routeCrud(new FoodAddonsController($conn), $method, $id, $action);
}


if ($resource === 'orders') {
    routeCrud(new OrdersController($conn), $method, $id, $action);
}


if ($resource === 'tables') {
    $controller = new TableController($conn);

    // GET /api/tables
    if ($method === 'GET' && $id === null) {
        $controller->index();
        exit;
    }


    // GET /api/tables/{id}
    if ($method === 'GET' && $id !== null && $action === null) {
        $controller->show($id);
        exit;
    }


    // POST /api/tables
    if ($method === 'POST' && $id === null) {
        $controller->store();
        exit;
    }


    // PUT /api/tables/{id}
    if ($method === 'PUT' && $id !== null && $action === null) {
        $controller->update($id);
        exit;
    }


    // DELETE /api/tables/{id}
    if ($method === 'DELETE' && $id !== null && $action === null) {
        $controller->destroy($id);
        exit;
    }


    // PATCH /api/tables/{id}/status
    if (
        $method === 'PATCH' &&
        $id !== null &&
        $action === 'status'
    ) {

        $controller->updateStatus($id);
        exit;
    }
}


if ($resource === 'menu-foods') {
    $controller = new FoodController($conn);

    // GET /api/menu-foods
    if ($method === 'GET' && $id === null) {
        $controller->index();
        exit;
    }

    // GET /api/menu-foods/{id}
    if ($method === 'GET' && $id !== null && $action === null) {
        $controller->show($id);
        exit;
    }

    // POST /api/menu-foods
    if ($method === 'POST' && $id === null) {
        $controller->store();
        exit;
    }

    // PUT /api/menu-foods/{id}
    if ($method === 'PUT' && $id !== null && $action === null) {
        $controller->update($id);
        exit;
    }

    // DELETE /api/menu-foods/{id}
    if ($method === 'DELETE' && $id !== null && $action === null) {
        $controller->destroy($id);
        exit;
    }
}


if ($resource === 'menu-categories') {
    $controller = new CategoriesController($conn);

    // GET /api/menu-categories
    if ($method === 'GET' && $id === null) {
        $controller->index();
        exit;
    }

    // GET /api/menu-categories/{id}
    if ($method === 'GET' && $id !== null && $action === null) {
        $controller->show($id);
        exit;
    }

    // POST /api/menu-categories
    if ($method === 'POST' && $id === null) {
        $controller->store();
        exit;
    }

    // PUT /api/menu-categories/{id}
    if ($method === 'PUT' && $id !== null && $action === null) {
        $controller->update($id);
        exit;
    }

    // DELETE /api/menu-categories/{id}
    if ($method === 'DELETE' && $id !== null && $action === null) {
        $controller->destroy($id);
        exit;
    }
}


response404();


function routeCrud(object $controller, string $method, ?int $id, ?string $action): void
{
    if ($method === 'GET' && $id === null) {
        $controller->index();
        exit;
    }

    if ($method === 'GET' && $id !== null && $action === null) {
        $controller->show($id);
        exit;
    }

    if ($method === 'POST' && $id === null) {
        $controller->store();
        exit;
    }

    if ($method === 'PUT' && $id !== null && $action === null) {
        $controller->update($id);
        exit;
    }

    if ($method === 'DELETE' && $id !== null && $action === null) {
        $controller->destroy($id);
        exit;
    }
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
