ALTER TABLE orders
ADD COLUMN session_order_key VARCHAR(255) NULL AFTER details;

UPDATE orders AS o
INNER JOIN tables AS t
    ON t.id = o.table_id
SET o.session_order_key = CONCAT(
    o.restaurant_id,
    t.table_number,
    o.order_id,
    DATE_FORMAT(COALESCE(o.created_at, NOW()), '%H%i')
)
WHERE o.session_order_key IS NULL
    OR o.session_order_key = '';

ALTER TABLE orders
MODIFY session_order_key VARCHAR(255) NOT NULL;
