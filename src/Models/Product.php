<?php

namespace App\Models;

/**
 * Class Product (Refactored for New Tax Logic)
 * Represents a record from the 'products' table.
 */
class Product
{
    public ?int $id = null;
    public string $name = '';
    public ?int $category_id = null;
    public ?string $product_code = null;
    public string $unit_of_measure = 'gram';
    public ?string $description = null;
    public ?float $default_carat = null;
    public bool $is_active = true;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    // Capital fields
    public ?float $capital_quantity = null;
    public ?float $capital_weight_grams = null;
    public ?int $capital_reference_carat = 750;

    // **NEW**: New tax columns that replace old ones
    public string $vat_base_type = 'NONE'; // ENUM in DB: 'NONE', 'WAGE_PROFIT', 'PROFIT_ONLY'
    public string $general_tax_base_type = 'NONE'; // ENUM in DB: 'NONE', 'WAGE_PROFIT', 'PROFIT_ONLY'
    public ?float $tax_rate = null; // General tax rate percentage
    public ?float $vat_rate = null; // VAT rate percentage

    // Relational property (not a DB column)
    public ?ProductCategory $category = null;

    // **DEPRECATED**: These properties are kept for compatibility during transition
    // but should not be used for new logic. They correspond to columns that will be removed.
    public ?float $quantity = null;
    public ?float $weight = null;
    public ?int $coin_year = null;

    /**
     * Constructor to hydrate the object from an associative array.
     */
    public function __construct(array $data = [])
    {
        // Set default values for new instances
        $this->is_active = true;
        $this->name = '';
        $this->unit_of_measure = 'gram';
        $this->capital_reference_carat = 750;
        $this->vat_base_type = 'NONE';
        $this->general_tax_base_type = 'NONE';

        if (!empty($data)) {
            // Hydrate properties that exist in the class from the data array
            foreach ($data as $key => $value) {
                if (property_exists($this, $key)) {
                    // Special casting for boolean values for robustness
                    if ($key === 'is_active') {
                        $this->{$key} = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    } else {
                        $this->{$key} = $value;
                    }
                }
            }
            
            // Hydrate the related category object if data is available from a JOIN query
            if (isset($data['category_id'], $data['category_name'], $data['base_category'])) {
                $this->category = new ProductCategory([
                    'id' => (int)$data['category_id'],
                    'name' => $data['category_name'],
                    'code' => $data['category_code'] ?? null,
                    'base_category' => $data['base_category']
                ]);
            }
        }
    }

    /**
     * Converts the object to an associative array, ready for the database or JSON response.
     * @return array
     */
    public function toArray(): array
    {
        // This simple method returns all public properties of the object.
        // It's clean because the model properties directly reflect the desired output.
        $properties = get_object_vars($this);

        // If the category object exists, convert it to an array as well
        if ($this->category instanceof ProductCategory) {
            $properties['category'] = $this->category->toArray();
        }

        return $properties;
    }
}