<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Validator ported from the original app.
 *
 * Rule strings look like "required|email|min:3|unique:teams,name,4". The
 * messages are intentionally identical to the original implementation so the
 * smoke suite keeps matching (e.g. "... has already been taken.").
 */
class Validator
{
    /**
     * @return array<string, array<int, string>> Field name => list of messages.
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $empty = $value === null || $value === '' || $value === [];

            foreach (explode('|', (string) $ruleString) as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramString] = explode(':', $rule, 2);
                    $params = explode(',', $paramString);
                }

                // Non-required rules are skipped for empty values.
                if ($rule !== 'required' && $empty) {
                    continue;
                }

                $label = self::label($field);
                $message = null;

                switch ($rule) {
                    case 'required':
                        if ($empty) {
                            $message = $label.' is required.';
                        }
                        break;

                    case 'email':
                        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $message = $label.' must be a valid email address.';
                        }
                        break;

                    case 'url':
                        if (! filter_var($value, FILTER_VALIDATE_URL)) {
                            $message = $label.' must be a valid URL.';
                        }
                        break;

                    case 'min':
                        if (is_numeric($value) && ! is_string($value)) {
                            if ($value < (float) $params[0]) {
                                $message = $label.' must be at least '.$params[0].'.';
                            }
                        } elseif (mb_strlen(trim((string) $value)) < (int) $params[0]) {
                            $message = $label.' must be at least '.$params[0].' characters.';
                        }
                        break;

                    case 'max':
                        if (is_numeric($value) && ! is_string($value)) {
                            if ($value > (float) $params[0]) {
                                $message = $label.' must not exceed '.$params[0].'.';
                            }
                        } elseif (mb_strlen(trim((string) $value)) > (int) $params[0]) {
                            $message = $label.' must not exceed '.$params[0].' characters.';
                        }
                        break;

                    case 'numeric':
                        if (! is_numeric($value)) {
                            $message = $label.' must be a number.';
                        }
                        break;

                    case 'integer':
                        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $message = $label.' must be a whole number.';
                        }
                        break;

                    case 'date':
                        if (! is_string($value) || strtotime($value) === false) {
                            $message = $label.' must be a valid date.';
                        }
                        break;

                    case 'in':
                        if (! in_array((string) $value, $params, true)) {
                            $message = $label.' is invalid.';
                        }
                        break;

                    case 'unique':
                        $table = $params[0];
                        $column = $params[1];
                        $ignoreId = isset($params[2]) ? (int) $params[2] : 0;
                        $query = DB::table($table)->where($column, $value);
                        if ($ignoreId > 0) {
                            $query->where('id', '!=', $ignoreId);
                        }
                        if ($query->count() > 0) {
                            $message = $label.' has already been taken.';
                        }
                        break;

                    case 'exists':
                        $table = $params[0];
                        $column = $params[1] ?? 'id';
                        $count = DB::table($table)->where($column, $value)->count();
                        if ($count === 0) {
                            $message = 'Selected '.strtolower($label).' does not exist.';
                        }
                        break;

                    case 'nullable':
                    case 'confirmed':
                        break;
                }

                if ($message !== null) {
                    $errors[$field][] = $message;
                    break; // One message per field is enough.
                }
            }
        }

        return $errors;
    }

    private static function label(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}
