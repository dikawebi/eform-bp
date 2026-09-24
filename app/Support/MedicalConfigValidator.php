<?php

namespace App\Support;

use InvalidArgumentException;

final class MedicalConfigValidator
{
    public static function validate(): void
    {
        $medical = config('eform.medical', []);
        foreach (['benefit_types', 'allowed_documents', 'required_documents'] as $key) {
            $value = $medical[$key] ?? null;
            if (! is_array($value) || $value === [] || collect($value)->contains(fn ($item) => ! is_string($item) || trim($item) === '')
                || count($value) !== count(array_unique($value))) {
                throw new InvalidArgumentException("Invalid medical configuration: {$key} must be a non-empty list of unique non-empty strings.");
            }
        }
        $allowed = array_values(array_unique($medical['allowed_documents']));
        $required = array_values(array_unique($medical['required_documents']));
        $invalid = array_values(array_diff($required, $allowed));

        if ($invalid !== []) {
            throw new InvalidArgumentException(sprintf(
                'Invalid medical document configuration: required_documents must be a subset of allowed_documents; invalid: %s.',
                implode(', ', $invalid),
            ));
        }

        $max = $medical['max_file_size_kb'] ?? null;
        if (! is_int($max) || $max < 1 || $max > 102400) {
            throw new InvalidArgumentException('Invalid medical configuration: max_file_size_kb must be an integer between 1 and 102400.');
        }
    }
}
