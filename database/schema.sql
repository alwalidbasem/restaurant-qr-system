CREATE TABLE restaurants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    active_until DATETIME NULL,
    manager_number VARCHAR(50) NOT NULL,
    txt_details TEXT NULL,
    main_code VARCHAR(100) NOT NULL
);


CREATE TABLE employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(255) NOT NULL,
    username text NOT NULL,
    password VARCHAR(255) NOT NULL,
    pfp VARCHAR(255) NULL,
    description TEXT NULL,

    role ENUM(
        'owner',
        'manager',
        'chef',
        'inventory_manager',
        'cashier',
        'delivery_manager'
    ) NOT NULL DEFAULT 'delivery_manager',

    
    permissions VARCHAR(255) DEFAULT "0,0,0,0,0,0,0,0,0,0,0,0,0,0",

    restaurant_id INT UNSIGNED NOT NULL,
    API_KEY VARCHAR(255),
    API_KEY_EXPIRY_DATE DATETIME,

    FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


CREATE TABLE inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,

    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,

    restaurant_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


CREATE TABLE menu_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,

    description_ar TEXT NULL,
    description_en TEXT NULL,

    restaurant_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


CREATE TABLE menu_foods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name_en VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,

    description_en TEXT NULL,
    description_ar TEXT NULL,

    image_url VARCHAR(255) NOT NULL,

    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,

    category_id INT UNSIGNED NOT NULL,
    restaurant_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (category_id)
        REFERENCES menu_categories(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


CREATE TABLE food_addons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,

    food_id INT UNSIGNED NOT NULL,

    extra_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    extra_profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,

    restaurant_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (food_id)
        REFERENCES menu_foods(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


CREATE TABLE tables (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    table_number INT UNSIGNED NOT NULL,

    table_status ENUM(
        'free',
        'waiting_order',
        'order_done'
    ) NOT NULL DEFAULT 'free',

    table_floor INT NOT NULL DEFAULT 1,

    position JSON NULL,

    order_id INT UNSIGNED NULL,

    restaurant_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    UNIQUE KEY unique_restaurant_table (
        restaurant_id,
        table_number
    )
);


CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_id INT UNSIGNED NOT NULL,

    status ENUM(
        'waiting',
        'canceled',
        'finished'
    ) NOT NULL DEFAULT 'waiting',
    extra_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    details TEXT NULL,
    session_order_key VARCHAR(255) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    restaurant_id INT UNSIGNED NOT NULL,

    FOREIGN KEY (table_id)
        REFERENCES tables(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE order_foods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    food_id INT UNSIGNED NOT NULL,
    table_id INT UNSIGNED NOT NULL,

    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    extra_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,

    details TEXT NULL,
    session_order_key VARCHAR(255) NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    restaurant_id INT UNSIGNED NOT NULL,


    FOREIGN KEY (order_id)
        REFERENCES orders(order_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (food_id)
        REFERENCES menu_foods(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (table_id)
        REFERENCES tables(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


ALTER TABLE tables
ADD CONSTRAINT fk_tables_order
FOREIGN KEY (order_id)
REFERENCES orders(order_id)
ON DELETE SET NULL
ON UPDATE CASCADE;
