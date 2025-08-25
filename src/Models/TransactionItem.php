<?php

namespace App\Models;

/**
 * REFACTORED: TransactionItem Model
 * Represents a clean Data Transfer Object (DTO) for a record from the 'transaction_items' table.
 * It only contains properties that directly map to the database schema.
 * Mapping from dynamic form fields is handled by the Controller.
 */
class TransactionItem
{
    // --- Database Columns ---
    public ?int $id = null;
    public ?int $transaction_id = null;
    public ?int $product_id = null;
    public ?int $quantity = null;
    public ?float $weight_grams = null;
    public ?float $weight_750 = null; // Calculated field, good to have for consistency
    public ?int $carat = null;
    public ?float $unit_price_rials = null;
    public ?float $total_value_rials = null;
    public ?string $tag_number = null;
    public ?int $assay_office_id = null;
    public ?int $coin_year = null;
    public ?string $seal_name = null;
    public ?bool $is_bank_coin = null;
    public ?float $ajrat_rials = null;
    public ?string $workshop_name = null;
    public ?float $stone_weight_grams = null;
    public ?string $description = null;
    public ?float $profit_percent = null;
    public ?float $profit_amount = null;
    public ?float $fee_percent = null;
    public ?float $fee_amount = null;
    public ?float $general_tax = null;
    public ?float $vat = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    // Optional: To hold related objects if joined from a repository query
    public ?Product $product = null;

    /**
     * Constructor to hydrate the object from an associative array.
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Converts the object to an associative array, ready for the database.
     * @return array
     */
    public function toArray(): array
    {
        // get_object_vars returns all public properties
        $data = get_object_vars($this);
        // Unset related objects that are not part of the table schema
        unset($data['product']);
        return $data;
    }
}
