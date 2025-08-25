<?php

namespace App\Models; // Namespace صحیح

/**
 * Class ProductCategory
 * Represents a record from the 'product_categories' table.
 */
class ProductCategory {
    public ?int $id = null;
    public ?string $name = null;
    public ?string $code = null;
    public ?string $base_category = null;
    public ?string $description = null;
    public ?bool $requires_carat = null;
    public ?bool $requires_weight = null;
    public ?bool $requires_quantity = null;
    public ?bool $requires_coin_year = null;
    public ?string $unit_of_measure = null;
    public ?string $created_at = null;
    public ?bool $is_active = null;
    public ?string $updated_at = null;

    /**
     * Constructor to hydrate the object from an associative array (e.g., from DB).
     *
     * @param array $data
     */
    public function __construct(array $data = []) {
        // Set defaults first
        $this->name = '';
        $this->requires_carat = false;
        $this->requires_weight = false;
        $this->requires_quantity = true;
        $this->requires_coin_year = false;
        $this->is_active = true;
        $this->unit_of_measure = $data['unit_of_measure'] ?? 'gram';

        // Then hydrate from data if provided
        if (!empty($data)) {
            $this->id = isset($data['id']) ? (int)$data['id'] : null;
            $this->name = $data['name'] ?? ''; // Use ?? for safety
            $this->code = $data['code'] ?? null;
            $this->base_category = $data['base_category'] ?? null;
            $this->description = $data['description'] ?? null;
            // Ensure boolean values are correctly cast using filter_var for robustness
            $this->requires_carat = isset($data['requires_carat']) ? filter_var($data['requires_carat'], FILTER_VALIDATE_BOOLEAN) : false;
            $this->requires_weight = isset($data['requires_weight']) ? filter_var($data['requires_weight'], FILTER_VALIDATE_BOOLEAN) : false;
            $this->requires_quantity = isset($data['requires_quantity']) ? filter_var($data['requires_quantity'], FILTER_VALIDATE_BOOLEAN) : true;
            $this->requires_coin_year = isset($data['requires_coin_year']) ? filter_var($data['requires_coin_year'], FILTER_VALIDATE_BOOLEAN) : false;
            $this->unit_of_measure = $data['unit_of_measure'] ?? 'gram';
            $this->created_at = $data['created_at'] ?? null;
            $this->updated_at = $data['updated_at'] ?? null;
            $this->is_active = isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true;
        }
    }

    // You can add other methods here if needed, for example, to convert to array:
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'base_category' => $this->base_category,
            'description' => $this->description,
            'requires_carat' => $this->requires_carat,
            'requires_weight' => $this->requires_weight,
            'requires_quantity' => $this->requires_quantity,
            'requires_coin_year' => $this->requires_coin_year,
            'unit_of_measure' => $this->unit_of_measure,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_active' => $this->is_active,
        ];
    }
}