<?php

class JoFotaraInvoiceBuilder
{
    public function build(array $invoice): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $doc->appendChild($root);

        $this->cbc($doc, $root, 'ID', $invoice['local_invoice_number']);
        $this->cbc($doc, $root, 'UUID', $invoice['invoice_uuid']);
        $this->cbc($doc, $root, 'IssueDate', substr((string) $invoice['issued_at'], 0, 10));
        $type = $this->cbc($doc, $root, 'InvoiceTypeCode', '388');
        $type->setAttribute('name', $invoice['payment_type'] === 'receivable' ? '022' : '012');
        $this->cbc($doc, $root, 'DocumentCurrencyCode', 'JOD');
        $this->cbc($doc, $root, 'TaxCurrencyCode', 'JOD');

        $icv = $this->cac($doc, $root, 'AdditionalDocumentReference');
        $this->cbc($doc, $icv, 'ID', 'ICV');
        $this->cbc($doc, $icv, 'UUID', preg_replace('/\D+/', '', (string) $invoice['local_invoice_number']) ?: (string) $invoice['id']);

        $supplier = $this->cac($doc, $root, 'AccountingSupplierParty');
        $supplierParty = $this->cac($doc, $supplier, 'Party');
        $address = $this->cac($doc, $supplierParty, 'PostalAddress');
        $country = $this->cac($doc, $address, 'Country');
        $this->cbc($doc, $country, 'IdentificationCode', 'JO');
        $taxScheme = $this->cac($doc, $supplierParty, 'PartyTaxScheme');
        $this->cbc($doc, $taxScheme, 'CompanyID', $invoice['seller_tax_number'] ?: $invoice['seller_national_number']);
        $scheme = $this->cac($doc, $taxScheme, 'TaxScheme');
        $this->cbc($doc, $scheme, 'ID', 'VAT');
        $legal = $this->cac($doc, $supplierParty, 'PartyLegalEntity');
        $this->cbc($doc, $legal, 'RegistrationName', $invoice['seller_name']);

        if (!empty($invoice['buyer_name']) || !empty($invoice['buyer_identification_number'])) {
            $customer = $this->cac($doc, $root, 'AccountingCustomerParty');
            $party = $this->cac($doc, $customer, 'Party');
            if (!empty($invoice['buyer_identification_number'])) {
                $partyId = $this->cac($doc, $party, 'PartyIdentification');
                $id = $this->cbc($doc, $partyId, 'ID', $invoice['buyer_identification_number']);
                $id->setAttribute('schemeID', $invoice['buyer_identification_type'] ?: 'TN');
            }
            $customerLegal = $this->cac($doc, $party, 'PartyLegalEntity');
            $this->cbc($doc, $customerLegal, 'RegistrationName', $invoice['buyer_name'] ?: 'Customer');
        }

        $seller = $this->cac($doc, $root, 'SellerSupplierParty');
        $sellerParty = $this->cac($doc, $seller, 'Party');
        $sellerId = $this->cac($doc, $sellerParty, 'PartyIdentification');
        $this->cbc($doc, $sellerId, 'ID', $invoice['seller_income_source_sequence']);

        $taxTotal = $this->cac($doc, $root, 'TaxTotal');
        $taxAmount = $this->cbc($doc, $taxTotal, 'TaxAmount', $invoice['tax_total']);
        $taxAmount->setAttribute('currencyID', 'JO');

        foreach (($invoice['items'] ?? []) as $index => $item) {
            $line = $this->cac($doc, $root, 'InvoiceLine');
            $this->cbc($doc, $line, 'ID', (string) ($index + 1));
            $qty = $this->cbc($doc, $line, 'InvoicedQuantity', $item['quantity']);
            $qty->setAttribute('unitCode', 'PCE');
            $lineAmount = $this->cbc($doc, $line, 'LineExtensionAmount', $item['price_after_discount']);
            $lineAmount->setAttribute('currencyID', 'JO');

            $lineTax = $this->cac($doc, $line, 'TaxTotal');
            $lineTaxAmount = $this->cbc($doc, $lineTax, 'TaxAmount', $item['tax_amount']);
            $lineTaxAmount->setAttribute('currencyID', 'JO');
            $rounding = $this->cbc($doc, $lineTax, 'RoundingAmount', $item['line_total']);
            $rounding->setAttribute('currencyID', 'JO');

            $lineItem = $this->cac($doc, $line, 'Item');
            $this->cbc($doc, $lineItem, 'Name', $item['description']);
            $price = $this->cac($doc, $line, 'Price');
            $priceAmount = $this->cbc($doc, $price, 'PriceAmount', $item['unit_price']);
            $priceAmount->setAttribute('currencyID', 'JO');
        }

        return $doc->saveXML();
    }

    private function cac(DOMDocument $doc, DOMElement $parent, string $name): DOMElement
    {
        $node = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2', 'cac:' . $name);
        $parent->appendChild($node);

        return $node;
    }

    private function cbc(DOMDocument $doc, DOMElement $parent, string $name, string $value): DOMElement
    {
        $node = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2', 'cbc:' . $name);
        $node->appendChild($doc->createTextNode($value));
        $parent->appendChild($node);

        return $node;
    }
}
