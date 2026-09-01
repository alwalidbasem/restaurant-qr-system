<?php

require_once __DIR__ . '/../Middleware/PermissionsMiddleware.php';
require_once __DIR__ . '/Auth/AuthController.php';

class controllersHelper
{
    public static function getJsonInput(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    public static function getHeaderValue(string $name): string
    {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($_SERVER[$serverKey] ?? '');
    }

    public static function getRestaurantIdFromQuery(string $field = 'restaurant_id'): ?int
    {
        $restaurantId = filter_input(INPUT_GET, $field, FILTER_VALIDATE_INT);
        if (($restaurantId === false || $restaurantId === null) && $field === 'restaurant_id') {
            $restaurantId = filter_input(INPUT_GET, 'branch_id', FILTER_VALIDATE_INT);
        }

        return ($restaurantId !== false && $restaurantId !== null && $restaurantId > 0)
            ? (int) $restaurantId
            : null;
    }

    public static function requestRestaurantId(string $field = 'restaurant_id'): ?int
    {
        static $json = null;

        if ($json === null) {
            $json = self::getJsonInput();
        }

        $value = $_POST[$field] ?? $_POST['restaurant_id'] ?? $json[$field] ?? $json['restaurant_id'] ?? null;
        $restaurantId = filter_var($value, FILTER_VALIDATE_INT);

        return ($restaurantId !== false && $restaurantId !== null && $restaurantId > 0)
            ? (int) $restaurantId
            : null;
    }

    public static function jsonResponse(array $response, int $statusCode = 200): void
    {
        if (
            array_key_exists('body', $response)
            && is_array($response['body'])
            && array_key_exists('status', $response)
        ) {
            $statusCode = (int) $response['status'];
            $response = $response['body'];
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $response,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }

    public static function apiResponse(array $body, int $statusCode = 200): array
    {
        return [
            'body' => $body,
            'status' => $statusCode
        ];
    }

    public static function permissionData(PDO $conn, array $data, string $crudName): array
    {
        $middleware = new PermissionsMiddleware($conn);

        if (self::isListData($data)) {
            return array_map(
                fn (array $row): array => self::filterPermissionRow($row, $crudName, $middleware),
                $data
            );
        }

        return self::filterPermissionRow($data, $crudName, $middleware);
    }

    public static function currentEmployee(PDO $conn): ?array
    {
        $auth = new AuthController($conn, true);
        $response = $auth->isAuth();
        $employee = $response['data']['employee'] ?? null;

        return is_array($employee) ? $employee : null;
    }

    public static function isSuperAdminEmployee(array $employee): bool
    {
        if (array_key_exists('is_superadmin', $employee)) {
            return (int) ($employee['is_superadmin'] ?? 0) === 1;
        }

        $webAdmins = require __DIR__ . '/../Middleware/permissions_config/restaurant_crud_admins.php';
        $webAdminIds = array_map('intval', $webAdmins['employee_ids'] ?? $webAdmins);

        return (int) ($employee['restaurant_id'] ?? 0) === 1
            && in_array((int) ($employee['id'] ?? 0), $webAdminIds, true);
    }

    public static function permissionDefinitions(): array
    {
        $definitions = require __DIR__ . '/../Middleware/permissions_config/definitions.php';
        if (isset($definitions['is_superadmin']) || isset($definitions['is_owner']) || isset($definitions['is_manager']) || isset($definitions['is_employee'])) {
            $flat = [];
            foreach (['is_superadmin', 'is_manager', 'is_employee'] as $role) {
                foreach (($definitions[$role] ?? []) ?: [] as $key => $label) {
                    $flat[$key] = $label;
                }
            }

            return $flat;
        }

        return $definitions;
    }

    public static function permissionRoleDefinitions(): array
    {
        $definitions = require __DIR__ . '/../Middleware/permissions_config/definitions.php';

        return isset($definitions['is_superadmin']) || isset($definitions['is_owner']) || isset($definitions['is_manager']) || isset($definitions['is_employee'])
            ? $definitions
            : [
                'is_superadmin' => array_slice($definitions, 0, 4, true),
                'is_owner' => null,
                'is_manager' => array_intersect_key($definitions, array_flip(['branches.create', 'branches.get', 'branches.update', 'branches.delete', 'branches_logs.get'])),
                'is_employee' => array_diff_key($definitions, array_flip(['restaurants.create', 'restaurants.get', 'restaurants.update', 'restaurants.delete', 'branches.create', 'branches.get', 'branches.update', 'branches.delete', 'branches_logs.get', 'branches_dashboard.get', 'managers.create', 'managers.get', 'managers.update', 'managers.delete'])),
            ];
    }

    public static function permissionKeys(): array
    {
        return array_keys(self::permissionDefinitions());
    }

    public static function employeeRoleKey(?array $employee): string
    {
        if ($employee === null) {
            return 'guest';
        }

        foreach (['is_superadmin', 'is_owner', 'is_manager', 'is_employee'] as $role) {
            if (!empty($employee[$role])) {
                return $role;
            }
        }

        return 'is_employee';
    }

    public static function roleAllowedPermissions(?array $employee): ?array
    {
        $role = self::employeeRoleKey($employee);
        $definitions = self::permissionRoleDefinitions();

        if ($role === 'is_owner') {
            return null;
        }

        return $definitions[$role] ?? [];
    }

    public static function permissionMap(?array $employee = null): array
    {
        $keys = self::permissionKeys();
        $values = array_map('trim', explode(',', (string) ($employee['permissions'] ?? '')));
        $map = [];

        foreach ($keys as $index => $key) {
            $map[$key] = ($values[$index] ?? '0') === '1';
        }

        return $map;
    }

    public static function employeeHasPermission(?array $employee, string $permission): bool
    {
        if ($employee === null) {
            return false;
        }

        if (self::isSuperAdminEmployee($employee)) {
            if (in_array($permission, ['restaurants.create', 'restaurants.get', 'restaurants.update', 'restaurants.delete'], true)) {
                return !empty(self::permissionMap($employee)[$permission]);
            }
            return true;
        }

        if (!empty($employee['is_owner'])) {
            return !isset((self::permissionRoleDefinitions()['is_superadmin'] ?? [])[$permission]);
        }

        if (!empty($employee['is_manager'])) {
            // A manager's capabilities come from his permission string:
            //   - branch-management keys (branches.*, branch logs) => his "manager permissions",
            //   - in-branch keys (staff, inventory, orders, foods, categories, tables, logs, discounts)
            //     => what he may do INSIDE the branches he manages.
            // Which branches he may act on is governed separately by allowed_branches / manager_scope.
            return !empty(self::permissionMap($employee)[$permission]);
        }

        $allowed = self::roleAllowedPermissions($employee);
        if ($allowed !== null && !isset($allowed[$permission])) {
            return false;
        }

        $map = self::permissionMap($employee);

        return !empty($map[$permission]);
    }

    public static function effectiveRestaurantId(?array $employee): int
    {
        if ($employee === null) {
            return 0;
        }

        return !empty($employee['branch_id'])
            ? (int) $employee['branch_id']
            : (int) ($employee['restaurant_id'] ?? 0);
    }

    public static function employeeCanAccessRestaurant(PDO $conn, ?array $employee, int $restaurantId): bool
    {
        if ($employee === null || $restaurantId <= 0) {
            return false;
        }

        if (self::isSuperAdminEmployee($employee)) {
            return true;
        }

        if (self::effectiveRestaurantId($employee) === $restaurantId) {
            return true;
        }

        $baseRestaurantId = (int) ($employee['restaurant_id'] ?? 0);
        if ($baseRestaurantId <= 0 || $baseRestaurantId === $restaurantId) {
            return $baseRestaurantId === $restaurantId;
        }

        $allowedBranches = trim((string) ($employee['allowed_branches'] ?? $employee['managed_branches'] ?? ''));
        if ($allowedBranches !== '' && strtolower($allowedBranches) !== 'all') {
            $managedIds = array_map('intval', array_filter(array_map('trim', explode(',', $allowedBranches))));
            if (in_array($restaurantId, $managedIds, true)) {
                return true;
            }

            if (!empty($employee['is_manager'])) {
                return false;
            }
        }

        if (strtolower($allowedBranches) !== 'all' && (string) ($employee['manager_scope'] ?? '') === 'none') {
            return false;
        }

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

            return $parentId !== false && (int) $parentId === $baseRestaurantId;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function logActivity(
        PDO $conn,
        int $restaurantId,
        string $permissionKey,
        string $actionLabel,
        string $entityType,
        string|int|null $entityId = null,
        array $metadata = []
    ): void {
        if ($restaurantId <= 0) {
            return;
        }

        try {
            require_once __DIR__ . '/../Models/ActivityLogModel.php';
            $employee = self::currentEmployee($conn);
            $employeeName = $employee['name'] ?? 'System';
            $message = trim($employeeName . ' - ' . $actionLabel . ($entityId !== null ? ' (' . ucfirst($entityType) . ' ' . $entityId . ')' : ''));
            $scope = self::activityLogScope($conn, $restaurantId);
            if (isset($metadata['_log_branch_id'])) {
                $scope['branch_id'] = max(0, (int) $metadata['_log_branch_id']);
                unset($metadata['_log_branch_id']);
            }

            (new ActivityLog($conn))->create([
                'restaurant_id' => $scope['restaurant_id'],
                'branch_id' => $scope['branch_id'],
                'employee_id' => isset($employee['id']) ? (int) $employee['id'] : null,
                'employee_name' => $employeeName,
                'permission_key' => $permissionKey,
                'entity_type' => $entityType,
                'entity_id' => $entityId !== null ? (string) $entityId : null,
                'action_label' => $actionLabel,
                'message' => $message,
                'metadata' => $metadata,
            ]);
        } catch (Throwable $e) {
            // Logging must never break the restaurant workflow.
        }
    }

    public static function activityLogScope(PDO $conn, int $restaurantId): array
    {
        if ($restaurantId <= 0) {
            return ['restaurant_id' => 0, 'branch_id' => 0];
        }

        try {
            $columns = $conn->query("SHOW COLUMNS FROM restaurants")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('parent_restaurant_id', $columns, true)) {
                return ['restaurant_id' => $restaurantId, 'branch_id' => 0];
            }

            $stmt = $conn->prepare("
                SELECT parent_restaurant_id
                FROM restaurants
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $restaurantId]);
            $parentId = $stmt->fetchColumn();

            if ($parentId !== false && (int) $parentId > 0) {
                return [
                    'restaurant_id' => (int) $parentId,
                    'branch_id' => $restaurantId,
                ];
            }
        } catch (Throwable $e) {
            return ['restaurant_id' => $restaurantId, 'branch_id' => 0];
        }

        return ['restaurant_id' => $restaurantId, 'branch_id' => 0];
    }

    public static function changedFields(array $before, array $after, array $ignore = []): array
    {
        $ignored = array_fill_keys($ignore, true);
        $changes = [];

        foreach ($after as $key => $newValue) {
            if (isset($ignored[$key]) || is_array($newValue)) {
                continue;
            }

            $oldValue = $before[$key] ?? null;
            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changes[$key] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    public static function saveUploadedImage(string $field, string $type = 'general'): array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
            return [
                'success' => false,
                'message' => 'No image file was uploaded.'
            ];
        }

        $file = $_FILES[$field];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Image upload failed.'
            ];
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return [
                'success' => false,
                'message' => 'Image must be 5MB or smaller.'
            ];
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath) || !is_readable($tmpPath)) {
            return [
                'success' => false,
                'message' => 'Invalid uploaded image.'
            ];
        }

        $safeType = preg_replace('/[^a-z0-9_-]/i', '', $type) ?: 'general';
        $projectRoot = dirname(__DIR__, 2);
        $relativeDir = 'public/uploads/admin/' . $safeType;
        $targetDir = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return [
                'success' => false,
                'message' => 'Unable to prepare upload folder.'
            ];
        }

        $mime = mime_content_type($tmpPath) ?: '';
        $originalName = strtolower((string) ($file['name'] ?? ''));
        $rawImage = file_get_contents($tmpPath);
        if ($rawImage === false || self::imageContainsCodePayload($rawImage)) {
            return [
                'success' => false,
                'message' => 'Image was rejected because it contains suspicious embedded code.'
            ];
        }

        if ($safeType === 'website-logo' && self::isSvgUpload($rawImage, $mime, $originalName)) {
            $svg = self::sanitizeSvgLogo($rawImage);
            if ($svg === null) {
                return [
                    'success' => false,
                    'message' => 'SVG logo was rejected because it contains unsafe content.'
                ];
            }

            $filename = bin2hex(random_bytes(12)) . '.svg';
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;
            if (file_put_contents($targetPath, $svg, LOCK_EX) === false) {
                return [
                    'success' => false,
                    'message' => 'Unable to save uploaded logo.'
                ];
            }

            return self::uploadedImageResponse($projectRoot, $relativeDir, $filename);
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            return [
                'success' => false,
                'message' => 'Image processing is not enabled on this server.'
            ];
        }

        $imageInfo = @getimagesize($tmpPath);
        $allowedTypes = [
            IMAGETYPE_JPEG => 'JPEG',
            IMAGETYPE_PNG => 'PNG',
            IMAGETYPE_WEBP => 'WEBP',
            IMAGETYPE_AVIF => 'AVIF',
            IMAGETYPE_GIF => 'GIF',
        ];
        $extensions = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_AVIF => 'avif',
            IMAGETYPE_GIF => 'gif',
        ];

        if ($imageInfo === false || !isset($allowedTypes[(int) ($imageInfo[2] ?? 0)])) {
            return [
                'success' => false,
                'message' => $safeType === 'website-logo'
                    ? 'Logo must be JPG, PNG, WEBP, GIF, or SVG.'
                    : 'Only JPG, PNG, AVIF, WEBP, and GIF images are allowed.'
            ];
        }

        if (!str_starts_with($mime, 'image/')) {
            return [
                'success' => false,
                'message' => 'Uploaded file is not a valid image.'
            ];
        }

        $image = @imagecreatefromstring($rawImage);
        if (!$image) {
            return [
                'success' => false,
                'message' => 'Image could not be decoded safely.'
            ];
        }

        $filename = bin2hex(random_bytes(12)) . '.webp';
        if ($safeType === 'website-logo') {
            $filename = bin2hex(random_bytes(12)) . '.' . $extensions[(int) $imageInfo[2]];
        }
        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        $saved = $safeType === 'website-logo'
            ? self::writeImageInOriginalFormat($image, $targetPath, (int) $imageInfo[2])
            : self::writeWebpImage($image, $targetPath, (int) ($file['size'] ?? 0));

        if (!$saved) {
            imagedestroy($image);

            return [
                'success' => false,
                'message' => $safeType === 'website-logo'
                    ? 'Unable to save uploaded logo.'
                    : 'Unable to convert uploaded image to WEBP.'
            ];
        }

        imagedestroy($image);

        return self::uploadedImageResponse($projectRoot, $relativeDir, $filename);
    }

    private static function uploadedImageResponse(string $projectRoot, string $relativeDir, string $filename): array
    {
        $projectName = basename($projectRoot);
        $publicPath = '/' . $projectName . '/' . $relativeDir . '/' . $filename;

        return [
            'success' => true,
            'data' => [
                'path' => $publicPath
            ]
        ];
    }

    private static function isSvgUpload(string $bytes, string $mime, string $filename): bool
    {
        $trimmed = ltrim($bytes);

        return str_contains(strtolower($mime), 'svg')
            || str_ends_with($filename, '.svg')
            || str_starts_with(strtolower($trimmed), '<svg')
            || str_starts_with(strtolower($trimmed), '<?xml');
    }

    private static function sanitizeSvgLogo(string $svg): ?string
    {
        if (strlen($svg) > 1024 * 1024 || str_contains($svg, "\0")) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$dom->documentElement || strtolower($dom->documentElement->localName) !== 'svg') {
            return null;
        }

        $allowedTags = [
            'svg' => true,
            'title' => true,
            'desc' => true,
            'metadata' => true,
            'defs' => true,
            'g' => true,
            'path' => true,
            'circle' => true,
            'ellipse' => true,
            'rect' => true,
            'line' => true,
            'polyline' => true,
            'polygon' => true,
            'lineargradient' => true,
            'radialgradient' => true,
            'stop' => true,
            'clippath' => true,
            'mask' => true,
            'pattern' => true,
            'use' => true,
            'text' => true,
            'tspan' => true,
        ];
        $blockedTags = [
            'script' => true,
            'foreignobject' => true,
            'iframe' => true,
            'object' => true,
            'embed' => true,
            'audio' => true,
            'video' => true,
            'canvas' => true,
            'link' => true,
            'style' => true,
        ];

        $nodes = [];
        foreach ($dom->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }

        foreach ($nodes as $node) {
            $tag = strtolower($node->localName);
            if (isset($blockedTags[$tag]) || !isset($allowedTags[$tag])) {
                return null;
            }

            if (!$node->hasAttributes()) {
                continue;
            }

            $attributes = [];
            foreach ($node->attributes as $attribute) {
                $attributes[] = $attribute;
            }

            foreach ($attributes as $attribute) {
                $name = strtolower($attribute->name);
                $value = trim((string) $attribute->value);
                $lowerValue = strtolower($value);

                if (str_starts_with($name, 'on') || str_contains($lowerValue, 'javascript:')) {
                    return null;
                }

                if (in_array($name, ['href', 'xlink:href', 'src'], true)) {
                    $isLocalRef = str_starts_with($value, '#');
                    if (!$isLocalRef) {
                        return null;
                    }
                }
            }
        }

        $dom->formatOutput = false;

        return $dom->saveXML($dom->documentElement) ?: null;
    }

    private static function imageContainsCodePayload(string $bytes): bool
    {
        $head = strtolower(substr($bytes, 0, 8192));
        $tail = strtolower(substr($bytes, -8192));
        $sample = $head . $tail;
        $signatures = [
            '<?php',
            '<?=',
            '<script',
            '</script',
            'javascript:',
            'onerror=',
            'onload=',
            'eval(',
            'base64_decode(',
            'shell_exec(',
            'passthru(',
            'system(',
        ];

        foreach ($signatures as $signature) {
            if (str_contains($sample, $signature)) {
                return true;
            }
        }

        return false;
    }

    private static function writeImageInOriginalFormat(GdImage $source, string $targetPath, int $imageType): bool
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $image = imagecreatetruecolor($width, $height);

        if (!$image) {
            return false;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, $width, $height, $transparent);
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);

        $saved = match ($imageType) {
            IMAGETYPE_JPEG => @imagejpeg($image, $targetPath, 92),
            IMAGETYPE_PNG => @imagepng($image, $targetPath, 6),
            IMAGETYPE_WEBP => @imagewebp($image, $targetPath, 92),
            IMAGETYPE_GIF => @imagegif($image, $targetPath),
            default => false,
        };

        imagedestroy($image);

        return $saved && is_file($targetPath);
    }

    private static function writeWebpImage(GdImage $source, string $targetPath, int $originalSize): bool
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $image = imagecreatetruecolor($width, $height);

        if (!$image) {
            return false;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, $width, $height, $transparent);
        imagecopy($image, $source, 0, 0, 0, 0, $width, $height);

        $qualities = [92, 88, 84];
        foreach ($qualities as $quality) {
            if (@imagewebp($image, $targetPath, $quality) && is_file($targetPath)) {
                clearstatcache(true, $targetPath);
                if ($originalSize <= 0 || filesize($targetPath) <= $originalSize || $quality === end($qualities)) {
                    imagedestroy($image);

                    return true;
                }
            }
        }

        imagedestroy($image);

        return false;
    }

    private static function filterPermissionRow(array $row, string $crudName, PermissionsMiddleware $middleware): array
    {
        $hadRestaurantId = array_key_exists('restaurant_id', $row);
        $filtered = $middleware->final_data(self::filterNestedPublicData($row, $middleware), $crudName);

        if (!$hadRestaurantId && array_key_exists('restaurant_id', $filtered)) {
            unset($filtered['restaurant_id']);
        }

        return $filtered;
    }

    private static function filterNestedPublicData(array $row, PermissionsMiddleware $middleware): array
    {
        if (isset($row['addons']) && is_array($row['addons'])) {
            $row['addons'] = $middleware->final_data($row['addons'], 'food_addons.get');
        }

        if (isset($row['id']) && !isset($row['restaurant_id'])) {
            $row['restaurant_id'] = $row['id'];
        }

        return $row;
    }

    private static function isListData(array $data): bool
    {
        return $data !== [] && array_keys($data) === range(0, count($data) - 1);
    }
}
