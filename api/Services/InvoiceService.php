<?php

require_once __DIR__ . '/../Models/InvoiceModel.php';
require_once __DIR__ . '/../Models/OrdersModel.php';
require_once __DIR__ . '/../Models/RestaurantTaxSettingsModel.php';
require_once __DIR__ . '/TaxService.php';
require_once __DIR__ . '/JoFotaraService.php';

class InvoiceService
{
    private PDO $db;
    private Invoice $invoiceModel;
    private Order $orderModel;
    private RestaurantTaxSettings $settingsModel;
    private TaxService $taxService;
    private JoFotaraService $joFotaraService;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->invoiceModel = new Invoice($db);
        $this->orderModel = new Order($db);
        $this->settingsModel = new RestaurantTaxSettings($db);
        $this->taxService = new TaxService();
        $this->joFotaraService = new JoFotaraService();
    }

    public function finalizeOrderInvoice(int $orderId): array
    {
        $order = $this->orderModel->getParentById($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found.'];
        }

        $existing = $this->invoiceModel->getByOrderId((int) $order['restaurant_id'], $orderId);
        if ($existing) {
            $this->submitIfNeeded($existing);
            return ['success' => true, 'invoice' => $this->invoiceModel->getById((int) $existing['id']), 'created' => false];
        }

        $settings = $this->settingsModel->getByRestaurantId((int) $order['restaurant_id'], true);

        $this->db->beginTransaction();
        try {
            $freshExisting = $this->invoiceModel->getByOrderId((int) $order['restaurant_id'], $orderId);
            if ($freshExisting) {
                $this->db->commit();
                return ['success' => true, 'invoice' => $freshExisting, 'created' => false];
            }

            $rows = $this->orderModel->getFoodsByOrderId($orderId);
            $calculation = $this->taxService->calculateLines($rows, $settings);
            $localNumber = $this->invoiceModel->nextLocalInvoiceNumber((int) $order['restaurant_id'], (string) ($settings['invoice_prefix'] ?? 'INV'));
            $status = !empty($settings['einvoicing_enabled']) ? 'ready' : 'disabled';
            $invoiceType = $this->invoiceType($settings);
            $paymentType = $this->paymentType($order);

            $invoiceId = $this->invoiceModel->create([
                'restaurant_id' => (int) $order['restaurant_id'],
                'order_id' => $orderId,
                'local_invoice_number' => $localNumber,
                'invoice_uuid' => $this->uuid(),
                'invoice_type' => $invoiceType,
                'taxpayer_type' => $settings['taxpayer_type'] ?? 'income_tax_only',
                'payment_type' => $paymentType,
                'currency' => 'JOD',
                'subtotal' => $calculation['totals']['subtotal'],
                'discount_total' => $calculation['totals']['discount_total'],
                'taxable_amount' => $calculation['totals']['taxable_amount'],
                'tax_total' => $calculation['totals']['tax_total'],
                'grand_total' => $calculation['totals']['grand_total'],
                'seller_name' => $settings['legal_seller_name'] ?: ($order['restaurant_name'] ?? 'Restaurant'),
                'seller_trade_name' => $settings['trade_name'] ?? null,
                'seller_address' => $settings['seller_address'] ?: ($order['restaurant_name'] ?? null),
                'seller_phone' => $settings['seller_phone'] ?? null,
                'seller_tax_number' => $settings['seller_tax_number'] ?? null,
                'seller_national_number' => $settings['seller_national_number'] ?? null,
                'seller_income_source_sequence' => $settings['income_source_sequence'] ?? null,
                'jofotara_submission_status' => $status,
                'issued_at' => date('Y-m-d H:i:s'),
            ], $calculation['lines']);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $invoice = $this->invoiceModel->getById($invoiceId);
        if (!empty($settings['einvoicing_enabled']) && !empty($settings['automatic_submission'])) {
            $this->submitInvoice($invoiceId);
            $invoice = $this->invoiceModel->getById($invoiceId);
        }

        return ['success' => true, 'invoice' => $invoice, 'created' => true];
    }

    public function submitInvoice(int $invoiceId): array
    {
        $invoice = $this->invoiceModel->getById($invoiceId);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }

        if (($invoice['jofotara_submission_status'] ?? '') === 'accepted') {
            return ['success' => true, 'invoice' => $invoice, 'message' => 'Invoice already accepted.'];
        }

        $settings = $this->settingsModel->getByRestaurantId((int) $invoice['restaurant_id'], true);
        $result = $this->joFotaraService->submit($invoice, $settings);
        $now = date('Y-m-d H:i:s');

        $this->invoiceModel->updateSubmission($invoiceId, [
            'jofotara_submission_status' => $result['status'],
            'jofotara_response_status' => $result['response_status'] ?? null,
            'jofotara_qr_value' => $result['qr_value'] ?? null,
            'jofotara_returned_xml' => $result['returned_xml'] ?? null,
            'local_generated_xml' => $result['local_generated_xml'] ?? null,
            'electronic_invoice_number' => $result['electronic_invoice_number'] ?? null,
            'error_code' => $result['error_code'] ?? null,
            'error_message' => $result['error_message'] ?? null,
            'attempt_increment' => 1,
            'submitted_at' => $now,
            'accepted_at' => !empty($result['success']) ? $now : null,
        ]);
        $this->invoiceModel->logAttempt($invoiceId, [
            'status' => $result['status'],
            'http_status' => $result['http_status'] ?? null,
            'jofotara_error_code' => $result['error_code'] ?? null,
            'sanitized_response' => $result['sanitized_response'] ?? null,
            'retry_number' => ((int) ($invoice['submission_attempts'] ?? 0)) + 1,
        ]);

        return ['success' => !empty($result['success']), 'invoice' => $this->invoiceModel->getById($invoiceId), 'message' => $result['error_message'] ?? 'Invoice submitted.'];
    }

    private function submitIfNeeded(array $invoice): void
    {
        if (in_array($invoice['jofotara_submission_status'] ?? '', ['ready', 'retry_pending'], true)) {
            $this->submitInvoice((int) $invoice['id']);
        }
    }

    private function invoiceType(array $settings): string
    {
        return [
            'general_sales_tax' => 'general_sales_tax_invoice',
            'special_sales_tax' => 'special_sales_tax_invoice',
            'income_tax_only' => 'income_invoice',
        ][$settings['taxpayer_type'] ?? 'income_tax_only'] ?? 'income_invoice';
    }

    private function paymentType(array $order): string
    {
        return ($order['payment_method'] ?? '') === 'credit' ? 'receivable' : 'cash';
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
