<?php

namespace App\Services\Payroll;

use Illuminate\Validation\ValidationException;

class PayrollFormulaEvaluator
{
    /**
     * @var list<string>
     */
    public const VARIABLES = [
        'absent_days',
        'present_days',
        'late_minutes',
        'basic_salary',
        'hours_worked',
        'additional_hours_worked',
        'overtime_hours',
        'working_days',
        'total_days_of_payroll',
    ];

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function variableOptions(): array
    {
        return [
            ['value' => 'absent_days', 'label' => 'Absent days'],
            ['value' => 'present_days', 'label' => 'Present days'],
            ['value' => 'late_minutes', 'label' => 'Late minutes'],
            ['value' => 'basic_salary', 'label' => 'Basic salary'],
            ['value' => 'hours_worked', 'label' => 'Hours worked'],
            ['value' => 'additional_hours_worked', 'label' => 'Additional hours worked'],
            ['value' => 'overtime_hours', 'label' => 'Approved overtime hours'],
            ['value' => 'working_days', 'label' => 'Number of working days'],
            ['value' => 'total_days_of_payroll', 'label' => 'Total days of payroll'],
        ];
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

    protected function assertValidSyntax(string $formula): void
    {
        $pattern = '/^(?:'
            .implode('|', array_map(fn (string $name) => preg_quote($name, '/'), self::VARIABLES))
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
        preg_match_all(
            '/'.implode('|', array_map(fn (string $name) => preg_quote($name, '/'), self::VARIABLES))
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
