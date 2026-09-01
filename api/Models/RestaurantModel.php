<?php

class Restaurant
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        $this->ensureBranchColumns();

        $stmt = $this->db->prepare("
            SELECT restaurants.*, settings.*, parent.name AS parent_restaurant_name
            FROM restaurants
            LEFT JOIN restaurant_website_settings settings
                ON settings.restaurant_id = restaurants.id
            LEFT JOIN restaurants parent
                ON parent.id = restaurants.parent_restaurant_id
            ORDER BY restaurants.id ASC
        ");

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $this->ensureBranchColumns();

        $stmt = $this->db->prepare("
            SELECT restaurants.*, settings.*, parent.name AS parent_restaurant_name
            FROM restaurants
            LEFT JOIN restaurant_website_settings settings
                ON settings.restaurant_id = restaurants.id
            LEFT JOIN restaurants parent
                ON parent.id = restaurants.parent_restaurant_id
            WHERE restaurants.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);

        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

        return $restaurant ?: null;
    }

    public function getByCode(string $code): ?array
    {
        $this->ensureBranchColumns();

        $stmt = $this->db->prepare("
            SELECT restaurants.*, settings.*, parent.name AS parent_restaurant_name
            FROM restaurants
            LEFT JOIN restaurant_website_settings settings
                ON settings.restaurant_id = restaurants.id
            LEFT JOIN restaurants parent
                ON parent.id = restaurants.parent_restaurant_id
            WHERE restaurants.main_code = :main_code
            LIMIT 1
        ");

        $stmt->execute([':main_code' => $code]);

        $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

        return $restaurant ?: null;
    }

    public function create(array $data): int
    {
        $this->ensureBranchColumns();
        $activeUntil = $data['active_until'] ?? $data['active_unitl'] ?? null;
        $parentRestaurantId = $data['parent_restaurant_id'] ?? null;
        $branchSettings = $this->encodeBranchSettings($data['branch_settings'] ?? null);

        if (isset($data['id'])) {
            $stmt = $this->db->prepare("
                INSERT INTO restaurants (
                    id,
                    name,
                    location,
                    active_until,
                    manager_number,
                    txt_details,
                    main_code,
                    parent_restaurant_id,
                    branch_management_enabled,
                    branch_limit,
                    branch_settings
                )
                VALUES (
                    :id,
                    :name,
                    :location,
                    :active_until,
                    :manager_number,
                    :txt_details,
                    :main_code,
                    :parent_restaurant_id,
                    :branch_management_enabled,
                    :branch_limit,
                    :branch_settings
                )
            ");

            $stmt->execute([
                ':id' => $data['id'],
                ':name' => $data['name'],
                ':location' => $data['location'],
                ':active_until' => $activeUntil,
                ':manager_number' => $data['manager_number'],
                ':txt_details' => $data['txt_details'],
                ':main_code' => $data['main_code'],
                ':parent_restaurant_id' => $parentRestaurantId,
                ':branch_management_enabled' => !empty($data['branch_management_enabled']) ? 1 : 0,
                ':branch_limit' => max(0, (int) ($data['branch_limit'] ?? 0)),
                ':branch_settings' => $branchSettings
            ]);

            return (int) $data['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO restaurants (
                name,
                location,
                active_until,
                manager_number,
                txt_details,
                main_code,
                parent_restaurant_id,
                branch_management_enabled,
                branch_limit,
                branch_settings
            )
            VALUES (
                :name,
                :location,
                :active_until,
                :manager_number,
                :txt_details,
                :main_code,
                :parent_restaurant_id,
                :branch_management_enabled,
                :branch_limit,
                :branch_settings
            )
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':location' => $data['location'],
            ':active_until' => $activeUntil,
            ':manager_number' => $data['manager_number'],
            ':txt_details' => $data['txt_details'],
            ':main_code' => $data['main_code'],
            ':parent_restaurant_id' => $parentRestaurantId,
            ':branch_management_enabled' => !empty($data['branch_management_enabled']) ? 1 : 0,
            ':branch_limit' => max(0, (int) ($data['branch_limit'] ?? 0)),
            ':branch_settings' => $branchSettings
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $this->ensureBranchColumns();
        $activeUntil = $data['active_until'] ?? $data['active_unitl'] ?? null;
        $parentRestaurantId = $data['parent_restaurant_id'] ?? null;

        $stmt = $this->db->prepare("
            UPDATE restaurants
            SET
                name = :name,
                location = :location,
                active_until = :active_until,
                manager_number = :manager_number,
                txt_details = :txt_details,
                main_code = :main_code,
                parent_restaurant_id = :parent_restaurant_id,
                branch_management_enabled = :branch_management_enabled,
                branch_limit = :branch_limit,
                branch_settings = :branch_settings
            WHERE id = :id
        ");

        return $stmt->execute([
            ':name' => $data['name'],
            ':location' => $data['location'],
            ':active_until' => $activeUntil,
            ':manager_number' => $data['manager_number'],
            ':txt_details' => $data['txt_details'],
            ':main_code' => $data['main_code'],
            ':parent_restaurant_id' => $parentRestaurantId,
            ':branch_management_enabled' => !empty($data['branch_management_enabled']) ? 1 : 0,
            ':branch_limit' => max(0, (int) ($data['branch_limit'] ?? 0)),
            ':branch_settings' => $this->encodeBranchSettings($data['branch_settings'] ?? null),
            ':id' => $id
        ]);
    }

    public function getBranches(int $parentRestaurantId): array
    {
        $this->ensureBranchColumns();

        $stmt = $this->db->prepare("
            SELECT restaurants.*, settings.*
            FROM restaurants
            LEFT JOIN restaurant_website_settings settings
                ON settings.restaurant_id = restaurants.id
            WHERE restaurants.parent_restaurant_id = :parent_restaurant_id
            ORDER BY restaurants.name ASC
        ");
        $stmt->execute([':parent_restaurant_id' => $parentRestaurantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function branchCount(int $parentRestaurantId, ?int $excludeId = null): int
    {
        $this->ensureBranchColumns();

        $where = "parent_restaurant_id = :parent_restaurant_id";
        $params = [':parent_restaurant_id' => $parentRestaurantId];
        if ($excludeId !== null) {
            $where .= " AND id <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM restaurants WHERE $where");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function branchDashboard(int $parentRestaurantId): array
    {
        $this->ensureBranchColumns();

        $stmt = $this->db->prepare("
            SELECT
                branches.id,
                branches.name,
                branches.location,
                branches.manager_number,
                branches.branch_settings,
                COALESCE(profits.profit_without_salary, 0) AS profit_without_salary,
                COALESCE(salaries.total_salary, 0) AS salary_total,
                COALESCE(waste.inventory_loss, 0) AS inventory_loss
            FROM restaurants branches
            LEFT JOIN (
                SELECT restaurant_id, COALESCE(SUM(profit), 0) AS profit_without_salary
                FROM orders
                GROUP BY restaurant_id
            ) profits
                ON profits.restaurant_id = branches.id
            LEFT JOIN (
                SELECT branch_id, COALESCE(SUM(salary), 0) AS total_salary
                FROM staff
                WHERE branch_id IS NOT NULL
                GROUP BY branch_id
            ) salaries
                ON salaries.branch_id = branches.id
            LEFT JOIN (
                SELECT restaurant_id, COALESCE(SUM(ABS(quantity_change)), 0) AS inventory_loss
                FROM inventory_movements
                WHERE movement_type = 'waste'
                GROUP BY restaurant_id
            ) waste
                ON waste.restaurant_id = branches.id
            WHERE branches.parent_restaurant_id = :parent_restaurant_id
            ORDER BY profit_without_salary DESC
        ");
        $stmt->execute([':parent_restaurant_id' => $parentRestaurantId]);

        $branches = array_map(function (array $row): array {
            $row['profit_without_salary'] = (float) $row['profit_without_salary'];
            $row['salary_total'] = (float) $row['salary_total'];
            $row['profit_with_salary'] = $row['profit_without_salary'] - $row['salary_total'];
            $row['inventory_loss'] = (float) $row['inventory_loss'];

            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'branches' => $branches,
            'highest_profitability' => $branches[0] ?? null,
            'highest_inventory_losses' => $this->topBy($branches, 'inventory_loss', true),
            'lowest_inventory_losses' => $this->topBy($branches, 'inventory_loss', false),
        ];
    }

    public function updateWebsiteSettings(int $restaurantId, array $data): bool
    {
        $this->ensureWebsiteSettingsColumns();

        $fields = [
            'brand_name_en',
            'brand_name_ar',
            'hero_title_en',
            'hero_title_ar',
            'hero_accent_en',
            'hero_accent_ar',
            'hero_description_en',
            'hero_description_ar',
            'hero_eyebrow_en',
            'hero_eyebrow_ar',
            'menu_title_en',
            'menu_title_ar',
            'menu_subtitle_en',
            'menu_subtitle_ar',
            'logo_image_url',
            'hero_image_url',
            'takeaway_enabled',
            'primary_color',
            'accent_color',
            'background_color',
            'background_alt_color',
            'surface_color',
            'surface_raised_color',
            'border_color',
            'text_color',
            'text_muted_color',
            'text_faint_color',
            'accent_dark_color',
            'accent_soft_color',
            'ember_color',
            'success_color',
            'danger_color',
            'website_colors',
        ];

        $updates = implode(",\n                ", array_map(
            static fn (string $field): string => $field . ' = VALUES(' . $field . ')',
            $fields
        ));

        $stmt = $this->db->prepare("
            INSERT INTO restaurant_website_settings (
                restaurant_id,
                " . implode(",\n                ", $fields) . "
            )
            VALUES (
                :restaurant_id,
                " . implode(",\n                ", array_map(static fn (string $field): string => ':' . $field, $fields)) . "
            )
            ON DUPLICATE KEY UPDATE
                $updates
        ");

        $params = [':restaurant_id' => $restaurantId];
        $htmlFields = [
            'brand_name_en' => true,
            'brand_name_ar' => true,
            'hero_title_en' => true,
            'hero_title_ar' => true,
            'hero_accent_en' => true,
            'hero_accent_ar' => true,
            'hero_description_en' => true,
            'hero_description_ar' => true,
            'hero_eyebrow_en' => true,
            'hero_eyebrow_ar' => true,
            'menu_title_en' => true,
            'menu_title_ar' => true,
            'menu_subtitle_en' => true,
            'menu_subtitle_ar' => true,
        ];

        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if (isset($htmlFields[$field]) && is_string($value)) {
                $value = $this->sanitizeWebsiteHtml($value);
            }
            if ($field === 'takeaway_enabled') {
                $params[':' . $field] = !empty($value) ? 1 : 0;
                continue;
            }
            if ($field === 'website_colors') {
                $params[':' . $field] = $this->sanitizeWebsiteColors($value);
                continue;
            }
            if (str_ends_with($field, '_color')) {
                $value = $this->sanitizeWebsiteColor((string) ($value ?? ''));
            }
            $params[':' . $field] = is_string($value) && trim($value) === '' ? null : $value;
        }

        return $stmt->execute($params);
    }

    private function ensureWebsiteSettingsColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $checked = true;
        $columns = $this->db
            ->query("SHOW COLUMNS FROM restaurant_website_settings")
            ->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('website_colors', $columns, true)) {
            $this->db->exec("ALTER TABLE restaurant_website_settings ADD COLUMN website_colors LONGTEXT NULL AFTER danger_color");
        }
    }

    private function allowedWebsiteColorVariables(): array
    {
        return [
            '--color-bg' => true,
            '--color-bg-alt' => true,
            '--color-surface' => true,
            '--color-surface-raised' => true,
            '--color-border' => true,
            '--color-text' => true,
            '--color-text-muted' => true,
            '--color-text-faint' => true,
            '--color-accent' => true,
            '--color-accent-dark' => true,
            '--color-accent-soft' => true,
            '--color-ember' => true,
            '--color-gold' => true,
            '--color-success' => true,
            '--color-danger' => true,
            '--color-danger-strong' => true,
            '--color-scrollbar-accent' => true,
            '--color-on-accent' => true,
            '--color-on-success' => true,
            '--color-transparent' => true,
            '--overlay-hero-vertical' => true,
            '--overlay-hero-horizontal' => true,
            '--overlay-status-cover' => true,
            '--overlay-modal-backdrop' => true,
            '--overlay-drawer-backdrop' => true,
            '--overlay-language-backdrop' => true,
            '--overlay-navbar-bg' => true,
            '--overlay-navbar-mobile-bg' => true,
            '--overlay-food-badge-bg' => true,
            '--overlay-panel-bg' => true,
            '--overlay-panel-strong-bg' => true,
            '--overlay-footer-bg' => true,
            '--overlay-footer-strong-bg' => true,
            '--overlay-footer-mobile-bg' => true,
            '--overlay-control-bg' => true,
            '--overlay-control-bg-mid' => true,
            '--overlay-control-bg-focus' => true,
            '--overlay-control-bg-dark' => true,
            '--overlay-control-bg-darker' => true,
            '--overlay-media-bg' => true,
            '--overlay-qty-bg' => true,
            '--overlay-check-inset' => true,
            '--overlay-bill-bg' => true,
            '--overlay-meal-group-bg' => true,
            '--tint-text-045' => true,
            '--tint-text-05' => true,
            '--tint-text-055' => true,
            '--tint-text-06' => true,
            '--tint-text-07' => true,
            '--tint-text-08' => true,
            '--tint-text-09' => true,
            '--tint-text-12' => true,
            '--tint-text-13' => true,
            '--tint-text-14' => true,
            '--tint-text-16' => true,
            '--tint-text-18' => true,
            '--tint-text-20' => true,
            '--tint-text-22' => true,
            '--tint-text-24' => true,
            '--tint-text-28' => true,
            '--tint-text-34' => true,
            '--tint-text-40' => true,
            '--tint-text-50' => true,
            '--tint-text-64' => true,
            '--tint-accent-10' => true,
            '--tint-accent-12' => true,
            '--tint-accent-13' => true,
            '--tint-accent-18' => true,
            '--tint-accent-28' => true,
            '--tint-accent-36' => true,
            '--tint-accent-45' => true,
            '--tint-accent-50' => true,
            '--tint-accent-55' => true,
            '--tint-accent-60' => true,
            '--tint-accent-65' => true,
            '--tint-accent-70' => true,
            '--tint-gold-12' => true,
            '--tint-gold-28' => true,
            '--tint-danger-20' => true,
            '--tint-success-20' => true,
            '--tint-success-48' => true,
            '--glow-success' => true,
            '--shadow-card-hover' => true,
            '--shadow-panel' => true,
            '--shadow-panel-soft' => true,
            '--shadow-panel-strong' => true,
            '--shadow-control' => true,
            '--shadow-toast' => true,
        ];
    }

    private function sanitizeWebsiteColors(mixed $value): ?string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return null;
        }

        $allowed = $this->allowedWebsiteColorVariables();
        $clean = [];
        foreach ($value as $variable => $token) {
            $variable = trim((string) $variable);
            if (!isset($allowed[$variable])) {
                continue;
            }

            $token = $this->sanitizeWebsiteCssColorToken((string) $token);
            if ($token !== null) {
                $clean[$variable] = $token;
            }
        }

        return $clean === [] ? null : json_encode($clean, JSON_UNESCAPED_SLASHES);
    }

    private function sanitizeWebsiteCssColorToken(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtoupper($value);
        }

        if (preg_match('/^transparent$/i', $value)) {
            return 'transparent';
        }

        if (preg_match('/^rgba?\(\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value)) {
            return $value;
        }

        if (preg_match('/^linear-gradient\([#a-zA-Z0-9\s,().%-]+\)$/', $value)) {
            return $value;
        }

        return null;
    }

    private function sanitizeWebsiteColor(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtoupper($value);
        }

        if (preg_match('/^rgba\(\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:0|1|0?\.\d+)\s*\)$/i', $value)) {
            return $value;
        }

        return null;
    }

    private function sanitizeWebsiteHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div>' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $allowedTags = [
            'div' => true,
            'p' => true,
            'br' => true,
            'strong' => true,
            'b' => true,
            'em' => true,
            'i' => true,
            'u' => true,
            'span' => true,
            'small' => true,
            'ul' => true,
            'ol' => true,
            'li' => true,
            'a' => true,
        ];

        $nodes = [];
        foreach ($dom->getElementsByTagName('*') as $node) {
            $nodes[] = $node;
        }

        foreach ($nodes as $node) {
            $tag = strtolower($node->nodeName);
            if (!isset($allowedTags[$tag])) {
                $node->parentNode?->removeChild($node);
                continue;
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
                $isSafeHref = $name === 'href'
                    && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/') || str_starts_with($value, '#'));

                if ($tag === 'a' && $isSafeHref) {
                    $node->setAttribute('rel', 'noopener noreferrer');
                    continue;
                }

                $node->removeAttribute($attribute->name);
            }
        }

        $root = $dom->documentElement;
        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM restaurants
            WHERE id = :id
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }

    private function ensureBranchColumns(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $checked = true;
        $columns = $this->db
            ->query("SHOW COLUMNS FROM restaurants")
            ->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('parent_restaurant_id', $columns, true)) {
            $this->db->exec("ALTER TABLE restaurants ADD COLUMN parent_restaurant_id INT UNSIGNED NULL AFTER main_code");
        }

        if (!in_array('branch_management_enabled', $columns, true)) {
            $this->db->exec("ALTER TABLE restaurants ADD COLUMN branch_management_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER parent_restaurant_id");
        }

        if (!in_array('branch_limit', $columns, true)) {
            $this->db->exec("ALTER TABLE restaurants ADD COLUMN branch_limit INT UNSIGNED NOT NULL DEFAULT 0 AFTER branch_management_enabled");
        }

        if (!in_array('branch_settings', $columns, true)) {
            $this->db->exec("ALTER TABLE restaurants ADD COLUMN branch_settings JSON NULL AFTER branch_limit");
        }
    }

    private function encodeBranchSettings(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            $decoded = json_decode($trimmed, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? json_encode($decoded, JSON_UNESCAPED_SLASHES)
                : json_encode(['notes' => $trimmed], JSON_UNESCAPED_SLASHES);
        }

        return is_array($value) && $value !== []
            ? json_encode($value, JSON_UNESCAPED_SLASHES)
            : null;
    }

    private function topBy(array $branches, string $field, bool $descending): ?array
    {
        if ($branches === []) {
            return null;
        }

        usort($branches, function (array $a, array $b) use ($field, $descending): int {
            $result = ((float) ($a[$field] ?? 0)) <=> ((float) ($b[$field] ?? 0));

            return $descending ? -$result : $result;
        });

        return $branches[0] ?? null;
    }
}
