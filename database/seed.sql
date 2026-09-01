INSERT INTO restaurants (id, name, location, active_until, manager_number, txt_details, main_code, parent_restaurant_id, branch_management_enabled, branch_limit, branch_settings) VALUES
(1, 'Platform Admin Restaurant', 'Amman, Jordan', '2030-12-31 23:59:59', '0790000000', 'Internal restaurant used for super admin access.', 'ADMIN001', NULL, 0, 0, NULL),
(2, 'Al Waleed Restaurant', 'Amman, Jordan', '2030-12-31 23:59:59', '0797588238', 'Casual dining restaurant with table QR ordering.', 'WLD2026', NULL, 1, 3, '{"opening_hours":"10:00-23:00","delivery_radius_km":8}'),
(3, 'Cedar Bistro', 'Irbid, Jordan', '2030-12-31 23:59:59', '0781112233', 'Demo bistro restaurant for testing multi-restaurant data.', 'CEDAR01', NULL, 0, 0, NULL),
(4, 'Al Waleed Downtown Branch', 'Downtown Amman', '2030-12-31 23:59:59', '0797588238', 'Downtown branch managed under Al Waleed Restaurant.', 'WLD-DT', 2, 0, 0, '{"opening_hours":"09:00-23:00","manager":"Ahmad Cashier"}'),
(5, 'Al Waleed Sweifieh Branch', 'Sweifieh, Amman', '2030-12-31 23:59:59', '0797588239', 'Sweifieh branch managed under Al Waleed Restaurant.', 'WLD-SW', 2, 0, 0, '{"opening_hours":"11:00-01:00","manager":"Maya Chef"}');

INSERT INTO restaurant_website_settings (restaurant_id, brand_name_en, brand_name_ar, hero_title_en, hero_title_ar, hero_accent_en, hero_accent_ar, hero_description_en, hero_description_ar, hero_eyebrow_en, hero_eyebrow_ar, menu_title_en, menu_title_ar, menu_subtitle_en, menu_subtitle_ar, logo_image_url, hero_image_url, takeaway_enabled, primary_color, accent_color, background_color, background_alt_color, surface_color, surface_raised_color, border_color, text_color, text_muted_color, text_faint_color, accent_dark_color, accent_soft_color, ember_color, success_color, danger_color) VALUES
(2, 'Al Waleed Restaurant', 'Al Waleed Restaurant', 'Fresh food at your table', 'Fresh food at your table', 'Scan, order, enjoy', 'Scan, order, enjoy', 'Order burgers, pizza, mains, and drinks directly from your table.', 'Order burgers, pizza, mains, and drinks directly from your table.', 'QR Table Ordering', 'QR Table Ordering', 'Our Menu', 'Our Menu', 'Choose your favorites and send the order to the kitchen.', 'Choose your favorites and send the order to the kitchen.', NULL, '/Portfolio/public/uploads/admin/website/demo-restaurant.jpg', 1, '#e0872f', '#cba15c', '#1b140f', '#221a14', '#2a2019', '#322620', '#3d2f26', '#f4ece0', '#b9a696', '#8a7768', '#b85f1e', 'rgba(224, 135, 47, 0.14)', '#c1441e', '#6f9c6a', '#c1441e'),
(3, 'Cedar Bistro', 'Cedar Bistro', 'Comfort food, clean service', 'Comfort food, clean service', 'Demo Restaurant', 'Demo Restaurant', 'A second restaurant for testing super admin switching.', 'A second restaurant for testing super admin switching.', 'Table Service', 'Table Service', 'Bistro Menu', 'Bistro Menu', 'Fresh items prepared for every order.', 'Fresh items prepared for every order.', NULL, '/Portfolio/public/uploads/admin/website/demo-bistro.jpg', 0, '#1c7ed6', '#f08c00', '#111827', '#172033', '#1f2937', '#263244', '#334155', '#f8fafc', '#cbd5e1', '#94a3b8', '#155ca8', 'rgba(28, 126, 214, 0.14)', '#dc2626', '#16a34a', '#dc2626');

