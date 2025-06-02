<?php

namespace App\Models;

/**
 * REFACTORED: TransactionItem Model
 * Represents a record from the 'transaction_items' table.
 * This model is now a clean Data Transfer Object (DTO) that directly maps to the database schema.
 * All complex, non-existent properties have been removed.
 */
class TransactionItem {
    // --- Database Columns ---
    public ?int $id = null;
    public ?int $transaction_id = null;
    public ?int $product_id = null;
    public ?int $quantity = null;
    public ?float $weight_grams = null;
    public ?int $carat = null;
    public ?float $unit_price_rials = null;
    public ?float $total_value_rials = null;
    public ?string $tag_number = null;
    public ?int $assay_office_id = null;
    public ?int $coin_year = null;
    public ?string $seal_name = null; // Corrected column name from 'coin_seal_type'
    public ?bool $is_bank_coin = null;
    public ?float $ajrat_rials = null; // Corrected column name from 'wage_amount'
    public ?string $workshop_name = null;
    public ?float $stone_weight_grams = null;
    public ?string $description = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    // NOTE: The numerous 'item_*_melted', 'item_*_manufactured' properties have been removed
    // as they do not exist in the database table. The mapping from the dynamic form
    // to these standard properties will now be handled in the TransactionController.
    
    // Optional: To hold related objects if joined from a repository query
    public ?Product $product = null;
    public ?AssayOffice $assayOffice = null;

    /**
     * Constructor to hydrate the object from an associative array (e.g., from DB).
     *
     * @param array $data
     */
    public function __construct(array $data = []) {
        if (!empty($data)) {
            $this->id = isset($data['id']) ? (int)$data['id'] : null;
            $this->transaction_id = isset($data['transaction_id']) ? (int)$data['transaction_id'] : null;
            $this->product_id = isset($data['product_id']) ? (int)$data['product_id'] : null;
            $this->quantity = isset($data['quantity']) ? (int)$data['quantity'] : null;
            $this->weight_grams = isset($data['weight_grams']) ? (float)$data['weight_grams'] : null;
            $this->carat = isset($data['carat']) ? (int)$data['carat'] : null;
            $this->unit_price_rials = isset($data['unit_price_rials']) ? (float)$data['unit_price_rials'] : null;
            $this->total_value_rials = isset($data['total_value_rials']) ? (float)$data['total_value_rials'] : null;
            $this->tag_number = $data['tag_number'] ?? null;
            $this->assay_office_id = isset($data['assay_office_id']) ? (int)$data['assay_office_id'] : null;
            $this->coin_year = isset($data['coin_year']) ? (int)$data['coin_year'] : null;
            $this->seal_name = $data['seal_name'] ?? null;
            $this->is_bank_coin = isset($data['is_bank_coin']) ? filter_var($data['is_bank_coin'], FILTER_VALIDATE_BOOLEAN) : null;
            $this->ajrat_rials = isset($data['ajrat_rials']) ? (float)$data['ajrat_rials'] : null;
            $this->workshop_name = $data['workshop_name'] ?? null;
            $this->stone_weight_grams = isset($data['stone_weight_grams']) ? (float)$data['stone_weight_grams'] : null;
            $this->description = $data['description'] ?? null;
            $this->created_at = $data['created_at'] ?? null;
            $this->updated_at = $data['updated_at'] ?? null;
        }
    }

