<?php
// src/Models/InventoryLedger.php
namespace App\Models;

use DateTime; // Optional if you need DateTime objects

/**
 * Class InventoryLedger
 * Represents a record from the 'inventory_ledger' table.
 * REVISED: Added missing properties to fully map to the database schema.
 */
class InventoryLedger {
    public ?int $id = null;
    public ?int $product_id = null;
    public ?int $transaction_id = null;
    public ?int $transaction_item_id = null;
    public ?string $movement_date = null; // Stored as datetime in DB
    public ?string $movement_type = null; // varchar(50)
    public ?int $related_transaction_id = null; // DB column `related_transaction_id` - might be redundant with `transaction_id`
    public ?int $related_initial_balance_id = null;
    public ?int $related_payment_id = null;
    public ?string $description = null;
    public ?float $quantity_in = null;
    public ?float $quantity_out = null;
    public ?float $balance_quantity_after_movement = null; // Important for current stock
    public ?float $weight_grams_in = null;
    public ?float $weight_grams_out = null;
    public ?float $balance_weight_grams_after_movement = null; // Important for current stock
    public ?int $carat = null;
    public ?float $price_per_unit_at_movement = null;
    public ?float $total_value_in = null;
    public ?float $total_value_out = null;
    public ?float $balance_total_value_after_movement = null; // Cost of inventory
    public ?int $created_by_user_id = null;
    public ?string $created_at = null;
    
    // Properties from your InventoryLedger model from InventoryController provided in THIS query.
    // Ensure these map correctly. Your `client-database.json` has `change_quantity` instead of `quantity_in`/`quantity_out`.
    // It's likely that change_quantity/weight is used to calculate after_movement balance, not in/out specifically.
    // Based on `client-database.json` and your current `InventoryLedger.txt`:
    public ?int $change_quantity = null;
    public ?float $change_weight_grams = null;
    public ?int $quantity_after = null; // This one is crucial. From `balance_quantity_after_movement` in original schema, aliased or used interchangeably?
    public ?float $weight_grams_after = null; // This one is crucial. From `balance_weight_grams_after_movement`
    public ?string $event_type = null; // Conflicting with movement_type? Client SQL uses event_type enum.
    public ?string $event_date = null; // Conflicting with movement_date? Client SQL uses event_date.
    public ?string $notes = null;

    // Optional: To hold related objects if joined
    public ?Product $product = null;
    public ?TransactionItem $transactionItem = null; // Unlikely to need directly on ledger, usually join for info

    public function __construct(array $data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                // Special handling for boolean fields (if any) or int casts
                if (in_array($key, ['id', 'product_id', 'transaction_id', 'transaction_item_id', 'related_transaction_id', 'related_initial_balance_id', 'related_payment_id', 'carat', 'created_by_user_id', 'change_quantity', 'quantity_after'])) {
                    $this->{$key} = isset($value) ? (int)$value : null;
                }
                // Floating point values
                elseif (in_array($key, ['quantity_in', 'quantity_out', 'balance_quantity_after_movement', 'weight_grams_in', 'weight_grams_out', 'balance_weight_grams_after_movement', 'price_per_unit_at_movement', 'total_value_in', 'total_value_out', 'balance_total_value_after_movement', 'change_weight_grams', 'weight_grams_after'])) {
                    $this->{$key} = isset($value) ? (float)$value : null;
                }
                // Strings (dates/text) - direct assignment
                else {
                    $this->{$key} = $value;
                }
            }
        }
        
        // Hydrate related objects if data for them exists and they're useful here
        if ($this->product_id !== null && isset($data['product_name'])) { // Check `product_name` exists in join
            // Simplify, only pass the basic info if it's used elsewhere
            $this->product = new Product([
                'id' => $this->product_id, 
                'name' => $data['product_name'] ?? null,
                // Add more product properties from $data if necessary.
            ]); 
        }
        // $this->transactionItem would require more joins and checks. Usually for ledger you just need transaction_id.
    }

    public function toArray(): array {
        $props = get_object_vars($this);
        unset($props['product'], $props['transactionItem']); // Exclude objects for basic array output.
        return $props;
    }
}