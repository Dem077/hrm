<?php

namespace App\Services\Payroll;

use Illuminate\Validation\ValidationException;

class PayrollFormulaEvaluator
{
    /**
     * Canonical formula variable names (snake_case identifiers used in formulas).
     *
     * @var list<string>
     */
    public const VARIABLES = [
        'absent_days',
        'present_days',
        'late_minutes',
        'basic_salary',
        'gross_salary',
        'total_deductions',
        'net_salary',
        'hours_worked',
        'additional_hours_worked',
        'overtime_hours',
        'working_days',
        'total_days_of_payroll',
    ];

    /**
     * Display labels for formula variables (single source of truth).
     *
     * @var array<string, string>
     */
    public const VARIABLE_LABELS = [
        'absent_days' => 'Absent days',
        'present_days' => 'Present days',
        'late_minutes' => 'Late minutes',
        'basic_salary' => 'Basic salary',
        'gross_salary' => 'Gross salary',
        'total_deductions' => 'Total deductions',
        'net_salary' => 'Net salary',
        'hours_worked' => 'Hours worked',
        'additional_hours_worked' => 'Additional hours worked',
        'overtime_hours' => 'Overtime approved hours',
        'working_days' => 'Working days',
        'total_days_of_payroll' => 'Total days of payroll',
    ];

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function variableOptions(): array
    {
        return collect(self::VARIABLES)
            ->map(fn (string $name) => [
                'value' => $name,
                'label' => self::labelFor($name),
            ])
            ->values()
            ->all();
    }

    public static function labelFor(string $name): string
    {
        return self::VARIABLE_LABELS[$name] ?? str_replace('_', ' ', $name);
    }

    /**
     * @param  array<string, float|int>  $variables
     */
    public function evaluate(string $formula, array $variables): float
    {
        $normalized = $this->normalize($formula);

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'calculation_formula' => 'Enter a calculation formula.',
            ]);
        }

        $this->assertValidSyntax($normalized);

        $values = [];
        foreach (self::VARIABLES as $name) {
            $values[$name] = (float) ($variables[$name] ?? 0);
        }

        $tokens = $this->tokenize($normalized);
        $index = 0;
        $result = $this->parseExpression($tokens, $index, $values);

        if ($index !== count($tokens)) {
            throw ValidationException::withMessages([
                'calculation_formula' => 'Formula has unexpected trailing characters.',
            ]);
        }

        if (! is_finite($result)) {
            throw ValidationException::withMessages([
                'calculation_formula' => 'Formula evaluation produced an invalid number.',
            ]);
        }

        return round(max(0, $result), 2);
    }

    public function assertValid(string $formula): void
    {
        $this->evaluate($formula, array_fill_keys(self::VARIABLES, 0));
    }

    protected function normalize(string $formula): string
    {
        $formula = strtolower(trim($formula));
        $formula = preg_replace('/\s+/', '', $formula) ?? '';

        return $formula;
    }

    /**
     * Longer names first so e.g. gross_salary is not partially matched.
     *
     * @return list<string>
     */
    protected function variablesByLength(): array
    {
        $names = self::VARIABLES;
        usort($names, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $names;
    }

    protected function assertValidSyntax(string $formula): void
    {
        $names = $this->variablesByLength();
        $pattern = '/^(?:'
            .implode('|', array_map(fn (string $name) => preg_quote($name, '/'), $names))
            .'|\d+(?:\.\d+)?'
            .'|[\+\-\*\/\(\)]'
            .')+$/';

        if (! preg_match($pattern, $formula)) {
            throw ValidationException::withMessages([
                'calculation_formula' => 'Formula may only use numbers, + - * / ( ), and: '
                    .implode(', ', self::VARIABLES).'.',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function tokenize(string $formula): array
    {
        $names = $this->variablesByLength();

        preg_match_all(
            '/'.implode('|', array_map(fn (string $name) => preg_quote($name, '/'), $names))
            .'|\d+(?:\.\d+)?|[\+\-\*\/\(\)]/',
            $formula,
            $matches,
        );

        return $matches[0] ?? [];
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, float>  $values
     */
    protected function parseExpression(array $tokens, int &$index, array $values): float
    {
        $value = $this->parseTerm($tokens, $index, $values);

        while ($index < count($tokens) && in_array($tokens[$index], ['+', '-'], true)) {
            $operator = $tokens[$index++];
            $right = $this->parseTerm($tokens, $index, $values);
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, float>  $values
     */
    protected function parseTerm(array $tokens, int &$index, array $values): float
    {
        $value = $this->parseFactor($tokens, $index, $values);

        while ($index < count($tokens) && in_array($tokens[$index], ['*', '/'], true)) {
            $operator = $tokens[$index++];
            $right = $this->parseFactor($tokens, $index, $values);

            if ($operator === '*') {
                $value *= $right;
            } else {
                if (abs($right) < 1e-12) {
                    throw ValidationException::withMessages([
                        'calculation_formula' => 'Division by zero in formula.',
                    ]);
                }
                $value /= $right;
            }
        }

        return $value;
    }

    /**
     * @param  list<string>  $tokens
     * @param  array<string, float>  $values
     */
    protected function parseFactor(array $tokens, int &$index, array $values): float
    {
        if ($index >= count($tokens)) {
            throw ValidationException::withMessages([
                'calculation_formula' => 'Formula is incomplete.',
            ]);
        }

        $token = $tokens[$index];

        if ($token === '+') {
            $index++;

            return $this->parseFactor($tokens, $index, $values);
        }

        if ($token === '-') {
            $index++;

            return -1 * $this->parseFactor($tokens, $index, $values);
        }

        if ($token === '(') {
            $index++;
            $value = $this->parseExpression($tokens, $index, $values);

            if ($index >= count($tokens) || $tokens[$index] !== ')') {
                throw ValidationException::withMessages([
                    'calculation_formula' => 'Missing closing parenthesis in formula.',
                ]);
            }

            $index++;

            return $value;
        }

        if (array_key_exists($token, $values)) {
            $index++;

            return $values[$token];
        }

        if (is_numeric($token)) {
            $index++;

            return (float) $token;
        }

        throw ValidationException::withMessages([
            'calculation_formula' => "Unexpected token \"{$token}\" in formula.",
        ]);
    }
}
