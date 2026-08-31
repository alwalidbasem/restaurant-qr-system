<?php

require_once __DIR__ . '/../../Middleware/PermissionsMiddleware.php';
class LogController
{

    private function getJsonInput(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    private function getHeaderValue(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($_SERVER[$serverKey] ?? '');
    }

    private function getRestaurantIdFromQuery(): ?int
    {
        $restaurantId = filter_input(INPUT_GET, 'restaurant_id', FILTER_VALIDATE_INT);

        return ($restaurantId !== false && $restaurantId !== null && $restaurantId > 0)
            ? $restaurantId
            : null;
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function apiResponse(array $body, int $statusCode = 200): array
    {
        return [
            'body' => $body,
            'status' => $statusCode
        ];
    }

    private function permissionData(array $data, string $crudName): array
    {
        require __DIR__ . '/../../../config/database.php';

        $middleware = new PermissionsMiddleware($conn);

        if ($this->isListData($data)) {
            return array_map(
                fn (array $row): array => $this->filterPermissionRow($row, $crudName, $middleware),
                $data
            );
        }

        return $this->filterPermissionRow($data, $crudName, $middleware);
    }

    private function filterPermissionRow(array $row, string $crudName, PermissionsMiddleware $middleware): array
    {
        $hadRestaurantId = array_key_exists('restaurant_id', $row);
        $filtered = $middleware->final_data($this->filterNestedPublicData($row, $middleware), $crudName);

        if (!$hadRestaurantId && array_key_exists('restaurant_id', $filtered)) {
            unset($filtered['restaurant_id']);
        }

        return $filtered;
    }

    private function filterNestedPublicData(array $row, PermissionsMiddleware $middleware): array
    {
        if (isset($row['addons']) && is_array($row['addons'])) {
            $row['addons'] = $middleware->final_data($row['addons'], 'food_addons.get');
        }

        if (isset($row['id']) && !isset($row['restaurant_id'])) {
            $row['restaurant_id'] = $row['id'];
        }

        return $row;
    }

    private function isListData(array $data): bool
    {
        return $data !== [] && array_keys($data) === range(0, count($data) - 1);
    }

}
