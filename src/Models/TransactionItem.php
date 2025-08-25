<?php

namespace App\Models;

/**
 * FINAL REFACTORED: TransactionItem Model
 * Represents a clean Data Transfer Object (DTO) for a record from the 'transaction_items' table.
 * REVISED: Added new properties to match the updated database schema for manufactured goods.
 */
class TransactionItem
{
    public ?int $id = null;
    public ?int $transaction_id = null;
    public ?int $product_id = null;
    public ?int $quantity = null;
    public ?float $weight_grams = null;
    public ?int $carat = null;
    public ?float $unit_price_rials = null;
    public ?float $total_value_rials = null;
    public ?float $profit_percent = null;
    public ?float $fee_percent = null;
    public ?float $ajrat_percent = null;
    public ?string $tag_number = null;
    public ?string $tag_type = null; // جدید
    public ?int $assay_office_id = null;
    public ?int $coin_year = null;
    public ?string $seal_name = null;
    public ?bool $is_bank_coin = null;
    public ?float $ajrat_rials = null;
    public ?string $workshop_name = null;
    public ?float $stone_weight_grams = null;
    public ?string $manufactured_item_type = null;
    public ?int $has_attachments = 0;
    public ?string $attachment_type = null;
    public ?string $jewelry_type = null; // جدید
    public ?string $jewelry_color = null; // جدید
    public ?string $jewelry_quality = null; // جدید
    public ?string $description = null;
    public ?float $profit_amount_rials = null; // جدید
    public ?float $fee_amount_rials = null; // جدید
    public ?float $general_tax_rials = null; // جدید
    public ?float $vat_rials = null; // جدید
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
        return get_object_vars($this);
    }
}