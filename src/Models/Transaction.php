<?php

namespace App\Models;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Class Transaction
 * Represents a record from the 'transactions' table.
 */
class Transaction {
    // All public properties that match the 'transactions' table columns
    public ?int $id = null;
    public ?string $transaction_type = null;
    public ?string $transaction_date = null;
    public ?int $counterparty_contact_id = null;
    public ?float $mazaneh_price = null;
    public ?float $total_items_value_rials = null;
    public ?float $total_profit_wage_commission_rials = null;
    public ?float $total_general_tax_rials = null;
    public ?float $total_before_vat_rials = null;
    public ?float $total_vat_rials = null;
    public ?float $final_payable_amount_rials = null;
    public ?float $usd_rate_ref = null;
    public ?string $delivery_status = null;
    public ?string $notes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?int $created_by_user_id = null;
    public ?int $updated_by_user_id = null;
    // Add any other columns from your 'transactions' table here if they are missing.
    public ?string $delivery_person = null;
    public ?string $delivery_date = null;
    public ?int $product_id = null;
    public ?float $calculated_weight_grams = null;
    public ?float $price_per_reference_gram = null;


    /**
     * @var TransactionItem[] Array to hold associated TransactionItem objects.
     * This is NOT a database column.
     */
    public array $items = [];

    /**
     * @var Contact|null Optional: To hold the Contact object if joined.
     * This is NOT a database column.
     */
    public ?Contact $counterparty = null;

    public function __construct(array $data = []) {
        // Hydrate properties from data array
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
    
    /**
     * CRITICAL FIX: Converts the object to an associative array suitable for the database.
     * This version excludes related objects like 'items' and 'counterparty' which are not DB columns.
     *
     * @return array
     */
    public function toArray(): array
    {
        // Start with all public properties
        $dbData = get_object_vars($this);

        // **CRITICAL STEP**: Unset properties that are NOT columns in the 'transactions' table.
        // This prevents the "Unknown column" SQL error.
        unset($dbData['items']);
        unset($dbData['counterparty']);

        return $dbData;
    }

    // The rest of the methods from your original file are kept as they were.
    public function addItem(TransactionItem $item): void {
        $this->items[] = $item;
    }

    public function calculateItemsTotal(): float {
        $total = 0.0;
        foreach ($this->items as $item) {
             $total += (float)($item->total_value_rials ?? 0.0);
        }
        return round($total, 2);
    }

    public static function mapFormToDb(array $formData): array
    {
        // This static method is kept as it was in your original file.
        $mapping = [
            'id' => 'id',
            'transaction_type' => 'transaction_type',
            'product_id' => 'product_id',
            'transaction_date' => 'transaction_date',
            'calculated_weight_grams' => 'calculated_weight_grams',
            'price_per_reference_gram' => 'price_per_reference_gram',
            'total_value_rials' => 'total_value_rials',
            'usd_rate_ref' => 'usd_rate_ref',
            'counterparty_contact_id' => 'counterparty_contact_id',
            'total_items_value_rials' => 'total_items_value_rials',
            'notes' => 'notes',
            'created_at' => 'created_at',
            'updated_by_user_id' => 'updated_by_user_id',
            'updated_at' => 'updated_at',
            'delivery_status' => 'delivery_status',
            'delivery_person' => 'delivery_person',
            'delivery_date' => 'delivery_date',
            'created_by_user_id' => 'created_by_user_id',
            'total_profit_wage_commission_rials' => 'total_profit_wage_commission_rials',
            'total_general_tax_rials' => 'total_general_tax_rials',
            'total_before_vat_rials' => 'total_before_vat_rials',
            'total_vat_rials' => 'total_vat_rials',
            'final_payable_amount_rials' => 'final_payable_amount_rials',
            'mazaneh_price' => 'mazaneh_price',
        ];
        $dbData = [];
        foreach ($mapping as $formField => $dbField) {
            if (isset($formData[$formField])) {
                $dbData[$dbField] = $formData[$formField];
            }
        }
        return $dbData;
    }
}
