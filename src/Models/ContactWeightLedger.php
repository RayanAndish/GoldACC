<?php

namespace App\Models;

class ContactWeightLedger
{
    public ?int $id = null;
    public ?int $contact_id = null;
    public ?int $product_category_id = null;
    public ?string $event_date = null;
    public ?string $event_type = null;
    public ?float $change_weight_grams = null;
    public ?float $balance_after_grams = null;
    public ?int $related_transaction_id = null;
    public ?int $related_settlement_id = null;
    public ?string $notes = null;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}