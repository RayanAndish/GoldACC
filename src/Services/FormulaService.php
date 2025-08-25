<?php
// src/Services/FormulaService.php
namespace App\Services;

use Monolog\Logger;
use Exception;
use Throwable;

class FormulaService
{
    private Logger $logger;
    private array $formulas;
    private array $fields; 
    private array $formulasByName = [];
    private array $formulasByGroup = [];

    public function __construct(Logger $logger, array $formulas, array $fields)
    {
        $this->logger = $logger;
        $this->formulas = $formulas;
        $this->fields = $fields;

        foreach ($this->formulas as $formula) {
            if (isset($formula['name'])) {
                $this->formulasByName[$formula['name']] = $formula;
            }
            if (isset($formula['group'])) {
                $groupName = strtolower($formula['group']);
                if (!isset($this->formulasByGroup[$groupName])) {
                    $this->formulasByGroup[$groupName] = [];
                }
                $this->formulasByGroup[$groupName][] = $formula;
            }
        }
    }

    /**
     * Helper to retrieve all loaded formulas (now public for TransactionService).
     * @return array All configured formulas.
     */
    public function getFormulas(): array // Make this method public.
    {
        return $this->formulas;
    }

    public function findFormula(string $formulaName): ?array
    {
        return $this->formulasByName[$formulaName] ?? null;
    }

    public function calculate(string $formulaName, array $values): ?float
    {
        $formula = $this->findFormula($formulaName);
        if (!$formula) {
            $this->logger->warning("Formula not found during single calculation.", ['formula_name' => $formulaName]);
            return null;
        }

        $expression = $this->prepareExpression($formula['formula'], $formula['fields'] ?? [], $values);
        return $this->evaluateExpression($expression, $formulaName);
    }

    public function calculateAllForItem(array $inputValues): array
    {
        $calculatedValues = $inputValues;
        
        $productGroup = $inputValues['product_group'] ?? null;

        if (!$productGroup) {
            $this->logger->warning("Missing product_group for item calculation in FormulaService. No item formulas will be evaluated.", ['input_values' => $inputValues]);
            return $inputValues;
        }

        $itemFormulas = $this->formulasByGroup[$productGroup] ?? [];

        usort($itemFormulas, fn($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));

        foreach ($itemFormulas as $formula) {
            $expression = $this->prepareExpression($formula['formula'], $formula['fields'] ?? [], $calculatedValues); // Pass calculatedValues for chaining
            $result = $this->evaluateExpression($expression, $formula['name']);
            
            if ($result !== null && isset($formula['output_field'])) {
                $calculatedValues[$formula['output_field']] = (float)$result; // Ensure output is float
            } else if (!isset($formula['output_field'])) {
                $this->logger->debug("Formula '{$formula['name']}' has no output_field. Result discarded.");
            } else {
                 $this->logger->warning("Formula '{$formula['name']}' evaluation returned null or non-numeric result.", ['result' => $result]);
            }
        }
        return $calculatedValues;
    }
    
   public function calculateTransactionSummary(array $items): array
    {
        $summaryFormulas = array_filter($this->formulas, fn($f) => ($f['form'] ?? '') === 'transactions/form.php' && !isset($f['group']));
        usort($summaryFormulas, fn($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));

        $calculatedValues = [];
        foreach ($summaryFormulas as $formula) {
            $variables = [];
            foreach ($formula['fields'] as $field) {
                if (isset($calculatedValues[$field])) {
                    $variables[$field] = $calculatedValues[$field];
                } else {
                    // (اصلاح شده) به جای array_column، از یک حلقه برای جمع مقادیر از آبجکت‌ها استفاده می‌کنیم
                    $sum = 0.0;
                    foreach ($items as $item) {
                        // اطمینان حاصل می‌کنیم که $item یک آرایه یا آبجکت است
                        if (is_array($item) && isset($item[$field]) && is_numeric($item[$field])) {
                            $sum += (float)$item[$field];
                        } elseif (is_object($item) && isset($item->{$field}) && is_numeric($item->{$field})) {
                            $sum += (float)$item->{$field};
                        }
                    }
                    $variables[$field] = $sum;
                }
            }
            $expression = $this->prepareExpression($formula['formula'], $formula['fields'], $variables);
            $calculatedValues[$formula['name']] = $this->evaluateExpression($expression, $formula['name']);
        }

        $summaryMap = [
            'sum_base_items' => 'total_items_value_rials',
            'sum_profit_wage_fee' => 'total_profit_wage_commission_rials',
            'sum_general_tax' => 'total_general_tax_rials',
            'sum_before_vat' => 'total_before_vat_rials',
            'sum_vat' => 'total_vat_rials',
            'final_payable' => 'final_payable_amount_rials',
        ];

        $finalSummary = [];
        foreach ($summaryMap as $formulaKey => $dbKey) {
            $finalSummary[$dbKey] = (float)($calculatedValues[$formulaKey] ?? 0.0);
        }
        return $finalSummary;
    }


    private function prepareExpression(string $expression, array $requiredFields, array $values): string
    {
        foreach ($requiredFields as $field) {
            $value = $values[$field] ?? 0.0;
            $numericValue = is_numeric($value) ? (float)$value : 0.0;
            $expression = preg_replace('/\b' . preg_quote($field, '/') . '\b/', '(' . $numericValue . ')', $expression);
        }
        return $expression;
    }

    private function evaluateExpression(string $expression, string $context = 'N/A'): ?float
    {
        try {
            if (preg_match('/[^0-9\.\s\+\-\*\/\(\)\|&\?:<>=!]/', $expression)) { 
                $this->logger->error("Potentially unsafe characters detected in expression (eval context: '{$context}').", [
                    'expression' => $expression,
                ]);
                return 0.0;
            }

            $result = @eval("return {$expression};");
            
            if (!is_numeric($result)) {
                $this->logger->warning("Formula evaluation returned non-numeric result (eval context: '{$context}').", [
                    'expression' => $expression,
                    'result' => $result
                ]);
                return 0.0;
            }

            return (float)$result;

        } catch (Throwable $e) {
            $this->logger->error("Error evaluating formula expression (eval context: '{$context}').", ['expression' => $expression, 'exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return 0.0;
        }
    }
}