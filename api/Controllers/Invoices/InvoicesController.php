<?php

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../Models/InvoiceModel.php';
require_once __DIR__ . '/../../Services/InvoiceService.php';

class InvoicesController
{
    private PDO $db;
    private Invoice $invoiceModel;
    private InvoiceService $invoiceService;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->invoiceModel = new Invoice($db);
        $this->invoiceService = new InvoiceService($db);
    }

    public function index(): array
    {
        return controllersHelper::apiResponse([
            'success' => true,
            'data' => controllersHelper::permissionData($this->db, $this->invoiceModel->getAll(controllersHelper::getRestaurantIdFromQuery()), 'invoices.get')
        ]);
    }

    public function show(int $id): array
    {
        $invoice = $this->invoiceModel->getById($id);
        if (!$invoice) {
            return controllersHelper::apiResponse(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        return controllersHelper::apiResponse([
            'success' => true,
            'data' => controllersHelper::permissionData($this->db, $invoice, 'invoices.get')
        ]);
    }

    public function retry(int $id): void
    {
        $invoice = $this->invoiceModel->getById($id);
        if (!$invoice) {
            controllersHelper::jsonResponse(['success' => false, 'message' => 'Invoice not found.'], 404);
            return;
        }

        if (($invoice['jofotara_submission_status'] ?? '') === 'accepted') {
            controllersHelper::jsonResponse([
                'success' => true,
                'message' => 'Invoice already accepted.',
                'data' => controllersHelper::permissionData($this->db, $invoice, 'invoices.get')
            ]);
            return;
        }

        $result = $this->invoiceService->submitInvoice($id);
        controllersHelper::jsonResponse([
            'success' => $result['success'],
            'message' => $result['message'] ?? 'Retry finished.',
            'data' => isset($result['invoice']) ? controllersHelper::permissionData($this->db, $result['invoice'], 'invoices.get') : null
        ], $result['success'] ? 200 : 422);
    }
}
