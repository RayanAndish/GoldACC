<?php

namespace App\Models;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Class Transaction
 * Represents a record from the 'transactions' table.
 */
class Transaction {
    public ?int $id = null;
    public ?string $transaction_type = null; // 'buy', 'sell'
    public ?string $transaction_date = null; // Keep as string from DB or convert to DateTime?
    public ?int $counterparty_contact_id = null;
    public ?float $total_items_value_rials = null; // Sum of items' total_value_rials
    public ?float $usd_rate_ref = null;
    public ?string $delivery_status = null; // 'pending_receipt', 'pending_delivery', 'completed', 'cancelled'
    public ?string $notes = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * @var TransactionItem[] Array to hold associated TransactionItem objects.
     */
    public array $items = [];

    // Optional: To hold the Contact object if joined
    public ?Contact $counterparty = null;

    public function __construct(array $data = []) {
        // Set defaults
        $this->items = [];
        // Default date could be set here if needed, but usually comes from DB or form
        // $this->transaction_date = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tehran')))->format('Y-m-d H:i:s');

        if (!empty($data)) {
            $this->id = isset($data['id']) ? (int)$data['id'] : null;
            $this->transaction_type = $data['transaction_type'] ?? null;
            $this->transaction_date = $data['transaction_date'] ?? null; // Keep as string for now
            $this->counterparty_contact_id = isset($data['counterparty_contact_id']) ? (int)$data['counterparty_contact_id'] : null;
            $this->total_items_value_rials = isset($data['total_items_value_rials']) ? (float)$data['total_items_value_rials'] : null;
            $this->usd_rate_ref = isset($data['usd_rate_ref']) ? (float)$data['usd_rate_ref'] : null;
            $this->delivery_status = $data['delivery_status'] ?? null;
            $this->notes = $data['notes'] ?? null;
            $this->created_at = $data['created_at'] ?? null;
            $this->updated_at = $data['updated_at'] ?? null;

            // If counterparty data is passed directly (e.g., from a JOIN)
            if ($this->counterparty_contact_id !== null && isset($data['counterparty_name'])) {
                 $this->counterparty = new Contact([ // Assuming Contact model exists
                    'id' => $this->counterparty_contact_id,
                    'name' => $data['counterparty_name'],
                    // Add other relevant fields if joined
                 ]);
            }

            // If items data is passed (e.g., an array of arrays)
            if (isset($data['items']) && is_array($data['items'])) {
                 foreach ($data['items'] as $itemData) {
                    if (is_array($itemData)) {
                        $this->items[] = new TransactionItem($itemData); // Assuming TransactionItem model exists
                    }
                 }
            }
        }
    }

    /**
     * Adds a TransactionItem object to the transaction.
     * @param TransactionItem $item
     */
    public function addItem(TransactionItem $item): void {
        $this->items[] = $item;
        // Optionally recalculate total_items_value_rials here if needed
    }

    /**
     * Calculates the total value based on the items currently attached.
     * Note: This might differ from total_items_value_rials fetched from DB if items are modified after loading.
     * @return float
     */
    public function calculateItemsTotal(): float {
        $total = 0.0;
        foreach ($this->items as $item) {
            // Ensure total_value_rials is treated as float
             $total += (float)($item->total_value_rials ?? 0.0);
        }
        // Using round for potentially better float representation, but be cautious with float precision
        return round($total, 2);
    }


    public function toArray(bool $includeItems = true): array {
        $array = [
            'id' => $this->id,
            'transaction_type' => $this->transaction_type,
            'transaction_date' => $this->transaction_date,
            'counterparty_contact_id' => $this->counterparty_contact_id,
            'total_items_value_rials' => $this->total_items_value_rials,
            'usd_rate_ref' => $this->usd_rate_ref,
            'delivery_status' => $this->delivery_status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'counterparty' => $this->counterparty ? $this->counterparty->toArray() : null, // Assuming Contact has toArray
        ];

        if ($includeItems) {
             $array['items'] = [];
             foreach ($this->items as $item) {
                $array['items'][] = $item->toArray(); // Assuming TransactionItem has toArray
             }
        }

        return $array;
    }

    /**
     * مپینگ مرکزی و دقیق داده‌های فرم تراکنش به فیلدهای جدول transactions
     * @param array $formData داده خام فرم
     * @return array داده آماده برای ذخیره در جدول
     */
    public static function mapFormToDb(array $formData): array
    {
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