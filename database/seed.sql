-- =========================================
-- MENU CATEGORIES
-- =========================================

INSERT INTO menu_categories (
    name_ar,
    name_en,
    description_ar,
    description_en
) VALUES
('برغر', 'Burgers', 'مجموعة متنوعة من البرغر', 'A variety of burgers'),
('بيتزا', 'Pizza', 'بيتزا طازجة بنكهات مختلفة', 'Fresh pizza with different flavors'),
('وجبات رئيسية', 'Main Meals', 'وجبات رئيسية متنوعة', 'A variety of main meals'),
('مقبلات', 'Appetizers', 'مقبلات ووجبات جانبية', 'Appetizers and side dishes'),
('مشروبات', 'Drinks', 'مشروبات باردة ومنعشة', 'Cold and refreshing drinks');


-- =========================================
-- MENU FOODS
-- =========================================

INSERT INTO menu_foods (
    name_en,
    name_ar,
    description_en,
    description_ar,
    image_url,
    price,
    category_id
) VALUES

-- Burgers
(
    'Classic Beef Burger',
    'برغر لحم كلاسيك',
    'Grilled beef patty with lettuce, tomato and special sauce',
    'قطعة لحم مشوية مع خس وطماطم وصوص خاص',
    'https://images.unsplash.com/photo-1568901346375-23c9450c58cd',
    4.50,
    1
),
(
    'Cheese Burger',
    'تشيز برغر',
    'Beef burger with melted cheddar cheese',
    'برغر لحم مع جبنة شيدر مذابة',
    'https://images.unsplash.com/photo-1550547660-d9450f859349',
    5.00,
    1
),
(
    'Double Beef Burger',
    'دبل برغر لحم',
    'Two beef patties with cheese and special sauce',
    'قطعتان لحم مع الجبنة والصوص الخاص',
    'https://images.unsplash.com/photo-1594212699903-ec8a3eca50f5',
    6.50,
    1
),
(
    'Crispy Chicken Burger',
    'برغر دجاج كرسبي',
    'Crispy chicken with lettuce and mayonnaise',
    'دجاج مقرمش مع الخس والمايونيز',
    'https://images.unsplash.com/photo-1615297928064-24977384d0da',
    4.75,
    1
),
(
    'Spicy Chicken Burger',
    'برغر دجاج حار',
    'Spicy crispy chicken with jalapeno',
    'دجاج مقرمش حار مع الهالبينو',
    'https://images.unsplash.com/photo-1606755962773-d324e0a13086',
    5.25,
    1
),
(
    'Mushroom Burger',
    'برغر مشروم',
    'Beef burger with mushrooms and cheese',
    'برغر لحم مع الفطر والجبنة',
    'https://images.unsplash.com/photo-1571091718767-18b5b1457add',
    5.50,
    1
),

-- Pizza
(
    'Margherita Pizza',
    'بيتزا مارغريتا',
    'Tomato sauce, mozzarella and basil',
    'صوص طماطم وموزاريلا وريحان',
    'https://images.unsplash.com/photo-1574071318508-1cdbab80d002',
    6.00,
    2
),
(
    'Pepperoni Pizza',
    'بيتزا بيبروني',
    'Mozzarella with pepperoni slices',
    'موزاريلا مع شرائح البيبروني',
    'https://images.unsplash.com/photo-1628840042765-356cda07504e',
    7.00,
    2
),
(
    'BBQ Chicken Pizza',
    'بيتزا دجاج باربكيو',
    'Chicken, BBQ sauce and mozzarella',
    'دجاج وصوص باربكيو وموزاريلا',
    'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38',
    7.50,
    2
),
(
    'Vegetable Pizza',
    'بيتزا خضار',
    'Peppers, mushrooms, olives and onions',
    'فلفل وفطر وزيتون وبصل',
    'https://images.unsplash.com/photo-1579751626657-72bc17010498',
    6.50,
    2
),
(
    'Four Cheese Pizza',
    'بيتزا أربع أجبان',
    'Pizza with four types of cheese',
    'بيتزا بأربعة أنواع من الجبن',
    'https://images.unsplash.com/photo-1594007654729-407eedc4be65',
    7.25,
    2
),
(
    'Meat Lovers Pizza',
    'بيتزا عشاق اللحوم',
    'Beef, pepperoni and sausage',
    'لحم وبيبروني ونقانق',
    'https://images.unsplash.com/photo-1566843972142-a7fcb70de55a',
    8.00,
    2
),

