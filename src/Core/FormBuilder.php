<?php

namespace App\Core;

use App\Utils\Helper;

class FormBuilder
{
    /**
     * Renders a complete HTML form field (label, input, errors) based on its definition.
     * @param array $fieldDefinition The field definition array from fields.json.
     * @param mixed $currentValue The current value for the field.
     * @param string|null $error The error message for this field.
     * @return string The generated HTML.
     */
    public static function renderField(array $fieldDefinition, $currentValue = null, ?string $error = null): string
    {
        $type = $fieldDefinition['type'] ?? 'text';
        $name = $fieldDefinition['name'] ?? '';
        $id = 'form_' . $name;
        $label = $fieldDefinition['label'] ?? '';
        $required = $fieldDefinition['required'] ?? false;
        $class = 'form-control ' . ($fieldDefinition['class'] ?? '');
        if ($error) {
            $class .= ' is-invalid';
        }
        
        $html = '<div class="mb-3">';
        
        // --- LABEL ---
        if ($type !== 'checkbox') {
            $html .= sprintf(
                '<label for="%s" class="form-label">%s%s</label>',
                $id,
                Helper::escapeHtml($label),
                $required ? ' <span class="text-danger">*</span>' : ''
            );
        }

        // --- INPUT ELEMENT ---
        switch ($type) {
            case 'select':
                $html .= self::renderSelect($fieldDefinition, $currentValue, $id, $name, $class);
                break;
            case 'textarea':
                $html .= sprintf(
                    '<textarea id="%s" name="%s" class="%s" rows="3">%s</textarea>',
                    $id, $name, trim($class), Helper::escapeHtml($currentValue ?? '')
                );
                break;
            case 'checkbox':
                 $html .= self::renderCheckbox($fieldDefinition, $currentValue, $id, $name, $label);
                 break;
            default: // text, number, email, etc.
                $html .= sprintf(
                    '<input type="%s" id="%s" name="%s" class="%s" value="%s">',
                    $type, $id, $name, trim($class), Helper::escapeHtml($currentValue ?? '')
                );
                break;
        }

        // --- ERROR MESSAGE ---
        if ($error) {
            $html .= sprintf('<div class="invalid-feedback">%s</div>', Helper::escapeHtml($error));
        }
        
        // --- HELP TEXT ---
        if (isset($fieldDefinition['help_text'])) {
             $html .= sprintf('<small class="form-text text-muted">%s</small>', Helper::escapeHtml($fieldDefinition['help_text']));
        }

        $html .= '</div>';
        return $html;
    }

    private static function renderSelect(array $def, $currentValue, string $id, string $name, string $class): string
    {
        $html = sprintf('<select id="%s" name="%s" class="%s">', $id, $name, trim('form-select ' . $class));
        $html .= '<option value="">انتخاب کنید...</option>';
        if (!empty($def['options'])) {
            foreach ($def['options'] as $option) {
                $selected = ($currentValue == $option['value']) ? 'selected' : '';
                $html .= sprintf('<option value="%s" %s>%s</option>', 
                    Helper::escapeHtml($option['value']), 
                    $selected, 
                    Helper::escapeHtml($option['label'])
                );
            }
        }
        // Note: Dynamic options from a source like DB require passing the options array to this builder.
        // This is handled in the controller.
        else if (!empty($def['options_data'])) {
             foreach ($def['options_data'] as $option) {
                $value = $option[$def['options_value_key'] ?? 'id'];
                $label = $option[$def['options_label_key'] ?? 'name'];
                $selected = ($currentValue == $value) ? 'selected' : '';
                $html .= sprintf('<option value="%s" %s>%s</option>', 
                    Helper::escapeHtml($value), 
                    $selected, 
                    Helper::escapeHtml($label)
                );
            }
        }
        $html .= '</select>';
        return $html;
    }

    private static function renderCheckbox(array $def, $currentValue, string $id, string $name, string $label): string
    {
        $checked = ($currentValue) ? 'checked' : '';
        $html = '<div class="form-check form-switch">';
        $html .= sprintf(
            '<input class="form-check-input" type="checkbox" id="%s" name="%s" value="1" %s>',
            $id, $name, $checked
        );
        $html .= sprintf(
            '<label class="form-check-label" for="%s">%s</label>',
            $id, Helper::escapeHtml($label)
        );
        $html .= '</div>';
        return $html;
    }
}