<?php

class Invoice
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(?int $restaurantId = null): array
    {
        $where = $restaurantId !== null ? "WHERE invoices.restaurant_id = :restaurant_id" : "";
        $stmt = $this->db->prepare("
            SELECT invoices.*, restaurants.name AS restaurant_name
            FROM invoices
            INNER JOIN restaurants ON restaurants.id = invoices.restaurant_id
            $where
            ORDER BY invoices.id DESC
        ");
        $stmt->execute($restaurantId !== null ? [':restaurant_id' => $restaurantId] : []);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT invoices.*, restaurants.name AS restaurant_name
            FROM invoices
            INNER JOIN restaurants ON restaurants.id = invoices.restaurant_id
            WHERE invoices.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return null;
        }

        $invoice['items'] = $this->items($id);
        $invoice['attempts'] = $this->attempts($id);

        return $invoice;
    }

    public function getByOrderId(int $restaurantId, int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM invoices
            WHERE restaurant_id = :restaurant_id
                AND order_id = :order_id
            LIMIT 1
        ");
        $stmt->execute([':restaurant_id' => $restaurantId, ':order_id' => $orderId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        return $invoice ? $this->getById((int) $invoice['id']) : null;
    }

    public function create(array $invoice, array $items): int
    {
        $columns = array_keys($invoice);
        $stmt = $this->db->prepare("
            INSERT INTO invoices (`" . implode('`, `', $columns) . "`)
            VALUES (:" . implode(', :', $columns) . ")
        ");
        $params = [];
        foreach ($invoice as $key => $value) {
            $params[':' . $key] = $value;
        }
        $stmt->execute($params);
        $invoiceId = (int) $this->db->lastInsertId();

        $itemStmt = $this->db->prepare("
            INSERT INTO invoice_items (
                invoice_id, source_food_id, source_order_item_id, item_code, description, quantity,
                unit_price, discount, price_after_discount, tax_category, tax_rate, special_tax,
                tax_amount, line_total
            )
            VALUES (
                :invoice_id, :source_food_id, :source_order_item_id, :item_code, :description, :quantity,
                :unit_price, :discount, :price_after_discount, :tax_category, :tax_rate, :special_tax,
                :tax_amount, :line_total
            )
        ");

        foreach ($items as $item) {
            $item['invoice_id'] = $invoiceId;
            $itemStmt->execute([
                ':invoice_id' => $item['invoice_id'],
                ':source_food_id' => $item['source_food_id'] ?: null,
                ':source_order_item_id' => $item['source_order_item_id'] ?: null,
                ':item_code' => $item['item_code'],
                ':description' => $item['description'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':discount' => $item['discount'],
                ':price_after_discount' => $item['price_after_discount'],
                ':tax_category' => $item['tax_category'],
                ':tax_rate' => $item['tax_rate'],
                ':special_tax' => $item['special_tax'],
                ':tax_amount' => $item['tax_amount'],
                ':line_total' => $item['line_total'],
            ]);
        }

        return $invoiceId;
    }

    public function nextLocalInvoiceNumber(int $restaurantId, string $prefix): string
    {
        $this->db->prepare("
            INSERT INTO invoice_counters (restaurant_id, next_number)
            VALUES (:restaurant_id, 1)
            ON DUPLICATE KEY UPDATE restaurant_id = restaurant_id
        ")->execute([':restaurant_id' => $restaurantId]);

        $stmt = $this->db->prepare("
            SELECT next_number
            FROM invoice_counters
            WHERE restaurant_id = :restaurant_id
            FOR UPDATE
        ");
        $stmt->execute([':restaurant_id' => $restaurantId]);
        $number = (int) $stmt->fetchColumn();

        $this->db->prepare("
            UPDATE invoice_counters
            SET next_number = next_number + 1
            WHERE restaurant_id = :restaurant_id
        ")->execute([':restaurant_id' => $restaurantId]);

        return sprintf('%s-%06d', $prefix ?: 'INV', $number);
    }

    public function updateSubmission(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE invoices
            SET
                jofotara_submission_status = :jofotara_submission_status,
                jofotara_response_status = :jofotara_response_status,
                jofotara_qr_value = :jofotara_qr_value,
                jofotara_returned_xml = :jofotara_returned_xml,
                local_generated_xml = :local_generated_xml,
                electronic_invoice_number = :electronic_invoice_number,
                error_code = :error_code,
                error_message = :error_message,
                submission_attempts = submission_attempts + :attempt_increment,
                submitted_at = :submitted_at,
                accepted_at = :accepted_at
            WHERE id = :id
        ");

        return $stmt->execute([
            ':jofotara_submission_status' => $data['jofotara_submission_status'],
            ':jofotara_response_status' => $data['jofotara_response_status'] ?? null,
            ':jofotara_qr_value' => $data['jofotara_qr_value'] ?? null,
            ':jofotara_returned_xml' => $data['jofotara_returned_xml'] ?? null,
            ':local_generated_xml' => $data['local_generated_xml'] ?? null,
            ':electronic_invoice_number' => $data['electronic_invoice_number'] ?? null,
            ':error_code' => $data['error_code'] ?? null,
            ':error_message' => $data['error_message'] ?? null,
            ':attempt_increment' => $data['attempt_increment'] ?? 0,
            ':submitted_at' => $data['submitted_at'] ?? null,
            ':accepted_at' => $data['accepted_at'] ?? null,
            ':id' => $id
        ]);
    }

    public function logAttempt(int $invoiceId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO invoice_submission_attempts (
                invoice_id, status, http_status, jofotara_error_code, sanitized_response, retry_number
            )
            VALUES (
                :invoice_id, :status, :http_status, :jofotara_error_code, :sanitized_response, :retry_number
            )
        ");
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':status' => $data['status'],
            ':http_status' => $data['http_status'] ?? null,
            ':jofotara_error_code' => $data['jofotara_error_code'] ?? null,
            ':sanitized_response' => $data['sanitized_response'] ?? null,
            ':retry_number' => $data['retry_number'] ?? 1,
        ]);
    }

    private function items(int $invoiceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id ASC");
        $stmt->execute([':invoice_id' => $invoiceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function attempts(int $invoiceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM invoice_submission_attempts WHERE invoice_id = :invoice_id ORDER BY id DESC");
        $stmt->execute([':invoice_id' => $invoiceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