-- Main Meals
(
    'Grilled Chicken',
    'دجاج مشوي',
    'Grilled chicken served with rice and vegetables',
    'دجاج مشوي مع الأرز والخضار',
    'https://images.unsplash.com/photo-1532550907401-a500c9a57435',
    7.00,
    3
),
(
    'Chicken Steak',
    'ستيك دجاج',
    'Seasoned chicken steak served with fries',
    'ستيك دجاج متبل مع البطاطا',
    'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d',
    7.50,
    3
),
(
    'Beef Steak',
    'ستيك لحم',
    'Grilled beef steak served with potatoes',
    'ستيك لحم مشوي يقدم مع البطاطا',
    'https://images.unsplash.com/photo-1546833999-b9f581a1996d',
    12.00,
    3
),
(
    'Chicken Alfredo',
    'دجاج ألفريدو',
    'Creamy Alfredo pasta with grilled chicken',
    'باستا ألفريدو مع الدجاج المشوي',
    'https://images.unsplash.com/photo-1645112411341-6c4fd023714a',
    7.50,
    3
),
(
    'Spaghetti Bolognese',
    'سباغيتي بولونيز',
    'Spaghetti with beef tomato sauce',
    'سباغيتي مع صوص الطماطم واللحم',
    'https://images.unsplash.com/photo-1622973536968-3ead9e780960',
    6.75,
    3
),
(
    'Chicken Rice Bowl',
    'وجبة أرز بالدجاج',
    'Chicken served over seasoned rice',
    'دجاج يقدم فوق أرز متبل',
    'https://images.unsplash.com/photo-1547592180-85f173990554',
    6.50,
    3
),

-- Appetizers
(
    'French Fries',
    'بطاطا مقلية',
    'Golden crispy french fries',
    'بطاطا مقلية ذهبية ومقرمشة',
    'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d',
    2.00,
    4
),
(
    'Cheese Fries',
    'بطاطا بالجبنة',
    'French fries topped with cheese sauce',
    'بطاطا مقلية مع صوص الجبنة',
    'https://images.unsplash.com/photo-1573080496219-bb080dd4f877',
    3.00,
    4
),
(
    'Onion Rings',
    'حلقات البصل',
    'Crispy onion rings',
    'حلقات بصل مقرمشة',
    'https://images.unsplash.com/photo-1639024471283-03518883512d',
    2.75,
    4
),
(
    'Chicken Wings',
    'أجنحة دجاج',
    'Crispy chicken wings with sauce',
    'أجنحة دجاج مقرمشة مع الصوص',
    'https://images.unsplash.com/photo-1527477396000-e27163b481c2',
    4.50,
    4
),
(
    'Mozzarella Sticks',
    'أصابع موزاريلا',
    'Fried mozzarella sticks',
    'أصابع موزاريلا مقلية',
    'https://images.unsplash.com/photo-1548340748-6d2b7d7da280',
    3.50,
    4
),
(
    'Chicken Nuggets',
    'ناجتس دجاج',
    'Crispy chicken nuggets',
    'قطع ناجتس دجاج مقرمشة',
    'https://images.unsplash.com/photo-1562967914-608f82629710',
    3.75,
    4
),

-- Drinks
(
    'Coca Cola',
    'كوكا كولا',
    'Cold Coca Cola drink',
    'مشروب كوكا كولا بارد',
    'https://images.unsplash.com/photo-1629203851122-3726ecdf080e',
    1.00,
    5
),
(
    'Sprite',
    'سبرايت',
    'Cold lemon lime soft drink',
    'مشروب غازي بنكهة الليمون',
    'https://images.unsplash.com/photo-1624517452488-04869289c4ca',
    1.00,
    5
),
(
    'Orange Juice',
    'عصير برتقال',
    'Fresh orange juice',
    'عصير برتقال طازج',
    'https://images.unsplash.com/photo-1600271886742-f049cd451bba',
    2.00,
    5
),
(
    'Lemon Mint',
    'ليمون ونعنع',
    'Fresh lemon mint juice',
    'عصير ليمون ونعنع طازج',
    'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd',
    2.50,
    5
),
(
    'Iced Tea',
    'شاي مثلج',
    'Refreshing iced tea',
    'شاي مثلج ومنعش',
    'https://images.unsplash.com/photo-1556679343-c7306c1976bc',
    2.00,
    5
),
(
    'Mineral Water',
    'مياه معدنية',
    'Cold mineral water',
    'مياه معدنية باردة',
    'https://images.unsplash.com/photo-1523362628745-0c100150b504',
    0.75,
    5
);


-- =========================================
-- FOOD ADDONS
-- =========================================

INSERT INTO food_addons (
    name_ar,
    name_en,
    food_id,
    extra_price
) VALUES

-- Burger addons
('جبنة إضافية', 'Extra Cheese', 1, 0.50),
('قطعة لحم إضافية', 'Extra Beef Patty', 1, 1.50),
('صوص إضافي', 'Extra Sauce', 1, 0.25),

('جبنة إضافية', 'Extra Cheese', 2, 0.50),
('قطعة لحم إضافية', 'Extra Beef Patty', 2, 1.50),

('جبنة إضافية', 'Extra Cheese', 3, 0.50),
('قطعة لحم إضافية', 'Extra Beef Patty', 3, 1.50),

('جبنة إضافية', 'Extra Cheese', 4, 0.50),
('قطعة دجاج إضافية', 'Extra Chicken', 4, 1.25),

