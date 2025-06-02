<?php

namespace App\Models;

/**
 * Class TransactionItem
 * Represents a record from the 'transaction_items' table.
 */
class TransactionItem {
    // فیلدهای اصلی جدول transaction_items
    public ?int $id = null;
    public ?int $transaction_id = null;
    public ?int $product_id = null;
    public ?int $quantity = null;
    public ?float $weight_grams = null;
    public ?float $item_weight_carat_jewelry = null;
    public ?float $item_profit_percent_jewelry = null;
    public ?float $item_profit_amount_jewelry = null;
    public ?float $item_fee_percent_jewelry = null;
    public ?float $item_fee_amount_jewelry = null;
    public ?int $carat = null;
    public ?float $unit_price_rials = null;
    public ?float $total_value_rials = null;
    public ?string $tag_number = null;
    public ?int $assay_office_id = null;
    public ?int $coin_year = null;
    public ?string $coin_seal_type = null;
    public ?bool $is_bank_coin = null;
    public ?float $wage_amount = null;
    public ?float $manufactured_fee_percent = null;
    public ?float $manufactured_profit_percent = null;
    public ?float $manufactured_weight_750 = null;
    public ?float $manufactured_profit_amount = null;
    public ?float $manufactured_fee_amount = null;
    public ?float $manufactured_total_price = null;
    public ?string $workshop_name = null;
    public ?float $stone_weight_grams = null;
    public ?string $description = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?float $profit_percent = null;
    public ?float $fee_percent = null;
    public ?float $profit_amount = null;
    public ?float $fee_amount = null;
    public ?float $general_tax = null;
    public ?float $vat = null;
    public ?bool $manufactured_has_attachments = null;
    public ?string $manufactured_attachment_type = null;
    public ?float $manufactured_attachment_weight = null;
    public ?string $item_type_manufactured = null;
    public ?int $item_quantity_manufactured = null;
    public ?float $item_weight_750_manufactured = null;
    public ?float $item_unit_price_manufactured = null;
    public ?float $item_total_price_manufactured = null;
    public ?float $item_profit_amount_manufactured = null;
    public ?float $item_fee_amount_manufactured = null;
    public ?float $item_manufacturing_fee_amount_manufactured = null;
    public ?float $item_manufacturing_fee_rate_manufactured = null;
    public ?int $item_quantity_coin = null;
    public ?float $item_unit_price_coin = null;
    public ?float $item_total_price_coin = null;
    public ?int $item_coin_year_coin = null;
    public ?string $item_type_coin = null;
    public ?string $item_vacuum_name_coin = null;
    public ?float $item_profit_percent_coin = null;
    public ?float $item_profit_amount_coin = null;
    public ?float $item_carat_goldbullion = null;
    public ?float $item_weight_scale_goldbullion = null;
    public ?float $item_weight_750_goldbullion = null;
    public ?float $item_unit_price_goldbullion = null;
    public ?float $item_total_price_goldbullion = null;
    public ?string $item_bullion_number_goldbullion = null;
    public ?string $item_manufacturer_goldbullion = null;
    public ?float $item_profit_percent_goldbullion = null;
    public ?float $item_profit_amount_goldbullion = null;
    public ?float $item_fee_percent_goldbullion = null;
    public ?float $item_fee_amount_goldbullion = null;
    public ?string $item_type_jewelry = null;
    public ?string $item_color_jewelry = null;
    public ?string $item_quality_grade_jewelry = null;
    public ?int $item_quantity_jewelry = null;
    public ?float $item_unit_price_jewelry = null;
    public ?float $item_total_price_jewelry = null;

