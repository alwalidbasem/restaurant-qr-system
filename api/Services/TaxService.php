<?php

class TaxService
{
    public function calculateLines(array $orderFoods, array $settings): array
    {
        $lines = [];
        $totals = [
            'subtotal' => '0.000',
            'discount_total' => '0.000',
            'taxable_amount' => '0.000',
            'tax_total' => '0.000',
            'grand_total' => '0.000',
        ];

        foreach ($orderFoods as $row) {
            if (($row['food_status'] ?? $row['status'] ?? 'waiting') === 'canceled') {
                continue;
            }

            $price = $this->money($row['price'] ?? $row['food_price'] ?? 0);
            $discount = '0.000';
            $rate = $this->itemTaxRate($row, $settings);
            $taxCategory = $this->itemTaxCategory($row, $settings, $rate);
            $pricesIncludeTax = !empty($settings['prices_include_tax']);

            if ($pricesIncludeTax && $this->decimalToFils($rate) > 0) {
                $priceFils = $this->decimalToFils($price);
                $rateBasisPoints = (int) round(((float) $rate) * 100);
                $taxableFils = (int) round(($priceFils * 10000) / (10000 + $rateBasisPoints));
                $taxFils = $priceFils - $taxableFils;
                $taxable = $this->filsToDecimal($taxableFils);
                $tax = $this->filsToDecimal($taxFils);
                $lineTotal = $price;
            } else {
                $taxableFils = max(0, $this->decimalToFils($price) - $this->decimalToFils($discount));
                $rateBasisPoints = (int) round(((float) $rate) * 100);
                $taxFils = (int) round(($taxableFils * $rateBasisPoints) / 10000);
                $lineTotalFils = $taxableFils + $taxFils;
                $taxable = $this->filsToDecimal($taxableFils);
                $tax = $this->filsToDecimal($taxFils);
                $lineTotal = $this->filsToDecimal($lineTotalFils);
            }

            $lines[] = [
                'source_food_id' => (int) ($row['food_id'] ?? 0),
                'source_order_item_id' => (int) ($row['order_food_row_id'] ?? $row['order_food_id'] ?? $row['id'] ?? 0),
                'item_code' => (string) ($row['food_id'] ?? ''),
                'description' => trim((string) (($row['food_name_en'] ?? '') . ' / ' . ($row['food_name_ar'] ?? ''))) ?: 'Food item',
                'quantity' => '1.000',
                'unit_price' => $price,
                'discount' => $discount,
                'price_after_discount' => $taxable,
                'tax_category' => $taxCategory,
                'tax_rate' => $rate,
                'special_tax' => $this->money($row['special_tax_amount'] ?? 0),
                'tax_amount' => $tax,
                'line_total' => $lineTotal,
            ];

            $totals['subtotal'] = $this->addDecimal($totals['subtotal'], $price);
            $totals['discount_total'] = $this->addDecimal($totals['discount_total'], $discount);
            $totals['taxable_amount'] = $this->addDecimal($totals['taxable_amount'], $taxable);
            $totals['tax_total'] = $this->addDecimal($totals['tax_total'], $tax);
            $totals['grand_total'] = $this->addDecimal($totals['grand_total'], $lineTotal);
        }

        return ['lines' => $lines, 'totals' => $totals];
    }

    public function itemTaxRate(array $row, array $settings): string
    {
        if (!empty($row['tax_exempt'])) {
            return '0.000';
        }

        if (($row['tax_rate'] ?? null) !== null && $row['tax_rate'] !== '') {
            return $this->money($row['tax_rate']);
        }

        if (($settings['taxpayer_type'] ?? 'income_tax_only') === 'general_sales_tax' || ($settings['taxpayer_type'] ?? '') === 'special_sales_tax') {
            return $this->money($settings['default_tax_rate'] ?? 0);
        }

        return '0.000';
    }

    public function itemTaxCategory(array $row, array $settings, string $rate): string
    {
        if (!empty($row['tax_exempt']) || $this->decimalToFils($rate) === 0) {
            return 'Z';
        }

        if (($row['tax_category'] ?? '') !== '' && ($row['tax_category'] ?? 'default') !== 'default') {
            return (string) $row['tax_category'];
        }

        return 'S';
    }

    public function round(string $value): string
    {
        return number_format(round((float) $value, 3), 3, '.', '');
    }

    private function addDecimal(string $left, string $right): string
    {
        return $this->filsToDecimal($this->decimalToFils($left) + $this->decimalToFils($right));
    }

    private function decimalToFils(mixed $value): int
    {
        return (int) round(((float) $value) * 1000);
    }

    private function filsToDecimal(int $value): string
    {
        return number_format($value / 1000, 3, '.', '');
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
