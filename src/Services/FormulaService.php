<?php

namespace App\Services;

use Monolog\Logger;
use Exception;
use Throwable;

/**
 * سرویس محاسبه فرمول‌ها
 */
class FormulaService {
    private Logger $logger;
    private array $formulas;
    private array $fields;

    public function __construct(Logger $logger, array $formulas, array $fields) {
        $this->logger = $logger;
        $this->formulas = $formulas;
        $this->fields = $fields;
        $this->logger->debug("FormulaService initialized.");
    }

    /**
     * محاسبه مقدار یک فرمول بر اساس مقادیر ورودی
     * @param string $formulaName نام فرمول
     * @param array $values مقادیر ورودی (نام فیلد => مقدار)
     * @return float|null نتیجه محاسبه یا null در صورت خطا
     */
    public function calculate(string $formulaName, array $values): ?float {
        try {
            $formula = $this->findFormula($formulaName);
            if (!$formula) {
                $this->logger->error("Formula not found.", ['formula_name' => $formulaName]);
                return null;
            }
            $requiredFields = $formula['fields'] ?? [];
            foreach ($requiredFields as $field) {
                if (!isset($values[$field])) {
                    $this->logger->error("Required field missing for formula calculation.", [
                        'formula_name' => $formulaName,
                        'missing_field' => $field
                    ]);
                    return null;
                }
            }
            $expression = $formula['formula'];
            foreach ($values as $field => $value) {
                if (!is_numeric($value)) {
                    $this->logger->error("Non-numeric value for field in formula.", [
                        'formula_name' => $formulaName,
                        'field' => $field,
                        'value' => $value
                    ]);
                    return null;
                }
                $expression = str_replace($field, (float)$value, $expression);
            }
            $result = $this->evaluateExpression($expression);
            if ($result === null) {
                $this->logger->error("Formula calculation failed.", [
                    'formula_name' => $formulaName,
                    'expression' => $expression
                ]);
                return null;
            }
            return $result;
        } catch (Throwable $e) {
            $this->logger->error("Error calculating formula.", [
                'formula_name' => $formulaName,
                'values' => $values,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * محاسبه امن عبارات ریاضی
     */
    private function evaluateExpression(string $expression): ?float {
        try {
            // حذف تمام کاراکترهای غیرمجاز
            $expression = preg_replace('/[^0-9\+\-\*\/\(\)\.\s]/', '', $expression);
            
            // بررسی عبارت برای اطمینان از امن بودن
            if (!preg_match('/^[\d\s\+\-\*\/\(\)\.]+$/', $expression)) {
                $this->logger->error("Invalid expression detected.", ['expression' => $expression]);
                return null;
            }

            // استفاده از bc math برای محاسبات دقیق
            bcscale(6); // تنظیم دقت اعشار
            
            // تقسیم عبارت به عملوندها و عملگرها
            $tokens = $this->tokenizeExpression($expression);
            return $this->evaluateTokens($tokens);

        } catch (Throwable $e) {
            $this->logger->error("Error evaluating expression.", [
                'expression' => $expression,
                'exception' => $e
            ]);
            return null;
        }
    }

    /**
     * تبدیل عبارت به توکن‌ها
     */
    private function tokenizeExpression(string $expression): array {
        $tokens = [];
        $current = '';
        
        for ($i = 0; $i < strlen($expression); $i++) {
            $char = $expression[$i];
            
            if (ctype_digit($char) || $char === '.') {
                $current .= $char;
            } else if (in_array($char, ['+', '-', '*', '/', '(', ')'])) {
                if ($current !== '') {
                    $tokens[] = (float)$current;
                    $current = '';
                }
                $tokens[] = $char;
            }
        }
        
        if ($current !== '') {
            $tokens[] = (float)$current;
        }
        
        return $tokens;
    }

    /**
     * محاسبه نتیجه بر اساس توکن‌ها
     */
    private function evaluateTokens(array $tokens): float {
        $output = [];
        $operators = [];
        
        $precedence = [
            '+' => 1,
            '-' => 1,
            '*' => 2,
            '/' => 2
        ];
        
        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $output[] = $token;
            } else if ($token === '(') {
                $operators[] = $token;
            } else if ($token === ')') {
                while (!empty($operators) && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }
                array_pop($operators); // حذف '('
            } else {
                while (!empty($operators) && 
                       end($operators) !== '(' && 
                       $precedence[$token] <= $precedence[end($operators)]) {
                    $output[] = array_pop($operators);
                }
                $operators[] = $token;
            }
        }
        
        while (!empty($operators)) {
            $output[] = array_pop($operators);
        }
        
        return $this->evaluateRPN($output);
    }

    /**
     * محاسبه عبارت RPN
     */
    private function evaluateRPN(array $tokens): float {
        $stack = [];
        
        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $stack[] = $token;
            } else {
                $b = array_pop($stack);
                $a = array_pop($stack);
                
                switch ($token) {
                    case '+':
                        $stack[] = bcadd($a, $b);
                        break;
                    case '-':
                        $stack[] = bcsub($a, $b);
                        break;
                    case '*':
                        $stack[] = bcmul($a, $b);
                        break;
                    case '/':
                        if ($b == 0) {
                            throw new Exception("Division by zero");
                        }
                        $stack[] = bcdiv($a, $b);
                        break;
                }
            }
        }
        
        return (float)$stack[0];
    }