('هالبينو إضافي', 'Extra Jalapeno', 5, 0.30),
('صوص حار إضافي', 'Extra Spicy Sauce', 5, 0.25),

('مشروم إضافي', 'Extra Mushroom', 6, 0.75),
('جبنة إضافية', 'Extra Cheese', 6, 0.50),

-- Pizza addons
('جبنة إضافية', 'Extra Cheese', 7, 1.00),
('زيتون', 'Olives', 7, 0.50),

('بيبروني إضافي', 'Extra Pepperoni', 8, 1.00),
('جبنة إضافية', 'Extra Cheese', 8, 1.00),

('دجاج إضافي', 'Extra Chicken', 9, 1.50),
('صوص باربكيو', 'Extra BBQ Sauce', 9, 0.50),

('مشروم إضافي', 'Extra Mushroom', 10, 0.50),
('زيتون إضافي', 'Extra Olives', 10, 0.50),

('جبنة إضافية', 'Extra Cheese', 11, 1.00),

('لحم إضافي', 'Extra Meat', 12, 1.50),
('بيبروني إضافي', 'Extra Pepperoni', 12, 1.00),

-- Main meal addons
('أرز إضافي', 'Extra Rice', 13, 1.00),
('صوص ثوم', 'Garlic Sauce', 13, 0.50),

('بطاطا إضافية', 'Extra Fries', 14, 1.00),
('صوص مشروم', 'Mushroom Sauce', 14, 0.75),

('صوص مشروم', 'Mushroom Sauce', 15, 1.00),
('صوص فلفل', 'Pepper Sauce', 15, 1.00),

('دجاج إضافي', 'Extra Chicken', 16, 1.50),
('بارميزان إضافي', 'Extra Parmesan', 16, 0.75),

('لحم إضافي', 'Extra Beef', 17, 1.50),
('بارميزان', 'Parmesan', 17, 0.75),

('أرز إضافي', 'Extra Rice', 18, 1.00),
('دجاج إضافي', 'Extra Chicken', 18, 1.50),

-- Appetizers
('صوص جبنة', 'Cheese Sauce', 19, 0.50),
('صوص ثوم', 'Garlic Sauce', 19, 0.30),

('جبنة إضافية', 'Extra Cheese', 20, 0.75),
('هالبينو', 'Jalapeno', 20, 0.30),

('صوص ثوم', 'Garlic Sauce', 21, 0.30),

('صوص باربكيو', 'BBQ Sauce', 22, 0.50),
('صوص بافلو', 'Buffalo Sauce', 22, 0.50),

('صوص طماطم', 'Tomato Sauce', 23, 0.30),

('صوص باربكيو', 'BBQ Sauce', 24, 0.30),
('صوص ثوم', 'Garlic Sauce', 24, 0.30);


-- =========================================
-- TABLES
-- =========================================

INSERT INTO tables (
    table_number,
    table_status,
    table_floor,
    position,
    order_id
) VALUES

(1, 'free', 1, '{"x":100,"y":120}', NULL),
(2, 'waiting_order', 1, '{"x":250,"y":120}', 1),
(3, 'order_done', 1, '{"x":400,"y":120}', 2),
(4, 'free', 1, '{"x":550,"y":120}', NULL),
(5, 'waiting_order', 1, '{"x":100,"y":300}', 3),

(6, 'free', 2, '{"x":250,"y":300}', NULL),
(7, 'waiting_order', 2, '{"x":400,"y":300}', 4),
(8, 'order_done', 2, '{"x":550,"y":300}', 5),
(9, 'free', 2, '{"x":100,"y":480}', NULL),
(10, 'waiting_order', 2, '{"x":250,"y":480}', 6);


-- =========================================
-- ORDERS
-- =========================================

INSERT INTO orders (
    food_id,
    table_id,
    status,
    extra_price,
    price,
    details
) VALUES

(
    1,
    2,
    'waiting',
    0.50,
    5.00,
    'Extra cheese, no pickles'
),

(
    8,
    3,
    'finished',
    1.00,
    8.00,
    'Extra pepperoni'
),

(
    16,
    5,
    'waiting',
    1.50,
    9.00,
    'Extra chicken'
),

(
    22,
    7,
    'waiting',
    0.50,
    5.00,
    'Buffalo sauce'
),

(
    15,
    8,
    'finished',
    1.00,
    13.00,
    'Mushroom sauce'
),

(
    4,
    10,
    'waiting',
    0.50,
    5.25,
    'Extra cheese'
),

(
    19,
    2,
    'waiting',
    0.30,
    2.30,
    'Garlic sauce'
),

(
    27,
    5,
    'waiting',
    0.00,
    2.00,
    NULL
),

(
    12,
    7,
    'canceled',
    1.50,
    9.50,
    'Extra meat - customer canceled'
),

(
    18,
    10,
    'waiting',
    1.00,
    7.50,
    'Extra rice'
);