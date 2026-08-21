CREATE TABLE menu_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,

    description_ar TEXT,
    description_en TEXT
);


CREATE TABLE menu_foods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name_en VARCHAR(255) NOT NULL,
    name_ar VARCHAR(255) NOT NULL,

    description_en TEXT,
    description_ar TEXT,
    image_url VARCHAR(255) NOT NULL,

    category_id INT UNSIGNED NOT NULL,


    FOREIGN KEY (category_id)
        REFERENCES menu_categories(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


CREATE TABLE food_addons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    name_ar VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,

    food_id INT UNSIGNED NOT NULL,

    extra_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,

    FOREIGN KEY (food_id)
        REFERENCES menu_foods(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);