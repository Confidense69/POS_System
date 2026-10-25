<?php
// Tax / discount engine.
//
// Pure functions only: no headers, no output, no database access. This file is
// safe to require from an API endpoint, a report page, or a test script.
//
// Pricing convention: product prices are VAT-EXCLUSIVE. The 12% VAT is added
// on top of the cart subtotal at checkout, so a ₱100.00 item becomes ₱112.00.
// Senior/PWD sales are VAT-exempt and instead get 20% off the net subtotal.

if (!defined('DEFAULT_VAT_RATE')) {
    define('DEFAULT_VAT_RATE', 0.12);       // 12% Philippine VAT
}
if (!defined('DEFAULT_DISCOUNT_RATE')) {
    define('DEFAULT_DISCOUNT_RATE', 0.20);  // RA 9994 / RA 10754 senior & PWD discount
}

/**
 * Compute the full VAT / discount breakdown for a cart.
 *
 * @param array       $items        Each item: ['price' => float, 'quantity' => int]
 * @param float       $taxRate      VAT rate as a decimal (0.12 = 12%)
 * @param string|null $customerType 'regular', 'senior' or 'pwd'
 * @param string|null $idNumber     OSCA / PWD ID (required for senior & pwd)
 *
 * @throws InvalidArgumentException on invalid items or a missing discount ID.
 */
function calculate_tax_and_totals(
    array $items,
    float $taxRate = DEFAULT_VAT_RATE,
    ?string $customerType = null,
    ?string $idNumber = null
): array {
    if ($taxRate < 0) {
        throw new InvalidArgumentException('Tax rate cannot be negative.');
    }
    if (!is_finite($taxRate)) {
        throw new InvalidArgumentException('Tax rate must be a finite number.');
    }

    $grossSubtotal = 0.0;
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['price'], $item['quantity'])) {
            throw new InvalidArgumentException('Each item needs a price and a quantity.');
        }

        $price    = (float) $item['price'];
        $quantity = (int) $item['quantity'];

        if (!is_finite($price) || $price < 0) {
            throw new InvalidArgumentException('Item price must be zero or greater.');
        }
        if ($quantity < 1) {
            throw new InvalidArgumentException('Item quantity must be at least 1.');
        }

        $grossSubtotal += $price * $quantity;
    }
    $grossSubtotal = round($grossSubtotal, 2);

    $normalizedType = strtolower(trim((string) $customerType));
    if (!in_array($normalizedType, ['regular', 'senior', 'pwd'], true)) {
        $normalizedType = 'regular';
    }
    $isDiscounted = $normalizedType === 'senior' || $normalizedType === 'pwd';

    if ($isDiscounted) {
        if (empty(trim((string) $idNumber))) {
            throw new InvalidArgumentException('ID Number is required for Senior Citizen / PWD discounts.');
        }

        // Prices are net of VAT, so the whole sale is the VAT-exempt amount.
        $vatableSales   = 0.0;
        $vatExemptSales = $grossSubtotal;
        $vatAmount      = 0.0;
        $taxRateApplied = 0.0;
        $discountAmount = round($vatExemptSales * DEFAULT_DISCOUNT_RATE, 2);
        $netTotal       = round($vatExemptSales - $discountAmount, 2);
    } else {
        // VAT is added on top of the VAT-exclusive subtotal.
        $vatableSales   = $grossSubtotal;
        $vatExemptSales = 0.0;
        $vatAmount      = round($vatableSales * $taxRate, 2);
        $discountAmount = 0.0;
        $taxRateApplied = $taxRate;
        $netTotal       = round($vatableSales + $vatAmount, 2);
    }

    return [
        'gross_subtotal'   => $grossSubtotal,
        'vatable_sales'    => round($vatableSales, 2),
        'vat_exempt_sales' => round($vatExemptSales, 2),
        'tax_rate_applied' => $taxRateApplied,
        'vat_amount'       => round($vatAmount, 2),
        'customer_type'    => $normalizedType,
        'discount_id'      => $isDiscounted ? trim((string) $idNumber) : null,
        'discount_amount'  => round($discountAmount, 2),
        'net_total'        => round($netTotal, 2),
    ];
}
