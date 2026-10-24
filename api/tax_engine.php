<?php
header('Content-Type: application/json');

function calculate_tax_and_totals(array $items, float $taxRate = 0.12, ?string $customerType = null, ?string $idNumber = null): array {
    $grossSubtotal = 0.0;
    foreach ($items as $item) {
        $grossSubtotal += ($item['price'] * $item['quantity']);
    }

    $vatExemptSales = 0.0;
    $vatableSales = 0.0;
    $vatAmount = 0.0;
    $discountAmount = 0.0;

    $normalizedType = strtolower(trim((string)$customerType));
    $isSeniorOrPwd = in_array($normalizedType, ['senior', 'pwd'], true);

    if ($isSeniorOrPwd) {
        if (empty(trim((string)$idNumber))) {
            throw new Exception("ID Number is required for Senior Citizen / PWD discounts.");
        }

        $vatExemptSales = $grossSubtotal / 1.12;
        $discountAmount = $vatExemptSales * 0.20;
        $netTotal = $vatExemptSales - $discountAmount;
        $vatAmount = 0.0;
    } else {
        $normalizedType = 'regular';
        $vatableSales = $grossSubtotal;
        $vatAmount = $vatableSales * $taxRate;
        $netTotal = $vatableSales + $vatAmount;
    }

    return [
        'gross_subtotal'   => round($grossSubtotal, 2),
        'vatable_sales'    => round($vatableSales, 2),
        'vat_exempt_sales' => round($vatExemptSales, 2),
        'tax_rate_applied' => $taxRate,
        'vat_amount'       => round($vatAmount, 2),
        'customer_type'    => $normalizedType,
        'discount_id'      => $isSeniorOrPwd ? trim((string)$idNumber) : null,
        'discount_amount'  => round($discountAmount, 2),
        'net_total'        => round($netTotal, 2),
    ];
}