    // propertyهای کمکی فقط private باشند
    private ?Product $product = null;
    private ?AssayOffice $assayOffice = null;

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
            $this->coin_seal_type = $data['coin_seal_type'] ?? null;
            $this->is_bank_coin = isset($data['is_bank_coin']) ? filter_var($data['is_bank_coin'], FILTER_VALIDATE_BOOLEAN) : null;
            $this->wage_amount = isset($data['wage_amount']) ? (float)$data['wage_amount'] : null;
            $this->workshop_name = $data['workshop_name'] ?? null;
            $this->stone_weight_grams = isset($data['stone_weight_grams']) ? (float)$data['stone_weight_grams'] : null;
            $this->description = $data['description'] ?? null;
            $this->created_at = $data['created_at'] ?? null;
            $this->updated_at = $data['updated_at'] ?? null;
            $this->item_type_manufactured = $data['item_type_manufactured'] ?? null;
            $this->manufactured_has_attachments = $data['manufactured_has_attachments'] ?? null;
            $this->manufactured_attachment_type = $data['manufactured_attachment_type'] ?? null;
            $this->manufactured_attachment_weight = $data['manufactured_attachment_weight'] ?? null;
            $this->manufactured_fee_percent = $data['manufactured_fee_percent'] ?? null;
            $this->manufactured_profit_percent = $data['manufactured_profit_percent'] ?? null;
            $this->manufactured_weight_750 = $data['manufactured_weight_750'] ?? null;
            $this->manufactured_profit_amount = $data['manufactured_profit_amount'] ?? null;
            $this->manufactured_fee_amount = $data['manufactured_fee_amount'] ?? null;
            $this->manufactured_total_price = $data['manufactured_total_price'] ?? null;
            $this->item_quantity_manufactured = $data['item_quantity_manufactured'] ?? null;
            $this->item_weight_750_manufactured = $data['item_weight_750_manufactured'] ?? null;
            $this->item_unit_price_manufactured = $data['item_unit_price_manufactured'] ?? null;
            $this->item_total_price_manufactured = $data['item_total_price_manufactured'] ?? null;
            $this->item_profit_amount_manufactured = $data['item_profit_amount_manufactured'] ?? null;
            $this->item_fee_amount_manufactured = $data['item_fee_amount_manufactured'] ?? null;
            $this->item_manufacturing_fee_amount_manufactured = $data['item_manufacturing_fee_amount_manufactured'] ?? null;
            $this->item_manufacturing_fee_rate_manufactured = $data['item_manufacturing_fee_rate_manufactured'] ?? null;
            $this->item_type_coin = $data['item_type_coin'] ?? null;
            $this->item_vacuum_name_coin = $data['item_vacuum_name_coin'] ?? null;
            $this->item_profit_percent_coin = $data['item_profit_percent_coin'] ?? null;
            $this->item_profit_amount_coin = $data['item_profit_amount_coin'] ?? null;
            $this->item_quantity_coin = $data['item_quantity_coin'] ?? null;
            $this->item_unit_price_coin = $data['item_unit_price_coin'] ?? null;
            $this->item_total_price_coin = $data['item_total_price_coin'] ?? null;
            $this->item_coin_year_coin = $data['item_coin_year_coin'] ?? null;
            $this->item_type_jewelry = $data['item_type_jewelry'] ?? null;
            $this->item_color_jewelry = $data['item_color_jewelry'] ?? null;
            $this->item_quality_grade_jewelry = $data['item_quality_grade_jewelry'] ?? null;
            $this->item_profit_percent_jewelry = $data['item_profit_percent_jewelry'] ?? null;
            $this->item_profit_amount_jewelry = $data['item_profit_amount_jewelry'] ?? null;
            $this->item_quantity_jewelry = $data['item_quantity_jewelry'] ?? null;
            $this->item_unit_price_jewelry = $data['item_unit_price_jewelry'] ?? null;
            $this->item_total_price_jewelry = $data['item_total_price_jewelry'] ?? null;
            $this->item_weight_carat_jewelry = $data['item_weight_carat_jewelry'] ?? null;
            $this->item_bullion_number_goldbullion = $data['item_bullion_number_goldbullion'] ?? null;
            $this->item_manufacturer_goldbullion = $data['item_manufacturer_goldbullion'] ?? null;
            $this->item_profit_percent_goldbullion = $data['item_profit_percent_goldbullion'] ?? null;
            $this->item_profit_amount_goldbullion = $data['item_profit_amount_goldbullion'] ?? null;
            $this->item_fee_percent_goldbullion = $data['item_fee_percent_goldbullion'] ?? null;
            $this->item_fee_amount_goldbullion = $data['item_fee_amount_goldbullion'] ?? null;
            $this->item_weight_scale_goldbullion = $data['item_weight_scale_goldbullion'] ?? null;
            $this->item_weight_750_goldbullion = $data['item_weight_750_goldbullion'] ?? null;
            $this->item_unit_price_goldbullion = $data['item_unit_price_goldbullion'] ?? null;
            $this->item_total_price_goldbullion = $data['item_total_price_goldbullion'] ?? null;

