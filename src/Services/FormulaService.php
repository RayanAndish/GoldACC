<?php

namespace App\Services;

use Monolog\Logger;
use Exception;
use Throwable;
use App\Models\TransactionItem;

class FormulaService {
    private Logger $logger;
    private array $formulas;
    private array $fields;

    public function __construct(Logger $logger, array $formulas, array $fields) {
        $this->logger = $logger;
        $this->formulas = $formulas;
        $this->fields = $fields;
    }

    public function findFormula(string $formulaName): ?array {
        foreach ($this->formulas as $formula) {
            if (isset($formula['name']) && $formula['name'] === $formulaName) {
                return $formula;
            }
        }
        return null;
    }

    public function getFormulasByGroup(string $group): array {
        if (empty($group)) {
            return [];
        }
        $groupLower = strtolower($group);
        return array_filter($this->formulas, function($formula) use ($groupLower) {
            return isset($formula['group']) && strtolower($formula['group']) === $groupLower;
        });
    }

    public function calculate(string $formulaName, array $values): ?float {
        try {
            $formula = $this->findFormula($formulaName);
            if (!$formula) {
                $this->logger->warning("Formula not found during calculation.", ['formula_name' => $formulaName]);
                return null;
            }
            $expression = $formula['formula'];
            $requiredFields = $formula['fields'] ?? [];
            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $values)) {
                    $values[$field] = 0;
                }
            }
            
            foreach ($values as $key => $value) {
                $numericValue = is_numeric($value) ? $value : 0;
                $expression = str_replace($key, (string)$numericValue, $expression);
            }
            
            if (preg_match('/[^-+*\/()\d.\s]/', $expression)) {
                 $this->logger->error("Potentially unsafe characters detected in expression before eval.", ['expression' => $expression]);
                 return null;
            }

            $result = eval("return {$expression};");
            return is_numeric($result) ? (float)$result : null;

        } catch (Throwable $e) {
            $this->logger->error("Error calculating formula '{$formulaName}'.", ['expression' => $expression ?? 'N/A', 'exception' => $e->getMessage()]);
            return null;
        }
    }

    public function calculateTransactionSummary(array $items, array $transactionData, array $productsById, array $defaults, array $taxSettings = []): array {
        $summary = [
            'total_items_value_rials' => 0,
            'total_profit_wage_commission_rials' => 0,
            'total_general_tax_rials' => 0,
            'total_before_vat_rials' => 0,
            'total_vat_rials' => 0,
            'final_payable_amount_rials' => 0
        ];

        try {
            // ۱. جمع پایه اقلام (بدون مالیات)
            $sum_base_items = 0;
            $baseFields = [
                'item_total_price_melted',
                'item_total_price_manufactured',
                'item_total_price_goldbullion',
                'item_total_price_silverbullion',
                'item_total_price_coin',
                'item_total_price_jewelry'
            ];
            foreach ($baseFields as $field) {
                $sum_base_items += array_sum(array_map(fn($item) => is_numeric($item[$field] ?? null) ? (float)$item[$field] : 0, $items));
            }
            $summary['total_items_value_rials'] = $sum_base_items;

            // ۲. جمع سود/اجرت/کارمزد
            $sum_profit_wage_fee = 0;
            $profitFields = [
                'item_profit_amount_melted','item_fee_amount_melted',
                'item_profit_amount_manufactured','item_fee_amount_manufactured','item_manufacturing_fee_amount_manufactured',
                'item_profit_amount_goldbullion','item_fee_amount_goldbullion',
                'item_profit_amount_silverbullion','item_fee_amount_silverbullion',
                'item_profit_amount_coin','item_profit_amount_jewelry','item_fee_amount_jewelry'
            ];
            foreach ($profitFields as $field) {
                $sum_profit_wage_fee += array_sum(array_map(fn($item) => is_numeric($item[$field] ?? null) ? (float)$item[$field] : 0, $items));
            }
            $summary['total_profit_wage_commission_rials'] = $sum_profit_wage_fee;

            // ۳. نرخ‌های مالیات
            $taxRate = isset($taxSettings['tax_rate']) ? (float)$taxSettings['tax_rate'] : 9;
            $vatRate = isset($taxSettings['vat_rate']) ? (float)$taxSettings['vat_rate'] : 7;

            // ۴. جمع مالیات عمومی (جمع داینامیک فیلدهای مالیات هر ردیف)
            $taxFields = [
                'item_general_tax_melted',
                'item_general_tax_manufactured',
                'item_general_tax_goldbullion',
                'item_general_tax_silverbullion',
                'item_general_tax_coin',
                'item_general_tax_jewelry'
            ];
            $sum_general_tax = 0;
            foreach ($taxFields as $field) {
                $sum_general_tax += array_sum(array_map(fn($item) => is_numeric($item[$field] ?? null) ? (float)$item[$field] : 0, $items));
            }
            $summary['total_general_tax_rials'] = $sum_general_tax;

            // ۵. جمع قبل از ارزش افزوده
            $sum_before_vat = $sum_base_items + $sum_profit_wage_fee + $sum_general_tax;
            $summary['total_before_vat_rials'] = $sum_before_vat;

            // ۶. مالیات بر ارزش افزوده (روی کل فاکتور پس از سایر محاسبات)
            $sum_vat = $sum_before_vat * $vatRate / 100;
            $summary['total_vat_rials'] = $sum_vat;

            // ۷. مبلغ نهایی قابل پرداخت
            $final_payable = $sum_before_vat + $sum_vat;
            $summary['final_payable_amount_rials'] = $final_payable;

            return $summary;
        } catch (Throwable $e) {
            $this->logger->error("Error calculating transaction summary.", [
                'exception' => $e,
                'items_count' => count($items)
            ]);
            throw $e;
        }
    }

    public function validateItemByCategory(array &$itemData, array $inputData, ?string $categoryCode, array &$errors, string $itemIndexLabel): void {
        // بررسی فیلدهای اجباری بر اساس دسته‌بندی
        $requiredFields = $this->getRequiredFieldsByCategory($categoryCode);
        foreach ($requiredFields as $field) {
            if (!isset($itemData[$field]) || $itemData[$field] === '' || $itemData[$field] === null) {
                $errors[] = "{$itemIndexLabel}_{$field}_required";
            }
        }

        // اعتبارسنجی مقادیر عددی
        $numericFields = $this->getNumericFieldsByCategory($categoryCode);
        foreach ($numericFields as $field) {
            if (isset($itemData[$field]) && !is_numeric($itemData[$field])) {
                $errors[] = "{$itemIndexLabel}_{$field}_invalid";
            }
        }

        // اعتبارسنجی محدوده مقادیر
        $this->validateFieldRanges($itemData, $categoryCode, $errors, $itemIndexLabel);
    }
}
