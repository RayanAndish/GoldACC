<?php

namespace App\Models;

class PhysicalSettlementItem
{
    public ?int $id = null;
    public ?int $settlement_id = null;
    public ?int $product_id = null;
    public ?float $weight_scale = null;
    public ?float $carat = null;
    public ?float $weight_750 = null;
    public ?string $tag_number = null;
    public ?int $assay_office_id = null;
    public ?int $source_inventory_ledger_id = null;
    public ?string $notes = null;
}