    /**
     * محاسبه قیمت واحد بر اساس مظنه
     * @param float $mazanehPrice قیمت مظنه
     * @return float|null قیمت واحد محاسبه شده یا null در صورت خطا
     */
    public function calculateUnitPrice(float $mazanehPrice, string $category = 'MELTED'): ?float {
        $formulaName = match($category) {
            'MELTED' => 'unit_price_melted',
            'MANUFACTURED' => 'unit_price_manufactured',
            'GOLDBULLION' => 'item_unit_price_goldbullion',
            'SILVERBULLION' => 'item_unit_price_silverbullion',
            default => null
        };

        if (!$formulaName) {
            $this->logger->error("No unit price formula defined for category.", ['category' => $category]);
            return null;
        }

        return $this->calculate($formulaName, ['mazaneh_price' => $mazanehPrice]);
    }

    /**
     * محاسبه مقادیر نهایی معامله
     */
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

    /**
     * محاسبه مقادیر یک قلم معامله
     */
    public function calculateItemValue(array $itemData, object $product, ?string $categoryCode): float {
        $values = array_merge($itemData, [
            'product' => (array)$product,
            'category' => $categoryCode
        ]);

        $formulaName = match($categoryCode) {
            'MELTED' => 'item_total_price_melted',
            'MANUFACTURED' => 'item_total_price_manufactured',
            'COIN' => 'item_total_price_coin',
            'GOLDBULLION' => 'item_total_price_goldbullion',
            'SILVERBULLION' => 'item_total_price_silverbullion',
            'JEWELRY' => 'item_total_price_jewelry',
            default => null
        };

        if (!$formulaName) {
            $this->logger->error("No total price formula defined for category.", ['category' => $categoryCode]);
            return 0;
        }

        $result = $this->calculate($formulaName, $values);
        return $result ?? 0;
    }

    /**
     * اعتبارسنجی داده‌های یک قلم معامله
     */
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

    /**
     * یافتن فرمول با نام مشخص
     */
    private function findFormula(string $formulaName): ?array {
        foreach ($this->formulas as $formula) {
            if ($formula['name'] === $formulaName) {
                return $formula;
            }
        }
        return null;
    }

    /**
     * دریافت فیلدهای اجباری بر اساس دسته‌بندی
     */
    private function getRequiredFieldsByCategory(?string $categoryCode): array {
        return match($categoryCode) {
            'MELTED' => [
                'item_weight_scale_melted',
                'item_carat_melted',
                'item_unit_price_melted'
            ],
            'MANUFACTURED' => [
                'item_weight_scale_manufactured',
                'item_carat_manufactured',
                'item_unit_price_manufactured'
            ],
            'COIN' => [
                'item_quantity_coin',
                'item_unit_price_coin'
            ],
            'GOLDBULLION' => [
                'item_weight_scale_goldbullion',
                'item_carat_goldbullion',
                'item_unit_price_goldbullion'
            ],
            'SILVERBULLION' => [
                'item_weight_scale_silverbullion',
                'item_unit_price_silverbullion'
            ],
            'JEWELRY' => [
                'item_weight_carat_jewelry',
                'item_unit_price_jewelry'
            ],
            default => []
        };
    }

    /**
     * دریافت فیلدهای عددی بر اساس دسته‌بندی
     */
    private function getNumericFieldsByCategory(?string $categoryCode): array {
        $baseFields = [
            'item_profit_percent',
            'item_fee_percent',
            'item_tax_percent'
        ];

        return match($categoryCode) {
            'MELTED' => array_merge($baseFields, [
                'item_weight_scale_melted',
                'item_carat_melted',
                'item_unit_price_melted'
            ]),
            'MANUFACTURED' => array_merge($baseFields, [
                'item_weight_scale_manufactured',
                'item_carat_manufactured',
                'item_unit_price_manufactured',
                'item_manufacturing_fee_rate_manufactured'
            ]),
            'COIN' => array_merge($baseFields, [
                'item_quantity_coin',
                'item_unit_price_coin'
            ]),
            default => $baseFields
        };
    }

    /**
     * اعتبارسنجی محدوده مقادیر
     */
    private function validateFieldRanges(array $itemData, ?string $categoryCode, array &$errors, string $itemIndexLabel): void {
        // اعتبارسنجی عیار
        if (in_array($categoryCode, ['MELTED', 'MANUFACTURED', 'GOLDBULLION'])) {
            $caratField = "item_carat_{$categoryCode}";
            if (isset($itemData[$caratField]) && ($itemData[$caratField] <= 0 || $itemData[$caratField] > 1000)) {
                $errors[] = "{$itemIndexLabel}_carat_range_invalid";
            }
        }

        // اعتبارسنجی وزن
        $weightFields = [
            'MELTED' => 'item_weight_scale_melted',
            'MANUFACTURED' => 'item_weight_scale_manufactured',
            'GOLDBULLION' => 'item_weight_scale_goldbullion',
            'SILVERBULLION' => 'item_weight_scale_silverbullion'
        ];

        if (isset($weightFields[$categoryCode])) {
            $weightField = $weightFields[$categoryCode];
            if (isset($itemData[$weightField]) && $itemData[$weightField] <= 0) {
                $errors[] = "{$itemIndexLabel}_weight_invalid";
            }
        }

        // اعتبارسنجی تعداد برای سکه
        if ($categoryCode === 'COIN' && isset($itemData['item_quantity_coin']) && $itemData['item_quantity_coin'] <= 0) {
            $errors[] = "{$itemIndexLabel}_quantity_invalid";
        }

        // اعتبارسنجی درصدها
        $percentageFields = [
            'item_profit_percent',
            'item_fee_percent',
            'item_tax_percent'
        ];

        foreach ($percentageFields as $field) {
            if (isset($itemData[$field]) && ($itemData[$field] < 0 || $itemData[$field] > 100)) {
                $errors[] = "{$itemIndexLabel}_{$field}_range_invalid";
            }
        }
    }
} 