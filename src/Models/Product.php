<?php

namespace App\Models;

/**
 * Class Product
 * Represents a record from the 'products' table.
 */
class Product {
    public ?int $id = null;
    public string $name = '';
    public ?int $category_id = null;
    public ?string $product_code = null;
    public string $unit_of_measure = 'gram'; // Added: Default 'gram'
    public ?string $description = null;
    public ?float $default_carat = null; // Using float, adjust if a more precise type like decimal is needed and handled
    public bool $is_active = true;
    public ?float $quantity = null;
    public ?float $weight = null;
    public ?int $coin_year = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    // New capital fields
    public ?float $capital_quantity = null;
    public ?float $capital_weight_grams = null;
    public ?int $capital_reference_carat = 750; // Default to 750

    // Optional: To hold the category object if joined
    public ?ProductCategory $category = null;

    public ?bool $tax_enabled = null;
    public ?float $tax_rate = null;
    public ?bool $vat_enabled = null;
    public ?float $vat_rate = null;

    public function __construct(array $data = []) {
        // Set defaults first
        $this->is_active = true; // Default active state
        $this->name = '';        // Default name
        $this->category_id = null; // Default category
        $this->unit_of_measure = 'gram'; // Added: Default UOM
        $this->capital_reference_carat = 750; // Default capital carat

        // Then hydrate from data if provided
        if (!empty($data)) {
            $this->id = isset($data['id']) ? (int)$data['id'] : null;
            $this->name = $data['name'] ?? ''; // Use ?? for safety, though default is set
            $this->category_id = isset($data['category_id']) ? (int)$data['category_id'] : null;
            $this->product_code = $data['product_code'] ?? null;
            $this->unit_of_measure = $data['unit_of_measure'] ?? 'gram'; // Added: Hydrate UOM
            $this->description = $data['description'] ?? null;
            $this->default_carat = isset($data['default_carat']) ? (float)$data['default_carat'] : null;
            $this->is_active = isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true;
            $this->quantity = isset($data['quantity']) ? (float)$data['quantity'] : null;
            $this->weight = isset($data['weight']) ? (float)$data['weight'] : null;
            $this->coin_year = isset($data['coin_year']) ? (int)$data['coin_year'] : null;
            $this->created_at = $data['created_at'] ?? null;
            $this->updated_at = $data['updated_at'] ?? null;

            // Hydrate new capital fields
            $this->capital_quantity = isset($data['capital_quantity']) ? (float)$data['capital_quantity'] : null;
            $this->capital_weight_grams = isset($data['capital_weight_grams']) ? (float)$data['capital_weight_grams'] : null;
            $this->capital_reference_carat = isset($data['capital_reference_carat']) ? (int)$data['capital_reference_carat'] : 750;

            // If category data is passed directly (e.g., from a JOIN)
            if ($this->category_id !== null && isset($data['category_name'])) {
                $this->category = new ProductCategory([
                    'id' => $this->category_id,
                    'name' => $data['category_name'],
                    'code' => $data['category_code'] ?? null, // Example
                ]);
            }

            $this->vat_enabled = isset($data['vat_enabled']) ? filter_var($data['vat_enabled'], FILTER_VALIDATE_BOOLEAN) : null;
            $this->vat_rate = isset($data['vat_rate']) ? (float)$data['vat_rate'] : null;
            $this->tax_enabled = isset($data['tax_enabled']) ? filter_var($data['tax_enabled'], FILTER_VALIDATE_BOOLEAN) : null;
            $this->tax_rate = isset($data['tax_rate']) ? (float)$data['tax_rate'] : null;
        }
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'product_code' => $this->product_code,
            'unit_of_measure' => $this->unit_of_measure, // Added
            'description' => $this->description,
            'default_carat' => $this->default_carat,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'quantity' => $this->quantity,
            'weight' => $this->weight,
            'coin_year' => $this->coin_year,
            'capital_quantity' => $this->capital_quantity,
            'capital_weight_grams' => $this->capital_weight_grams,
            'capital_reference_carat' => $this->capital_reference_carat,
            'category' => $this->category ? $this->category->toArray() : null,
            'tax_enabled' => $this->tax_enabled,
            'tax_rate' => $this->tax_rate,
            'vat_enabled' => $this->vat_enabled,
            'vat_rate' => $this->vat_rate,
        ];
    }
}