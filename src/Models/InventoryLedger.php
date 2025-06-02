<?php

namespace App\Models;

/**
 * Class InventoryLedger
 * Represents a record from the 'inventory_ledger' table.
 */
class InventoryLedger {
    public ?int $id = null;
    public ?int $product_id = null;
    public ?int $transaction_item_id = null;
    public ?int $change_quantity = null;
    public ?float $change_weight_grams = null;
    public ?int $quantity_after = null;
    public ?float $weight_grams_after = null;
    public ?string $event_type = null;
    public ?string $event_date = null; // Keep as string from DB or convert to DateTime?
    public ?string $notes = null;

    // Optional: To hold related objects if joined
    public ?Product $product = null;
    public ?TransactionItem $transactionItem = null;

    public function __construct(array $data = []) {
        if (!empty($data)) {
            $this->id = isset($data['id']) ? (int)$data['id'] : null;
            $this->product_id = isset($data['product_id']) ? (int)$data['product_id'] : null;
            $this->transaction_item_id = isset($data['transaction_item_id']) ? (int)$data['transaction_item_id'] : null;
            $this->change_quantity = isset($data['change_quantity']) ? (int)$data['change_quantity'] : null;
            $this->change_weight_grams = isset($data['change_weight_grams']) ? (float)$data['change_weight_grams'] : null;
            $this->quantity_after = isset($data['quantity_after']) ? (int)$data['quantity_after'] : null;
            $this->weight_grams_after = isset($data['weight_grams_after']) ? (float)$data['weight_grams_after'] : null;
            $this->event_type = $data['event_type'] ?? null;
            $this->event_date = $data['event_date'] ?? null;
            $this->notes = $data['notes'] ?? null;

            // Example of hydrating related objects if their data is passed
            if ($this->product_id !== null && isset($data['product_name'])) {
                $this->product = new Product($data); // Simplified; adjust as needed
            }
            if ($this->transaction_item_id !== null && isset($data['item_description'])) { // Assuming a field like 'item_description'
                $this->transactionItem = new TransactionItem($data); // Simplified
            }
        }
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'transaction_item_id' => $this->transaction_item_id,
            'change_quantity' => $this->change_quantity,
            'change_weight_grams' => $this->change_weight_grams,
            'quantity_after' => $this->quantity_after,
            'weight_grams_after' => $this->weight_grams_after,
            'event_type' => $this->event_type,
            'event_date' => $this->event_date,
            'notes' => $this->notes,
            'product' => $this->product ? $this->product->toArray() : null,
            'transactionItem' => $this->transactionItem ? $this->transactionItem->toArray() : null,
        ];
    }
} 