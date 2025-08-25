<?php

namespace App\Services;

use Monolog\Logger;
use Exception;

class MetadataService
{
    private Logger $logger;
    private array $fields;
    private array $formulas;
    private array $fieldsByForm = [];

    public function __construct(Logger $logger, array $fields, array $formulas)
    {
        $this->logger = $logger;
        $this->fields = $fields;
        $this->formulas = $formulas;
        $this->groupFieldsByForm();
        $this->logger->info("MetadataService initialized with " . count($fields) . " fields and " . count($formulas) . " formulas.");
    }

    private function groupFieldsByForm(): void
    {
        foreach ($this->fields as $field) {
            if (isset($field['form'])) {
                $formKey = $this->getFormKey($field['form']);
                if (!isset($this->fieldsByForm[$formKey])) {
                    $this->fieldsByForm[$formKey] = [];
                }
                $this->fieldsByForm[$formKey][$field['name']] = $field;
            }
        }
    }

    private function getFormKey(string $formPath): string
    {
        // Extracts 'products' from 'products/form.php'
        return explode('/', $formPath)[0];
    }

    /**
     * Gets all field definitions for a specific form.
     * @param string $formKey e.g., 'products', 'transactions'
     * @return array An associative array of fields indexed by name.
     */
    public function getFieldsFor(string $formKey): array
    {
        return $this->fieldsByForm[$formKey] ?? [];
    }

    /**
     * Generates a SQL SELECT clause string from field definitions.
     * @param string $formKey The entity key, e.g., 'products'.
     * @param string $alias The table alias, e.g., 'p'.
     * @return string The generated SQL fields string, e.g., "p.id, p.name, p.category_id".
     */
    public function generateSelectFieldsFor(string $formKey, string $alias): string
    {
        $fields = $this->getFieldsFor($formKey);
        if (empty($fields)) {
            return "{$alias}.*"; // Fallback
        }

        $dbFields = [];
        foreach ($fields as $field) {
            // Only include fields that are actual database columns (heuristic: no 'section' or specific types)
            if (isset($field['db_column']) && $field['db_column'] === false) {
                continue; // Skip non-DB fields
            }
            $dbFields[] = "{$alias}.`{$field['name']}`";
        }

        return implode(', ', $dbFields);
    }

    /**
     * Generates validation rules for a specific form.
     * @param string $formKey The entity key, e.g., 'products'.
     * @return array Validation rules array.
     */
    public function getValidationRulesFor(string $formKey): array
    {
        $rules = [];
        $fields = $this->getFieldsFor($formKey);
        foreach ($fields as $field) {
            $fieldRules = [];
            if (isset($field['required']) && $field['required']) {
                $fieldRules[] = 'required';
            }
            if (isset($field['is_numeric']) && $field['is_numeric']) {
                $fieldRules[] = 'numeric';
            }
            if (isset($field['max_length'])) {
                $fieldRules[] = 'max:' . $field['max_length'];
            }
            // Add more rule conversions as needed (e.g., email, date format)
            if (!empty($fieldRules)) {
                $rules[$field['name']] = $fieldRules;
            }
        }
        return $rules;
    }
}