             // If product data is passed directly
            if ($this->product_id !== null && isset($data['product_name'])) { // Example check
                $this->product = new Product($data); // Pass all data assuming prefix isn't used
            }
             // If assay office data is passed directly
             if ($this->assay_office_id !== null && isset($data['assay_office_name'])) { // Example check
                // Assuming AssayOffice model exists and has a constructor
                $this->assayOffice = new AssayOffice([
                     'id' => $this->assay_office_id,
                     'name' => $data['assay_office_name']
                     // Add other relevant fields if joined
                ]);
            }
        }
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'weight_grams' => $this->weight_grams,
            'carat' => $this->carat,
            'unit_price_rials' => $this->unit_price_rials,
            'total_value_rials' => $this->total_value_rials,
            'tag_number' => $this->tag_number,
            'assay_office_id' => $this->assay_office_id,
            'coin_year' => $this->coin_year,
            'coin_seal_type' => $this->coin_seal_type,
            'is_bank_coin' => $this->is_bank_coin,
            'wage_amount' => $this->wage_amount,
            'workshop_name' => $this->workshop_name,
            'stone_weight_grams' => $this->stone_weight_grams,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'item_type_manufactured' => $this->item_type_manufactured,
            'manufactured_has_attachments' => $this->manufactured_has_attachments,
            'manufactured_attachment_type' => $this->manufactured_attachment_type,
            'manufactured_attachment_weight' => $this->manufactured_attachment_weight,
            'manufactured_fee_percent' => $this->manufactured_fee_percent,
            'manufactured_profit_percent' => $this->manufactured_profit_percent,
            'manufactured_weight_750' => $this->manufactured_weight_750,
            'manufactured_profit_amount' => $this->manufactured_profit_amount,
            'manufactured_fee_amount' => $this->manufactured_fee_amount,
            'manufactured_total_price' => $this->manufactured_total_price,
            'item_quantity_manufactured' => $this->item_quantity_manufactured,
            'item_weight_750_manufactured' => $this->item_weight_750_manufactured,
            'item_unit_price_manufactured' => $this->item_unit_price_manufactured,
            'item_total_price_manufactured' => $this->item_total_price_manufactured,
            'item_profit_amount_manufactured' => $this->item_profit_amount_manufactured,
            'item_fee_amount_manufactured' => $this->item_fee_amount_manufactured,
            'item_manufacturing_fee_amount_manufactured' => $this->item_manufacturing_fee_amount_manufactured,
            'item_manufacturing_fee_rate_manufactured' => $this->item_manufacturing_fee_rate_manufactured,
            'item_type_coin' => $this->item_type_coin,
            'item_vacuum_name_coin' => $this->item_vacuum_name_coin,
            'item_profit_percent_coin' => $this->item_profit_percent_coin,
            'item_profit_amount_coin' => $this->item_profit_amount_coin,
            'item_quantity_coin' => $this->item_quantity_coin,
            'item_unit_price_coin' => $this->item_unit_price_coin,
            'item_total_price_coin' => $this->item_total_price_coin,
            'item_coin_year_coin' => $this->item_coin_year_coin,
            'item_type_jewelry' => $this->item_type_jewelry,
            'item_color_jewelry' => $this->item_color_jewelry,
            'item_quality_grade_jewelry' => $this->item_quality_grade_jewelry,
            'item_profit_percent_jewelry' => $this->item_profit_percent_jewelry,
            'item_profit_amount_jewelry' => $this->item_profit_amount_jewelry,
            'item_quantity_jewelry' => $this->item_quantity_jewelry,
            'item_unit_price_jewelry' => $this->item_unit_price_jewelry,
            'item_total_price_jewelry' => $this->item_total_price_jewelry,
            'product' => $this->product ? $this->product->toArray() : null, // Include product details if available
            'assayOffice' => $this->assayOffice ? $this->assayOffice->toArray() : null, // Include assay office details if available
        ];
    }

    /**
     * لیست دقیق و داینامیک فیلدهای مجاز برای ذخیره‌سازی (مطابق جدول transaction_items)
     */
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

    /**
     * متد استاتیک برای mapping فیلدهای فرم به فیلدهای جدول transaction_items بر اساس گروه کالا
     * @param string $group گروه کالا (مثلاً MELTED, MANUFACTURED, COIN, GOLDBULLION, JEWELRY)
     * @return array mapping [form_field => db_field]
     */
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

    /**
     * مپینگ مرکزی و دقیق داده‌های فرم آیتم به فیلدهای جدول transaction_items
     * @param array $formItem داده خام آیتم از فرم
     * @param string $group گروه کالا (MELTED, MANUFACTURED, ...)
     * @return array داده آماده برای ذخیره در جدول
     */
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
} 