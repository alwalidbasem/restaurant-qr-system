SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS invoice_submission_attempts;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS invoice_counters;
DROP TABLE IF EXISTS restaurant_tax_settings;
DROP TABLE IF EXISTS restaurant_website_settings;
DROP TABLE IF EXISTS inventory_movements;
DROP TABLE IF EXISTS inventory_food_links;
DROP TABLE IF EXISTS order_foods;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS tables;
DROP TABLE IF EXISTS discounts;
DROP TABLE IF EXISTS food_addons;
DROP TABLE IF EXISTS menu_foods;
DROP TABLE IF EXISTS menu_categories;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS restaurants;
DROP TABLE IF EXISTS migrations;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE restaurants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    active_until DATETIME NULL,
    manager_number VARCHAR(50) NOT NULL,
    txt_details TEXT NULL,
    main_code VARCHAR(100) NOT NULL UNIQUE,
    parent_restaurant_id INT UNSIGNED NULL,
    branch_management_enabled TINYINT(1) NOT NULL DEFAULT 0,
    branch_limit INT UNSIGNED NOT NULL DEFAULT 0,
    branch_settings JSON NULL,
    INDEX idx_restaurants_parent (parent_restaurant_id),
    FOREIGN KEY (parent_restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE staff (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    pfp VARCHAR(255) NULL,
    details TEXT NULL,
    hidden_details TEXT NULL,
    salary DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    branch_id INT UNSIGNED NULL,
    is_superadmin TINYINT(1) NOT NULL DEFAULT 0,
    is_owner TINYINT(1) NOT NULL DEFAULT 0,
    is_manager TINYINT(1) NOT NULL DEFAULT 0,
    is_employee TINYINT(1) NOT NULL DEFAULT 1,
    allowed_branches VARCHAR(500) NULL,
    manager_scope ENUM('all', 'some', 'none') NULL,
    managed_branches VARCHAR(500) NULL,
    phone VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    permissions VARCHAR(500) DEFAULT '0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0',
    restaurant_id INT UNSIGNED NOT NULL,
    API_KEY VARCHAR(255) NULL,
    API_KEY_EXPIRY_DATE DATETIME NULL,
    UNIQUE KEY unique_staff_username_restaurant (restaurant_id, username),
    FOREIGN KEY (branch_id) REFERENCES restaurants(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE menu_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    description_ar TEXT NULL,
    description_en TEXT NULL,
    restaurant_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE menu_foods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_en VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,
    description_en TEXT NULL,
    description_ar TEXT NULL,
    image_url VARCHAR(500) NOT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    tax_category VARCHAR(20) NOT NULL DEFAULT 'default',
    tax_rate DECIMAL(5, 3) NULL,
    special_tax_amount DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
    tax_exempt TINYINT(1) NOT NULL DEFAULT 0,
    note_enabled TINYINT(1) NOT NULL DEFAULT 0,
    category_id INT UNSIGNED NOT NULL,
    restaurant_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE food_addons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    food_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    extra_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    extra_profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    restaurant_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (food_id) REFERENCES menu_foods(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE discounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    discount_value DECIMAL(10, 3) NOT NULL DEFAULT 0.000,
    target_type ENUM('food', 'category', 'addon', 'full_menu_with_addons', 'full_menu_without_addons') NOT NULL,
    target_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    restaurant_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_discounts_restaurant (restaurant_id),
    INDEX idx_discounts_target (target_type, target_id),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_number INT UNSIGNED NOT NULL,
    table_status ENUM('free', 'waiting_order', 'order_done') NOT NULL DEFAULT 'free',
    table_floor INT NOT NULL DEFAULT 1,
    position JSON NULL,
    order_id INT UNSIGNED NULL,
    restaurant_id INT UNSIGNED NOT NULL,
    UNIQUE KEY unique_restaurant_table (restaurant_id, table_number),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_id INT UNSIGNED NULL,
    order_type ENUM('table', 'takeaway') NOT NULL DEFAULT 'table',
    status ENUM('waiting', 'canceled', 'finished') NOT NULL DEFAULT 'waiting',
    payment_status ENUM('unpaid', 'paid') NOT NULL DEFAULT 'unpaid',
    payment_method ENUM('cash', 'credit', 'cash_credit') NULL,
    total_paid_cash DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_paid_credit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    extra_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    details TEXT NULL,
    session_order_key VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    restaurant_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE order_foods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    food_id INT UNSIGNED NOT NULL,
    qty INT UNSIGNED NOT NULL DEFAULT 1,
    addon_id JSON NULL,
    status ENUM('waiting', 'finished', 'canceled') NOT NULL DEFAULT 'waiting',
    table_id INT UNSIGNED NULL,
    order_type ENUM('table', 'takeaway') NOT NULL DEFAULT 'table',
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    extra_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    details TEXT NULL,
    session_order_key VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    restaurant_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (food_id) REFERENCES menu_foods(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

ALTER TABLE tables
ADD CONSTRAINT fk_tables_order
FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    quantity DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    unit ENUM('pcs', 'kgs', 'liters') NOT NULL DEFAULT 'pcs',
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    restaurant_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE inventory_food_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT UNSIGNED NOT NULL,
    food_id INT UNSIGNED NOT NULL,
    addon_id INT UNSIGNED NULL,
    quantity_per_item DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    restaurant_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (food_id) REFERENCES menu_foods(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (addon_id) REFERENCES food_addons(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE inventory_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NULL,
    order_food_id INT UNSIGNED NULL,
    movement_type ENUM('purchase', 'consume', 'return', 'waste', 'adjustment') NOT NULL DEFAULT 'adjustment',
    quantity_change DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    restaurant_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (order_food_id) REFERENCES order_foods(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE restaurant_tax_settings (
    restaurant_id INT UNSIGNED PRIMARY KEY,
    einvoicing_enabled TINYINT(1) NOT NULL DEFAULT 0,
    taxpayer_type ENUM('income_tax_only', 'general_sales_tax', 'special_sales_tax') NOT NULL DEFAULT 'income_tax_only',
    legal_seller_name VARCHAR(255) NULL,
    trade_name VARCHAR(255) NULL,
    seller_address VARCHAR(255) NULL,
    seller_city VARCHAR(100) NULL,
    seller_postal_code VARCHAR(30) NULL,
    seller_phone VARCHAR(50) NULL,
    seller_national_number VARCHAR(50) NULL,
    seller_tax_number VARCHAR(50) NULL,
    income_source_sequence VARCHAR(100) NULL,
    jofotara_client_id_encrypted TEXT NULL,
    jofotara_secret_key_encrypted TEXT NULL,
    default_tax_rate DECIMAL(5, 3) NOT NULL DEFAULT 0.000,
    prices_include_tax TINYINT(1) NOT NULL DEFAULT 1,
    invoice_prefix VARCHAR(30) NOT NULL DEFAULT 'INV',
    automatic_submission TINYINT(1) NOT NULL DEFAULT 1,
    print_after_accepted TINYINT(1) NOT NULL DEFAULT 0,
    invoice_print_full_page TINYINT(1) NOT NULL DEFAULT 0,
    invoice_print_width_mm DECIMAL(6, 2) NOT NULL DEFAULT 80.00,
    invoice_print_height_mm DECIMAL(6, 2) NOT NULL DEFAULT 297.00,
    configuration_status ENUM('not_configured', 'configured', 'active', 'configuration_error') NOT NULL DEFAULT 'not_configured',
    configuration_errors TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE invoice_counters (
    restaurant_id INT UNSIGNED PRIMARY KEY,
    next_number INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    local_invoice_number VARCHAR(80) NOT NULL,
    invoice_uuid VARCHAR(80) NOT NULL,
    electronic_invoice_number VARCHAR(120) NULL,
    invoice_type VARCHAR(40) NOT NULL,
    taxpayer_type VARCHAR(40) NOT NULL,
    payment_type VARCHAR(40) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'JOD',
    subtotal DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    discount_total DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    taxable_amount DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    tax_total DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    grand_total DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    seller_name VARCHAR(255) NOT NULL,
    seller_trade_name VARCHAR(255) NULL,
    seller_address VARCHAR(255) NULL,
    seller_phone VARCHAR(50) NULL,
    seller_tax_number VARCHAR(50) NULL,
    seller_national_number VARCHAR(50) NULL,
    seller_income_source_sequence VARCHAR(100) NULL,
    buyer_name VARCHAR(255) NULL,
    buyer_identification_type VARCHAR(20) NULL,
    buyer_identification_number VARCHAR(80) NULL,
    buyer_phone VARCHAR(50) NULL,
    buyer_postal_code VARCHAR(30) NULL,
    buyer_address VARCHAR(255) NULL,
    jofotara_submission_status ENUM('draft', 'ready', 'submitting', 'accepted', 'rejected', 'retry_pending', 'disabled') NOT NULL DEFAULT 'draft',
    jofotara_response_status VARCHAR(80) NULL,
    jofotara_qr_value TEXT NULL,
    jofotara_returned_xml MEDIUMTEXT NULL,
    local_generated_xml MEDIUMTEXT NULL,
    error_code VARCHAR(80) NULL,
    error_message TEXT NULL,
    submission_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    original_invoice_id INT UNSIGNED NULL,
    return_reason VARCHAR(255) NULL,
    issued_at DATETIME NOT NULL,
    submitted_at DATETIME NULL,
    accepted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_invoice_order (restaurant_id, order_id),
    UNIQUE KEY unique_local_invoice (restaurant_id, local_invoice_number),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (original_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    source_food_id INT UNSIGNED NULL,
    source_order_item_id INT UNSIGNED NULL,
    item_code VARCHAR(80) NULL,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(12, 3) NOT NULL DEFAULT 1.000,
    unit_price DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    discount DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    price_after_discount DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    tax_category VARCHAR(20) NOT NULL DEFAULT 'Z',
    tax_rate DECIMAL(5, 3) NOT NULL DEFAULT 0.000,
    special_tax DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    tax_amount DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    line_total DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (source_food_id) REFERENCES menu_foods(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (source_order_item_id) REFERENCES order_foods(id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE invoice_submission_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(40) NOT NULL,
    http_status INT NULL,
    jofotara_error_code VARCHAR(80) NULL,
    sanitized_response MEDIUMTEXT NULL,
    retry_number INT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE restaurant_website_settings (
    restaurant_id INT UNSIGNED PRIMARY KEY,
    brand_name_en TEXT NULL,
    brand_name_ar TEXT NULL,
    hero_title_en TEXT NULL,
    hero_title_ar TEXT NULL,
    hero_accent_en TEXT NULL,
    hero_accent_ar TEXT NULL,
    hero_description_en TEXT NULL,
    hero_description_ar TEXT NULL,
    hero_eyebrow_en TEXT NULL,
    hero_eyebrow_ar TEXT NULL,
    menu_title_en TEXT NULL,
    menu_title_ar TEXT NULL,
    menu_subtitle_en TEXT NULL,
    menu_subtitle_ar TEXT NULL,
    logo_image_url VARCHAR(500) NULL,
    hero_image_url VARCHAR(500) NULL,
    takeaway_enabled TINYINT(1) NOT NULL DEFAULT 0,
    primary_color VARCHAR(20) NULL,
    accent_color VARCHAR(20) NULL,
    background_color VARCHAR(20) NULL,
    background_alt_color VARCHAR(20) NULL,
    surface_color VARCHAR(20) NULL,
    surface_raised_color VARCHAR(20) NULL,
    border_color VARCHAR(20) NULL,
    text_color VARCHAR(20) NULL,
    text_muted_color VARCHAR(20) NULL,
    text_faint_color VARCHAR(20) NULL,
    accent_dark_color VARCHAR(20) NULL,
    accent_soft_color VARCHAR(80) NULL,
    ember_color VARCHAR(20) NULL,
    success_color VARCHAR(20) NULL,
    danger_color VARCHAR(20) NULL,
    website_colors LONGTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NOT NULL DEFAULT 0,
    employee_id INT UNSIGNED NULL,
    employee_name VARCHAR(255) NULL,
    permission_key VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id VARCHAR(80) NULL,
    action_label VARCHAR(255) NOT NULL,
    message VARCHAR(500) NOT NULL,
    metadata JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_logs_restaurant_created (restaurant_id, created_at),
    INDEX idx_activity_logs_branch_created (branch_id, created_at),
    INDEX idx_activity_logs_permission (permission_key),
    INDEX idx_activity_logs_employee (employee_id),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES staff(id) ON DELETE SET NULL ON UPDATE CASCADE
);
