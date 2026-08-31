<?php

require_once __DIR__ . '/JoFotaraInvoiceBuilder.php';

class JoFotaraService
{
    private JoFotaraInvoiceBuilder $builder;

    public function __construct()
    {
        $this->builder = new JoFotaraInvoiceBuilder();
    }

    public function submit(array $invoice, array $settings): array
    {
        $xml = $this->builder->build($invoice);
        $endpoint = $this->endpoint();

        if ($endpoint === null) {
            return [
                'success' => false,
                'temporary' => false,
                'status' => 'retry_pending',
                'http_status' => null,
                'error_code' => 'JOFOTARA_ENDPOINT_NOT_CONFIGURED',
                'error_message' => 'JoFotara API URL is not configured. Confirm the current ISTD technical guide and set JOFOTARA_API_URL.',
                'local_generated_xml' => $xml,
                'sanitized_response' => null,
            ];
        }

        $clientId = $settings['jofotara_client_id'] ?? null;
        $secretKey = $settings['jofotara_secret_key'] ?? null;
        $payload = json_encode([
            'ClientId' => $clientId,
            'SecretKey' => $secretKey,
            'Invoice' => base64_encode($xml),
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            return [
                'success' => false,
                'temporary' => true,
                'status' => 'retry_pending',
                'http_status' => $httpStatus ?: null,
                'error_code' => 'NETWORK_ERROR',
                'error_message' => $error ?: 'JoFotara request failed.',
                'local_generated_xml' => $xml,
                'sanitized_response' => null,
            ];
        }

        $decoded = json_decode((string) $raw, true);
        $accepted = $httpStatus >= 200
            && $httpStatus < 300
            && $this->responseLooksAccepted(is_array($decoded) ? $decoded : []);

        return [
            'success' => $accepted,
            'temporary' => !$accepted && $httpStatus >= 500,
            'status' => $accepted ? 'accepted' : ($httpStatus >= 500 ? 'retry_pending' : 'rejected'),
            'http_status' => $httpStatus,
            'response_status' => $this->firstValue($decoded, ['status', 'EINV_STATUS', 'validationStatus']),
            'electronic_invoice_number' => $this->firstValue($decoded, ['EINV_NUM', 'invoiceNumber', 'ein']),
            'invoice_uuid' => $this->firstValue($decoded, ['EINV_INV_UUID', 'uuid', 'invoiceUUID']),
            'qr_value' => $this->firstValue($decoded, ['EINV_QR', 'qr', 'qrCode']),
            'returned_xml' => $this->firstValue($decoded, ['EINV_XML', 'xml']),
            'error_code' => $accepted ? null : $this->firstValue($decoded, ['errorCode', 'code', 'EINV_ERROR_CODE']),
            'error_message' => $accepted ? null : ($this->firstValue($decoded, ['errorMessage', 'message', 'EINV_ERROR_MESSAGE']) ?: 'JoFotara rejected the invoice.'),
            'local_generated_xml' => $xml,
            'sanitized_response' => $this->sanitizeResponse($decoded ?? $raw),
        ];
    }

    public function validateLocalConfiguration(array $settings): array
    {
        $missing = [];
        foreach (['jofotara_client_id', 'jofotara_secret_key', 'income_source_sequence'] as $field) {
            if (trim((string) ($settings[$field] ?? '')) === '') {
                $missing[] = $field;
            }
        }

        return [
            'success' => $missing === [],
            'message' => $missing === []
                ? 'Local configuration is complete. Final credential verification occurs when a real invoice is submitted.'
                : 'Missing required JoFotara configuration: ' . implode(', ', $missing),
        ];
    }

    private function endpoint(): ?string
    {
        $value = trim((string) ($_ENV['JOFOTARA_API_URL'] ?? ''));

        return $value !== '' ? $value : null;
    }

    private function responseLooksAccepted(array $response): bool
    {
        $status = strtoupper((string) ($this->firstValue($response, ['status', 'EINV_STATUS', 'validationStatus']) ?? ''));

        return in_array($status, ['PASS', 'SUCCESS', 'ACCEPTED', 'SUBMITTED'], true)
            || !empty($this->firstValue($response, ['EINV_QR', 'qr', 'qrCode']));
    }

    private function firstValue(mixed $source, array $keys): ?string
    {
        if (!is_array($source)) {
            return null;
        }

        foreach ($keys as $key) {
            if (isset($source[$key]) && $source[$key] !== '') {
                return is_scalar($source[$key]) ? (string) $source[$key] : json_encode($source[$key], JSON_UNESCAPED_UNICODE);
            }
        }

        foreach ($source as $value) {
            $found = $this->firstValue($value, $keys);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function sanitizeResponse(mixed $response): string
    {
        $json = is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return preg_replace('/(SecretKey|secret|Authorization|ClientId)\"?\s*[:=]\s*\"?[^\"]+/i', '$1":"[redacted]', (string) $json);
    }
}