INSERT INTO restaurant_tax_settings (restaurant_id, einvoicing_enabled, taxpayer_type, legal_seller_name, trade_name, seller_address, seller_city, seller_phone, seller_tax_number, default_tax_rate, prices_include_tax, invoice_prefix, automatic_submission, invoice_print_full_page, invoice_print_width_mm, invoice_print_height_mm, configuration_status) VALUES
(2, 0, 'general_sales_tax', 'Al Waleed Restaurant LLC', 'Al Waleed Restaurant', 'Amman', 'Amman', '0797588238', '1002829839', 16.000, 1, 'INV', 0, 0, 80.00, 297.00, 'configured'),
(3, 0, 'income_tax_only', 'Cedar Bistro LLC', 'Cedar Bistro', 'Irbid', 'Irbid', '0781112233', NULL, 0.000, 1, 'CED', 0, 1, 210.00, 297.00, 'configured');

INSERT INTO invoice_counters (restaurant_id, next_number) VALUES
(2, 2),
(3, 1);

INSERT INTO staff (id, name, username, password, pfp, details, hidden_details, salary, branch_id, is_superadmin, is_owner, is_manager, is_employee, allowed_branches, manager_scope, managed_branches, phone, email, permissions, restaurant_id) VALUES
(1, 'Platform Admin', 'admin', '$argon2id$v=19$m=19456,t=2,p=1$TnBIYU9uc2dGS0hZemxHbg$pT5kH/cStyshxmbn88462NA3XttKZz/hXtrSN00B/OU', '/Portfolio/public/uploads/admin/staff/admin.jpg', 'Super admin user. Login with restaurant code ADMIN001.', 'Internal platform administrator account.', 0.00, NULL, 1, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, '1,1,1,1', 1),
(2, 'Alwalid Owner', 'alwalid', '$argon2id$v=19$m=19456,t=2,p=1$blZnUW5kSlU0VW1aTEdFVQ$Zz21EkeNcx8sTAzb8gLoz/nQQ2uszH8xNR6/bn9bdio', '/Portfolio/public/uploads/admin/staff/alwalid.jpg', 'Restaurant owner with full local permissions.', NULL, 1200.00, NULL, 0, 1, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(3, 'Ahmad Cashier', 'ahmad', '$argon2id$v=19$m=19456,t=2,p=1$WHREMGl5a1F5SHd6UUo2Ng$QRurNYlPbyZB4OB7BH89BhHXbPZl7MpQaXckdfsvQLQ', '/Portfolio/public/uploads/admin/staff/ahmad.jpg', 'Cashier focused on orders, tables, invoices, and logs.', NULL, 450.00, 4, 0, 0, 0, 1, NULL, NULL, NULL, NULL, NULL, '0,1,0,1,0,0,1,1,1,1,0,1,0,0,0,1,0,0,0,1,0,1,0,0,0,0,0,1,0,0', 2),
(4, 'Maya Chef', 'maya', '$argon2id$v=19$m=19456,t=2,p=1$Rk1OOU50aDNSTEFydFFRQw$NaG8wkqTW0RW7bEEdjKlU8px7Z8EjZQytm5WfkxNh40', '/Portfolio/public/uploads/admin/staff/maya.jpg', 'Chef can view orders and update food status.', NULL, 500.00, 5, 0, 0, 0, 1, NULL, NULL, NULL, NULL, NULL, '0,0,0,0,0,0,1,0,1,0,0,0,1,0,0,0,1,0,0,0,1,0,0,0,0,0,0,1,0,0', 2);

INSERT INTO menu_categories (id, name_ar, name_en, description_ar, description_en, restaurant_id) VALUES
(1, 'Burgers', 'Burgers', 'Burger meals and sandwiches', 'Burger meals and sandwiches', 2),
(2, 'Pizza', 'Pizza', 'Fresh pizza with different toppings', 'Fresh pizza with different toppings', 2),
(3, 'Main Meals', 'Main Meals', 'Hot main dishes', 'Hot main dishes', 2),
(4, 'Appetizers', 'Appetizers', 'Sides and starters', 'Sides and starters', 2),
(5, 'Drinks', 'Drinks', 'Cold drinks and juices', 'Cold drinks and juices', 2),
(6, 'Bistro Plates', 'Bistro Plates', 'Demo second restaurant foods', 'Demo second restaurant foods', 3);

INSERT INTO menu_foods (id, name_en, name_ar, description_en, description_ar, image_url, price, profit, tax_category, tax_rate, special_tax_amount, tax_exempt, category_id, restaurant_id) VALUES
(1, 'Classic Beef Burger', 'Classic Beef Burger', 'Grilled beef patty with lettuce, tomato, and sauce.', 'Grilled beef patty with lettuce, tomato, and sauce.', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd', 4.50, 1.40, 'default', NULL, 0.000, 0, 1, 2),
(2, 'Double Beef Burger', 'Double Beef Burger', 'Two beef patties with cheddar and special sauce.', 'Two beef patties with cheddar and special sauce.', 'https://images.unsplash.com/photo-1594212699903-ec8a3eca50f5', 6.50, 2.10, 'default', NULL, 0.000, 0, 1, 2),
(3, 'Crispy Chicken Burger', 'Crispy Chicken Burger', 'Crispy chicken with lettuce and mayonnaise.', 'Crispy chicken with lettuce and mayonnaise.', 'https://images.unsplash.com/photo-1615297928064-24977384d0da', 4.75, 1.30, 'default', NULL, 0.000, 0, 1, 2),
(4, 'Margherita Pizza', 'Margherita Pizza', 'Tomato sauce, mozzarella, and basil.', 'Tomato sauce, mozzarella, and basil.', 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002', 6.00, 2.00, 'default', NULL, 0.000, 0, 2, 2),
(5, 'Pepperoni Pizza', 'Pepperoni Pizza', 'Mozzarella with pepperoni slices.', 'Mozzarella with pepperoni slices.', 'https://images.unsplash.com/photo-1628840042765-356cda07504e', 7.00, 2.25, 'default', NULL, 0.000, 0, 2, 2),
(6, 'Grilled Chicken', 'Grilled Chicken', 'Grilled chicken served with rice and vegetables.', 'Grilled chicken served with rice and vegetables.', 'https://images.unsplash.com/photo-1532550907401-a500c9a57435', 7.00, 2.40, 'default', NULL, 0.000, 0, 3, 2),
(7, 'French Fries', 'French Fries', 'Golden crispy french fries.', 'Golden crispy french fries.', 'https://images.unsplash.com/photo-1630384060421-cb20d0e0649d', 2.00, 0.80, 'default', NULL, 0.000, 0, 4, 2),
(8, 'Coca Cola', 'Coca Cola', 'Cold Coca Cola drink.', 'Cold Coca Cola drink.', 'https://images.unsplash.com/photo-1629203851122-3726ecdf080e', 1.00, 0.35, 'default', NULL, 0.000, 0, 5, 2),
(9, 'Mineral Water', 'Mineral Water', 'Cold mineral water.', 'Cold mineral water.', 'https://images.unsplash.com/photo-1523362628745-0c100150b504', 0.75, 0.30, 'default', NULL, 0.000, 0, 5, 2),
(10, 'Cedar Steak Plate', 'Cedar Steak Plate', 'Demo steak plate for Cedar Bistro.', 'Demo steak plate for Cedar Bistro.', 'https://images.unsplash.com/photo-1546833999-b9f581a1996d', 12.00, 4.00, 'default', NULL, 0.000, 0, 6, 3);

INSERT INTO food_addons (id, name_ar, name_en, food_id, category_id, extra_price, extra_profit, restaurant_id) VALUES
(1, 'Extra Cheese', 'Extra Cheese', 1, NULL, 0.50, 0.20, 2),
(2, 'Extra Beef Patty', 'Extra Beef Patty', 1, NULL, 1.50, 0.55, 2),
(3, 'Extra Sauce', 'Extra Sauce', 1, NULL, 0.25, 0.10, 2),
(4, 'Extra Cheese', 'Extra Cheese', 2, NULL, 0.50, 0.20, 2),
(5, 'Extra Beef Patty', 'Extra Beef Patty', 2, NULL, 1.50, 0.55, 2),
(6, 'Garlic Sauce', 'Garlic Sauce', 3, NULL, 0.30, 0.10, 2),
(7, 'Extra Cheese', 'Extra Cheese', 4, NULL, 1.00, 0.35, 2),
(8, 'Extra Pepperoni', 'Extra Pepperoni', 5, NULL, 1.00, 0.30, 2),
(9, 'Extra Rice', 'Extra Rice', 6, NULL, 1.00, 0.35, 2),
(10, 'Cheese Sauce', 'Cheese Sauce', 7, NULL, 0.50, 0.20, 2),
(11, 'Mushroom Sauce', 'Mushroom Sauce', 10, NULL, 1.00, 0.40, 3),
(12, 'Spicy Sauce', 'Spicy Sauce', NULL, 1, 0.20, 0.08, 2),
(13, 'Extra Pickles', 'Extra Pickles', NULL, 1, 0.15, 0.05, 2),
(14, 'Large Size', 'Large Size', NULL, 5, 0.35, 0.12, 2);

INSERT INTO tables (id, table_number, table_status, table_floor, position, order_id, restaurant_id) VALUES
(1, 1, 'free', 1, '{"x":80,"y":80}', NULL, 2),
(2, 2, 'waiting_order', 1, '{"x":220,"y":80}', NULL, 2),
(3, 3, 'order_done', 1, '{"x":360,"y":80}', NULL, 2),
(4, 4, 'free', 1, '{"x":500,"y":80}', NULL, 2),
(5, 5, 'free', 2, '{"x":80,"y":80}', NULL, 2),
(6, 6, 'waiting_order', 2, '{"x":220,"y":80}', NULL, 2),
(7, 1, 'free', 1, '{"x":80,"y":80}', NULL, 3);

INSERT INTO orders (order_id, table_id, order_type, status, payment_status, payment_method, total_paid_cash, total_paid_credit, extra_price, price, profit, details, session_order_key, created_at, restaurant_id) VALUES
(1, 2, 'table', 'waiting', 'unpaid', NULL, 0.00, 0.00, 2.00, 14.50, 4.20, NULL, '2221507', NOW() - INTERVAL 20 MINUTE, 2),
(2, 3, 'table', 'finished', 'unpaid', NULL, 0.00, 0.00, 1.00, 8.00, 2.55, NULL, '2321512', NOW() - INTERVAL 50 MINUTE, 2),
(3, 6, 'table', 'waiting', 'unpaid', NULL, 0.00, 0.00, 0.30, 5.05, 1.40, NULL, '2631530', NOW() - INTERVAL 8 MINUTE, 2),
(4, NULL, 'takeaway', 'waiting', 'unpaid', NULL, 0.00, 0.00, 0.00, 6.00, 1.90, NULL, '2TA41544', NOW() - INTERVAL 4 MINUTE, 2),
(5, NULL, 'takeaway', 'finished', 'paid', 'cash', 12.50, 0.00, 0.00, 12.50, 4.05, NULL, '4TA41544', NOW() - INTERVAL 1 DAY, 4),
(6, NULL, 'takeaway', 'finished', 'paid', 'credit', 0.00, 9.00, 0.00, 9.00, 2.80, NULL, '5TA41544', NOW() - INTERVAL 1 DAY, 5);

INSERT INTO order_foods (id, order_id, food_id, qty, addon_id, status, table_id, order_type, price, extra_price, profit, details, session_order_key, created_at, restaurant_id) VALUES
(1, 1, 1, 1, '[1]', 'waiting', 2, 'table', 5.00, 0.50, 1.60, NULL, '2221507', NOW() - INTERVAL 20 MINUTE, 2),
(2, 1, 2, 1, '[5]', 'waiting', 2, 'table', 8.00, 1.50, 2.65, NULL, '2221507', NOW() - INTERVAL 20 MINUTE, 2),
(3, 1, 8, 1, '[]', 'waiting', 2, 'table', 1.00, 0.00, 0.35, NULL, '2221507', NOW() - INTERVAL 20 MINUTE, 2),
(4, 1, 8, 1, '[]', 'canceled', 2, 'table', 0.00, 0.00, 0.00, NULL, '2221507', NOW() - INTERVAL 20 MINUTE, 2),
(5, 2, 5, 1, '[8]', 'finished', 3, 'table', 8.00, 1.00, 2.55, NULL, '2321512', NOW() - INTERVAL 50 MINUTE, 2),
(6, 3, 3, 1, '[6]', 'waiting', 6, 'table', 5.05, 0.30, 1.40, NULL, '2631530', NOW() - INTERVAL 8 MINUTE, 2),
(7, 4, 1, 1, '[]', 'waiting', NULL, 'takeaway', 4.50, 0.00, 1.40, NULL, '2TA41544', NOW() - INTERVAL 4 MINUTE, 2),
(8, 4, 8, 1, '[]', 'waiting', NULL, 'takeaway', 1.50, 0.00, 0.50, NULL, '2TA41544', NOW() - INTERVAL 4 MINUTE, 2),
(9, 5, 1, 2, '[1]', 'finished', NULL, 'takeaway', 10.00, 1.00, 3.20, NULL, '4TA41544', NOW() - INTERVAL 1 DAY, 4),
(10, 6, 5, 1, '[8]', 'finished', NULL, 'takeaway', 9.00, 1.00, 2.80, NULL, '5TA41544', NOW() - INTERVAL 1 DAY, 5);

UPDATE tables SET order_id = 1 WHERE id = 2;
UPDATE tables SET order_id = 2 WHERE id = 3;
UPDATE tables SET order_id = 3 WHERE id = 6;

INSERT INTO inventory (id, name, quantity, unit, price, profit, restaurant_id) VALUES
(1, 'Burger Meat', 48.500, 'kgs', 6.00, 0.00, 2),
(2, 'Chicken Breast', 32.000, 'kgs', 4.25, 0.00, 2),
(3, 'Burger Buns', 180.000, 'pcs', 0.12, 0.00, 2),
(4, 'Cheddar Cheese', 95.000, 'pcs', 0.10, 0.00, 2),
(5, 'Coca Cola Can', 120.000, 'pcs', 0.45, 0.00, 2),
(6, 'Mineral Water Bottle', 220.000, 'pcs', 0.25, 0.00, 2),
(7, 'Pizza Dough', 55.000, 'pcs', 0.70, 0.00, 2),
(8, 'Cedar Steak Meat', 18.000, 'kgs', 8.50, 0.00, 3),
(9, 'Downtown Burger Buns', 90.000, 'pcs', 0.12, 0.00, 4),
(10, 'Sweifieh Pizza Dough', 45.000, 'pcs', 0.70, 0.00, 5);

INSERT INTO inventory_food_links (inventory_id, food_id, addon_id, quantity_per_item, restaurant_id) VALUES
(1, 1, NULL, 0.250, 2),
(1, 1, 2, 0.250, 2),
(1, 2, NULL, 0.500, 2),
(1, 2, 5, 0.250, 2),
(2, 3, NULL, 0.220, 2),
(3, 1, NULL, 1.000, 2),
(3, 2, NULL, 1.000, 2),
(3, 3, NULL, 1.000, 2),
(4, 1, 1, 1.000, 2),
(4, 2, 4, 1.000, 2),
(5, 8, NULL, 1.000, 2),
(6, 9, NULL, 1.000, 2),
(7, 4, NULL, 1.000, 2),
(7, 5, NULL, 1.000, 2),
(8, 10, NULL, 0.400, 3);

INSERT INTO inventory_movements (inventory_id, order_id, order_food_id, movement_type, quantity_change, reason, created_at, restaurant_id) VALUES
(1, NULL, NULL, 'purchase', 50.000, 'Initial stock', NOW() - INTERVAL 2 DAY, 2),
(1, 1, 1, 'consume', -0.250, 'Order consumption', NOW() - INTERVAL 20 MINUTE, 2),
(1, 1, 2, 'consume', -0.750, 'Order consumption', NOW() - INTERVAL 20 MINUTE, 2),
(5, 1, 3, 'consume', -1.000, 'Order consumption', NOW() - INTERVAL 20 MINUTE, 2),
(5, 1, 4, 'return', 1.000, 'Canceled food returned to stock', NOW() - INTERVAL 18 MINUTE, 2),
(3, NULL, NULL, 'waste', -5.000, 'Damaged buns', NOW() - INTERVAL 1 DAY, 2),
(9, NULL, NULL, 'waste', -2.000, 'Branch sample waste', NOW() - INTERVAL 1 DAY, 4),
(10, NULL, NULL, 'waste', -1.000, 'Branch sample waste', NOW() - INTERVAL 1 DAY, 5);

INSERT INTO invoices (id, restaurant_id, order_id, local_invoice_number, invoice_uuid, invoice_type, taxpayer_type, payment_type, currency, subtotal, discount_total, taxable_amount, tax_total, grand_total, seller_name, seller_trade_name, seller_address, seller_phone, seller_tax_number, issued_at, jofotara_submission_status) VALUES
(1, 2, 2, 'INV-000001', 'demo-invoice-uuid-000001', 'sales_invoice', 'general_sales_tax', 'cash', 'JOD', 6.897, 0.000, 6.897, 1.103, 8.000, 'Al Waleed Restaurant LLC', 'Al Waleed Restaurant', 'Amman', '0797588238', '1002829839', NOW() - INTERVAL 45 MINUTE, 'disabled');

INSERT INTO invoice_items (invoice_id, source_food_id, source_order_item_id, item_code, description, quantity, unit_price, discount, price_after_discount, tax_category, tax_rate, special_tax, tax_amount, line_total) VALUES
(1, 5, 5, 'FOOD-5', 'Pepperoni Pizza + Extra Pepperoni', 1.000, 6.897, 0.000, 6.897, 'S', 16.000, 0.000, 1.103, 8.000);

INSERT INTO activity_logs (restaurant_id, branch_id, employee_id, employee_name, permission_key, entity_type, entity_id, action_label, message, metadata, created_at) VALUES
(2, 0, 2, 'Alwalid Owner', 'foods.create', 'food', '1', 'Added new food', 'Alwalid Owner - Added new food (Food 1)', '{"entity_name":"Classic Beef Burger"}', NOW() - INTERVAL 2 HOUR),
(2, 4, 3, 'Ahmad Cashier', 'orders.update', 'order_food', '5', 'Updated order food status', 'Ahmad Cashier - Updated order food status (Order_food 5)', '{"order_id":2,"entity_name":"Pepperoni Pizza","changes":{"status":{"old":"waiting","new":"finished"}}}', NOW() - INTERVAL 45 MINUTE),
(2, 4, 3, 'Ahmad Cashier', 'tables.update', 'table', '3', 'Updated the table status to order done', 'Ahmad Cashier - Updated the table status to order done (Table 3)', '{"entity_name":"Table 3","changes":{"table_status":{"old":"waiting_order","new":"order_done"}}}', NOW() - INTERVAL 40 MINUTE),
(2, 0, 2, 'Alwalid Owner', 'restaurant.update', 'restaurant', '2', 'Updated restaurant settings', 'Alwalid Owner - Updated restaurant settings (Restaurant 2)', '{"entity_name":"Al Waleed Restaurant","changes":{"primary_color":{"old":"#b8541b","new":"#b8541b"}}}', NOW() - INTERVAL 30 MINUTE),
(2, 5, 4, 'Maya Chef', 'orders.update', 'order_food', '6', 'Updated order food status', 'Maya Chef - Updated order food status (Order_food 6)', '{"order_id":3,"entity_name":"Crispy Chicken Burger","changes":{"status":{"old":"waiting","new":"waiting"}}}', NOW() - INTERVAL 8 MINUTE);
