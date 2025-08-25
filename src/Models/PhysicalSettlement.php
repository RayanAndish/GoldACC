<?php

namespace App\Models;

class PhysicalSettlement
{
    public ?int $id = null;
    public ?int $contact_id = null;
    public ?string $settlement_date = null;
    public ?string $direction = null; // 'inflow' or 'outflow'
    public ?string $notes = null;
}