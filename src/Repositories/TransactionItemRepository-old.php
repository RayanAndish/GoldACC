<?php

namespace App\Repositories;

use App\Models\TransactionItem;
use PDO;

class TransactionItemRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // متدهای مربوط به TransactionItem در اینجا اضافه خواهند شد
    // مانند: save, findById, findByTransactionId, getItemIdsByTransactionId, deleteByIds, etc.

    /**
     * ذخیره یا به‌روزرسانی یک قلم معامله
     * @param TransactionItem $item
     * @return int|false ID قلم ذخیره شده یا false در صورت خطا
     */
    public function save(TransactionItem $item): int|false
    {
        $allowedFields = \App\Models\TransactionItem::getAllowedFields();
        $dataToSave = array_intersect_key($item->toArray(), array_flip($allowedFields));
        $isUpdate = ($item->id > 0);

        if ($isUpdate) {
            // ساخت داینامیک کوئری UPDATE
            $fields = array_keys($dataToSave);
            $fields = array_filter($fields, fn($f) => $f !== 'id');
            $setClause = implode(", ", array_map(fn($f) => "$f = :$f", $fields));
            $this->logger->debug("TRANSACTION ITEM DATA FOR DB", ['item_data' => $dataToSave]);
            $sql = "UPDATE transaction_items SET $setClause, general_tax = :general_tax, vat = :vat WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            foreach ($fields as $f) {
                $stmt->bindValue(":$f", $dataToSave[$f]);
            }
            $stmt->bindValue(':general_tax', $item->general_tax);
            $stmt->bindValue(':vat', $item->vat);
            $stmt->bindValue(':id', $item->id, \PDO::PARAM_INT);
        } else {
            // ساخت داینامیک کوئری INSERT
            $fields = array_keys($dataToSave);
            $placeholders = array_map(fn($f) => ":$f", $fields);
            $sql = "INSERT INTO transaction_items (" . implode(", ", $fields) . ", general_tax, vat) VALUES (" . implode(", ", $placeholders) . ", :general_tax, :vat)";
            $stmt = $this->db->prepare($sql);
            foreach ($fields as $f) {
                $stmt->bindValue(":$f", $dataToSave[$f]);
            }
            $stmt->bindValue(':general_tax', $item->general_tax);
            $stmt->bindValue(':vat', $item->vat);
        }

        if ($stmt->execute()) {
            return $isUpdate ? $item->id : (int)$this->db->lastInsertId();
        }
        return false;
    }

     /**
     * دریافت آرایه‌ای از ID های اقلام مربوط به یک معامله خاص
     * @param int $transactionId
     * @return array<int>
     */
    public function getItemIdsByTransactionId(int $transactionId): array
    {
        $stmt = $this->db->prepare("SELECT id FROM transaction_items WHERE transaction_id = :transaction_id");
        $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
        $stmt->execute();
        // Fetch all IDs as a flat array of integers
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    /**
     * حذف اقلام با ID های مشخص شده
     * @param array<int> $ids
     * @return bool True on success, false on failure
     */
    public function deleteByIds(array $ids): bool
    {
        if (empty($ids)) {
            return true; // Nothing to delete
        }
        // Ensure all IDs are integers
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM transaction_items WHERE id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($ids);
    }

    /**
     * دریافت تمام اقلام مربوط به یک معامله خاص
     * @param int $transactionId
     * @return array<array<string, mixed>> آرایه‌ای از آرایه‌های حاوی داده‌های هر قلم
     */
    public function findByTransactionId(int $transactionId): array
    {
        $sql = "SELECT ti.*,
                       p.name AS product_name,
                       p.unit_of_measure AS product_unit_of_measure,
                       pc.code AS product_category_code,
                       pc.name AS product_category_name,
                       pc.base_category AS product_category_base
                FROM transaction_items ti
                JOIN products p ON ti.product_id = p.id
                JOIN product_categories pc ON p.category_id = pc.id
                WHERE ti.transaction_id = :transaction_id
                ORDER BY ti.id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':transaction_id', $transactionId, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // پس از دریافت آیتم‌ها، فیلدهای تخصصی را برای هر نوع محصول اضافه می‌کنیم
        foreach ($items as &$item) {
            $productCategory = strtolower($item['product_category_base'] ?? '');
            
            // تنظیم مقادیر عمومی برای همه نوع محصولات
            $item['profit_percent'] = $item['profit_percent'] ?? 0;
            $item['profit_amount'] = $item['profit_amount'] ?? 0;
            $item['fee_percent'] = $item['fee_percent'] ?? 0;
            $item['fee_amount'] = $item['fee_amount'] ?? 0;
            $item['general_tax'] = $item['general_tax'] ?? 0;
            $item['vat'] = $item['vat'] ?? 0;
            
            // فیلدهای پایه را به عنوان فیلدهای تخصصی برای هر نوع محصول کپی می‌کنیم
            if ($productCategory === 'melted') {
                // فیلدهای مخصوص آبشده
                $item['item_carat_melted'] = $item['carat'] ?? '';
                $item['item_weight_scale_melted'] = $item['weight_grams'] ?? 0;
                $item['item_weight_pure_melted'] = $item['weight_pure'] ?? 0;
                $item['item_assay_office_melted'] = $item['assay_office_id'] ?? '';
                $item['item_unit_price_melted'] = $item['unit_price_rials'] ?? 0;
                $item['item_total_price_melted'] = $item['total_value_rials'] ?? 0;
                $item['item_tag_number_melted'] = $item['tag_number'] ?? '';
                $item['item_tag_type_melted'] = $item['tag_type'] ?? '';
                $item['item_profit_percent_melted'] = $item['profit_percent'] ?? 0;
                $item['item_profit_amount_melted'] = $item['profit_amount'] ?? 0;
                $item['item_fee_percent_melted'] = $item['fee_percent'] ?? 0;
                $item['item_fee_amount_melted'] = $item['fee_amount'] ?? 0;
                $item['item_general_tax_melted'] = $item['general_tax'] ?? 0;
                $item['item_vat_melted'] = $item['vat'] ?? 0;
            }
            else if ($productCategory === 'manufactured') {
                // فیلدهای مخصوص مصنوعات
                $item['item_carat_manufactured'] = $item['carat'] ?? '';
                $item['item_weight_scale_manufactured'] = $item['weight_grams'] ?? 0;
                $item['item_weight_pure_manufactured'] = $item['weight_pure'] ?? 0;
                $item['item_quantity_manufactured'] = $item['quantity'] ?? 1;
                $item['item_quality_type_manufactured'] = $item['quality_type'] ?? '';
                $item['item_attachments_type_manufactured'] = $item['attachments_type'] ?? '';
                $item['item_attachments_weight_manufactured'] = $item['attachments_weight'] ?? 0;
                $item['item_ajrat_percent_manufactured'] = $item['ajrat_percent'] ?? 0;
                $item['item_ajrat_rials_manufactured'] = $item['ajrat_rials'] ?? 0;
                $item['item_workshop_name_manufactured'] = $item['workshop_name'] ?? '';
                $item['item_unit_price_manufactured'] = $item['unit_price_rials'] ?? 0;
                $item['item_total_price_manufactured'] = $item['total_value_rials'] ?? 0;
                $item['item_profit_percent_manufactured'] = $item['profit_percent'] ?? 0;
                $item['item_profit_amount_manufactured'] = $item['profit_amount'] ?? 0;
                $item['item_fee_percent_manufactured'] = $item['fee_percent'] ?? 0;
                $item['item_fee_amount_manufactured'] = $item['fee_amount'] ?? 0;
                $item['item_general_tax_manufactured'] = $item['general_tax'] ?? 0;
                $item['item_vat_manufactured'] = $item['vat'] ?? 0;
            }
            else if ($productCategory === 'coin') {
                // فیلدهای مخصوص سکه
                $item['item_quantity_coin'] = $item['quantity'] ?? 1;
                $item['item_year_coin'] = $item['coin_year'] ?? '';
                $item['item_unit_price_coin'] = $item['unit_price_rials'] ?? 0;
                $item['item_total_price_coin'] = $item['total_value_rials'] ?? 0;
                $item['item_seal_name_coin'] = $item['seal_name'] ?? '';
                $item['item_is_bank_coin'] = $item['is_bank_coin'] ?? 0;
                $item['item_profit_percent_coin'] = $item['profit_percent'] ?? 0;
                $item['item_profit_amount_coin'] = $item['profit_amount'] ?? 0;
                $item['item_fee_percent_coin'] = $item['fee_percent'] ?? 0;
                $item['item_fee_amount_coin'] = $item['fee_amount'] ?? 0;
                $item['item_general_tax_coin'] = $item['general_tax'] ?? 0;
                $item['item_vat_coin'] = $item['vat'] ?? 0;
            }
        }
        
        return $items;
    }

    /**
     * دریافت تمام اقلام معاملاتی در وضعیت معلق همراه با جزئیات محصول
     * @param string $deliveryStatus وضعیت تحویل (pending_receipt یا pending_delivery)
     * @return array<array<string, mixed>> آرایه‌ای از آرایه‌های حاوی داده‌های هر قلم همراه با اطلاعات محصول
     */
    public function findPendingItemsWithProductDetails(string $deliveryStatus): array
    {
        // استفاده از نوع معامله متناسب با وضعیت تحویل
        $transactionType = ($deliveryStatus === 'pending_receipt') ? 'buy' : 'sell';
        
        $sql = "SELECT 
                    ti.id,
                    ti.transaction_id,
                    ti.product_id,
                    ti.quantity,
                    ti.weight_grams,
                    ti.carat,
                    ti.coin_year,
                    t.transaction_date,
                    t.transaction_type,
                    t.delivery_status,
                    t.counterparty_contact_id,
                    t.id as transaction_id,
                    p.name as product_name,
                    p.unit_of_measure as product_unit_of_measure,
                    pc.code as product_category_code,
                    pc.name as product_category_name,
                    COALESCE(c.name, 'نامشخص') as counterparty_name
                FROM 
                    transaction_items ti
                    JOIN transactions t ON ti.transaction_id = t.id
                    JOIN products p ON ti.product_id = p.id
                    JOIN product_categories pc ON p.category_id = pc.id
                    LEFT JOIN contacts c ON t.counterparty_contact_id = c.id
                WHERE 
                    t.delivery_status = :delivery_status
                    AND t.transaction_type = :transaction_type
                ORDER BY 
                    t.transaction_date DESC, ti.id ASC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':delivery_status', $deliveryStatus, PDO::PARAM_STR);
        $stmt->bindValue(':transaction_type', $transactionType, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Add other necessary methods like findById if needed

} 