    /**
     * Converts the object to an associative array.
     *
     * @return array
     */
    public function toArray(): array {
        return get_object_vars($this);
    }
    public static function mapFormFieldsToDbFields(string $group): array
    {
        $mappings = [
            'MELTED' => [
                'item_carat_melted' => 'carat',
                'item_weight_scale_melted' => 'weight_grams',
                'item_weight_750_melted' => null, // محاسباتی
                'item_unit_price_melted' => 'unit_price_rials',
                'item_total_price_melted' => 'total_value_rials',
                'item_tag_number_melted' => 'tag_number',
                'item_assay_office_melted' => 'assay_office_id',
                'item_profit_percent_melted' => 'profit_percent',
                'item_profit_amount_melted' => 'profit_amount',
                'item_fee_percent_melted' => 'fee_percent',
                'item_fee_amount_melted' => 'fee_amount',
            ],
            'MANUFACTURED' => [
                'item_carat_manufactured' => 'carat',
                'item_weight_scale_manufactured' => 'weight_grams',
                'item_quantity_manufactured' => 'quantity',
                'item_weight_750_manufactured' => null,
                'item_unit_price_manufactured' => 'unit_price_rials',
                'item_total_price_manufactured' => 'total_value_rials',
                'item_type_manufactured' => 'item_type_manufactured',
                'item_has_attachments_manufactured' => 'manufactured_has_attachments',
                'item_attachment_type_manufactured' => 'manufactured_attachment_type',
                'item_attachment_weight_manufactured' => 'manufactured_attachment_weight',
                'item_workshop_manufactured' => 'workshop_name',
                'item_manufacturing_fee_rate_manufactured' => 'wage_amount',
                'item_profit_percent_manufactured' => 'profit_percent',
                'item_profit_amount_manufactured' => 'profit_amount',
                'item_fee_percent_manufactured' => 'fee_percent',
                'item_fee_amount_manufactured' => 'fee_amount',
            ],
            'COIN' => [
                'item_quantity_coin' => 'quantity',
                'item_unit_price_coin' => 'unit_price_rials',
                'item_total_price_coin' => 'total_value_rials',
                'item_coin_year_coin' => 'coin_year',
                'item_type_coin' => 'coin_seal_type',
                'item_vacuum_name_coin' => 'coin_vacuum_brand',
                'item_profit_percent_coin' => 'profit_percent',
                'item_profit_amount_coin' => 'profit_amount',
            ],
            'GOLDBULLION' => [
                'item_carat_goldbullion' => 'carat',
                'item_weight_scale_goldbullion' => 'weight_grams',
                'item_weight_750_goldbullion' => null,
                'item_unit_price_goldbullion' => 'unit_price_rials',
                'item_total_price_goldbullion' => 'total_value_rials',
                'item_bullion_number_goldbullion' => 'bullion_serial_number',
                'item_manufacturer_goldbullion' => 'bullion_manufacturer',
                'item_profit_percent_goldbullion' => 'profit_percent',
                'item_profit_amount_goldbullion' => 'profit_amount',
                'item_fee_percent_goldbullion' => 'fee_percent',
                'item_fee_amount_goldbullion' => 'fee_amount',
            ],
            'JEWELRY' => [
                'item_weight_carat_jewelry' => 'weight_grams',
                'item_quantity_jewelry' => 'quantity',
                'item_unit_price_jewelry' => 'unit_price_rials',
                'item_total_price_jewelry' => 'total_value_rials',
                'item_type_jewelry' => 'description', // یا فیلد خاص اگر نیاز است
                'item_color_jewelry' => null,
                'item_quality_grade_jewelry' => null,
                'item_profit_percent_jewelry' => 'profit_percent',
                'item_profit_amount_jewelry' => 'profit_amount',
                'item_fee_percent_jewelry' => 'fee_percent',
                'item_fee_amount_jewelry' => 'fee_amount',
            ],
        ];
        return $mappings[$group] ?? [];
    }
    public static function mapFormToDb(array $formItem, string $group): array
    {
        $fields = [
            'id','transaction_id','product_id','quantity','weight_grams','item_weight_carat_jewelry','item_profit_percent_jewelry','item_profit_amount_jewelry','item_fee_percent_jewelry','item_fee_amount_jewelry','carat','unit_price_rials','total_value_rials','tag_number','assay_office_id','coin_year','coin_seal_type','is_bank_coin','wage_amount','manufactured_fee_percent','manufactured_profit_percent','manufactured_weight_750','manufactured_profit_amount','manufactured_fee_amount','manufactured_total_price','workshop_name','stone_weight_grams','description','created_at','updated_at','profit_percent','fee_percent','profit_amount','fee_amount','general_tax','vat','manufactured_has_attachments','manufactured_attachment_type','manufactured_attachment_weight','item_type_manufactured','item_quantity_manufactured','item_weight_750_manufactured','item_unit_price_manufactured','item_total_price_manufactured','item_profit_amount_manufactured','item_fee_amount_manufactured','item_manufacturing_fee_amount_manufactured','item_manufacturing_fee_rate_manufactured','item_quantity_coin','item_unit_price_coin','item_total_price_coin','item_coin_year_coin','item_type_coin','item_vacuum_name_coin','item_profit_percent_coin','item_profit_amount_coin','item_carat_goldbullion','item_weight_scale_goldbullion','item_weight_750_goldbullion','item_unit_price_goldbullion','item_total_price_goldbullion','item_bullion_number_goldbullion','item_manufacturer_goldbullion','item_profit_percent_goldbullion','item_profit_amount_goldbullion','item_fee_percent_goldbullion','item_fee_amount_goldbullion','item_type_jewelry','item_color_jewelry','item_quality_grade_jewelry','item_quantity_jewelry','item_unit_price_jewelry','item_total_price_jewelry'
        ];
        $dbItem = [];
        foreach ($fields as $field) {
            if (isset($formItem[$field])) {
                $dbItem[$field] = $formItem[$field];
            }
        }
        return $dbItem;
    }
    public static function getAllowedFields(): array
    {
        return [
            'id',
            'transaction_id',
            'product_id',
            'quantity',
            'weight_grams',
            'item_weight_carat_jewelry',
            'item_profit_percent_jewelry',
            'item_profit_amount_jewelry',
            'item_fee_percent_jewelry',
            'item_fee_amount_jewelry',
            'carat',
            'unit_price_rials',
            'total_value_rials',
            'tag_number',
            'assay_office_id',
            'coin_year',
            'coin_seal_type',
            'is_bank_coin',
            'wage_amount',
            'manufactured_fee_percent',
            'manufactured_profit_percent',
            'manufactured_weight_750',
            'manufactured_profit_amount',
            'manufactured_fee_amount',
            'manufactured_total_price',
            'workshop_name',
            'stone_weight_grams',
            'description',
            'created_at',
            'updated_at',
            'profit_percent',
            'fee_percent',
            'profit_amount',
            'fee_amount',
            'general_tax',
            'vat',
            'manufactured_has_attachments',
            'manufactured_attachment_type',
            'manufactured_attachment_weight',
            'item_type_manufactured',
            'item_quantity_manufactured',
            'item_weight_750_manufactured',
            'item_unit_price_manufactured',
            'item_total_price_manufactured',
            'item_profit_amount_manufactured',
            'item_fee_amount_manufactured',
            'item_manufacturing_fee_amount_manufactured',
            'item_manufacturing_fee_rate_manufactured',
            'item_quantity_coin',
            'item_unit_price_coin',
            'item_total_price_coin',
            'item_coin_year_coin',
            'item_type_coin',
            'item_vacuum_name_coin',
            'item_profit_percent_coin',
            'item_profit_amount_coin',
            'item_carat_goldbullion',
            'item_weight_scale_goldbullion',
            'item_weight_750_goldbullion',
            'item_unit_price_goldbullion',
            'item_total_price_goldbullion',
            'item_bullion_number_goldbullion',
            'item_manufacturer_goldbullion',
            'item_profit_percent_goldbullion',
            'item_profit_amount_goldbullion',
            'item_fee_percent_goldbullion',
            'item_fee_amount_goldbullion',
            'item_type_jewelry',
            'item_color_jewelry',
            'item_quality_grade_jewelry',
            'item_quantity_jewelry',
            'item_unit_price_jewelry',
            'item_total_price_jewelry',
        ];
    }

}
