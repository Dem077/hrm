<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use UnitEnum;

class ReportFieldCatalog
{
    /** @var array<string, string> */
    public const MODELS = [
        'Employee' => 'Employees',
        'LeaveRequest' => 'Leave Requests',
        'OvertimeRequest' => 'Overtime Requests',
        'DutyRoster' => 'Duty Rosters',
        'PayrollRun' => 'Payroll Runs',
        'PayrollRunItem' => 'Payroll Run Items',
        'ZktAttendanceLog' => 'Attendance Punch Logs',
        'ZktDevice' => 'Attendance Machines',
        'LeaveType' => 'Leave Types',
        'PublicHoliday' => 'Public Holidays',
    ];

    /** @var list<string> */
    protected const EXCLUDED_COLUMNS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'device_password',
        'profile_photo_path',
    ];

    public static function modelLabel(string $modelType): string
    {
        return self::MODELS[$modelType] ?? $modelType;
    }

    /**
     * @return array<string, array{label: string, type: string, relation: ?string, input: string, options: array<string, string>}>
     */
    public static function fieldsFor(string $modelType): array
    {
        $modelClass = self::modelClass($modelType);

        if (! $modelClass) {
            return [];
        }

        /** @var Model $model */
        $model = new $modelClass;

        $fields = collect(Schema::getColumnListing($model->getTable()))
            ->reject(fn (string $column) => in_array($column, self::EXCLUDED_COLUMNS, true))
            ->mapWithKeys(fn (string $column) => [
                $column => self::columnConfig($modelClass, $column, 'column', null),
            ]);

        foreach (self::relationshipMethods($model) as $relation) {
            $relationModel = $model->{$relation}()->getRelated();
            $relationTable = $relationModel->getTable();

            foreach (Schema::getColumnListing($relationTable) as $column) {
                if (in_array($column, self::EXCLUDED_COLUMNS, true)) {
                    continue;
                }

                $key = "{$relation}.{$column}";

                $fields->put($key, self::columnConfig(
                    $relationModel::class,
                    $column,
                    'relation',
                    $relation,
                    self::humanize($relation).' · '.self::humanize($column),
                ));
            }
        }

        return $fields->sortBy('label')->all();
    }

    public static function fieldConfig(string $modelType, ?string $field): ?array
    {
        if (! filled($field)) {
            return null;
        }

        return self::fieldsFor($modelType)[$field] ?? null;
    }

    public static function isStatusField(string $modelType, ?string $field): bool
    {
        $config = self::fieldConfig($modelType, $field);

        return ($config['input'] ?? 'text') === 'status';
    }

    /**
     * @return array<string, string>
     */
    public static function filterTypesForField(string $modelType, ?string $field): array
    {
        if (self::isStatusField($modelType, $field)) {
            return [
                'equals' => 'Equals',
                'in' => 'In list',
            ];
        }

        return [
            'equals' => 'Equals',
            'contains' => 'Contains',
            'starts_with' => 'Starts with',
            'ends_with' => 'Ends with',
            'greater_than' => 'Greater than',
            'less_than' => 'Less than',
            'between' => 'Between',
            'in' => 'In list',
        ];
    }

    /**
     * @return array{label: string, type: string, relation: ?string, input: string, options: array<string, string>}
     */
    protected static function columnConfig(
        string $modelClass,
        string $column,
        string $type,
        ?string $relation,
        ?string $label = null,
    ): array {
        $casts = (new $modelClass)->getCasts();
        $options = self::enumOptionsForColumn($casts, $column);

        return [
            'label' => $label ?? self::humanize($column),
            'type' => $type,
            'relation' => $relation,
            'input' => $options !== [] ? 'status' : 'text',
            'options' => $options,
        ];
    }

    /**
     * @param  array<string, string>  $casts
     * @return array<string, string>
     */
    protected static function enumOptionsForColumn(array $casts, string $column): array
    {
        if (! isset($casts[$column])) {
            return [];
        }

        return self::enumOptions($casts[$column]);
    }

    /**
     * @return array<string, string>
     */
    protected static function enumOptions(string $enumClass): array
    {
        if (! enum_exists($enumClass)) {
            return [];
        }

        return collect($enumClass::cases())
            ->mapWithKeys(function (UnitEnum $case) {
                $value = $case instanceof \BackedEnum ? $case->value : $case->name;
                $label = method_exists($case, 'label')
                    ? (string) $case->label()
                    : self::humanize((string) $value);

                return [$value => $label];
            })
            ->all();
    }

    /**
     * @return array{models: list<array{value: string, label: string, fields: list<array<string, mixed>>}>, filter_types: list<array{value: string, label: string}>}
     */
    public static function inertiaCatalog(): array
    {
        $models = [];

        foreach (self::MODELS as $value => $label) {
            $models[] = [
                'value' => $value,
                'label' => $label,
                'fields' => self::inertiaFields($value),
            ];
        }

        return [
            'models' => $models,
            'filter_types' => [
                ['value' => 'equals', 'label' => 'Equals'],
                ['value' => 'contains', 'label' => 'Contains'],
                ['value' => 'starts_with', 'label' => 'Starts with'],
                ['value' => 'ends_with', 'label' => 'Ends with'],
                ['value' => 'greater_than', 'label' => 'Greater than'],
                ['value' => 'less_than', 'label' => 'Less than'],
                ['value' => 'between', 'label' => 'Between'],
                ['value' => 'in', 'label' => 'In list'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function inertiaFields(string $modelType): array
    {
        return collect(self::fieldsFor($modelType))
            ->map(fn (array $config, string $field) => [
                'value' => $field,
                'label' => $config['label'],
                'input' => $config['input'],
                'options' => collect($config['options'] ?? [])
                    ->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])
                    ->values()
                    ->all(),
                'filter_types' => collect(self::filterTypesForField($modelType, $field))
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values()
                    ->all(),
                'is_relation' => str_contains($field, '.'),
                'kind' => self::fieldKind($field, $config),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{input?: string}  $config
     */
    public static function fieldKind(string $field, array $config): string
    {
        if (($config['input'] ?? 'text') === 'status') {
            return 'category';
        }

        if (str_contains($field, '.')) {
            return 'related';
        }

        $name = strtolower($field);

        if (
            str_ends_with($name, '_at')
            || str_ends_with($name, '_date')
            || $name === 'date'
        ) {
            return 'date';
        }

        if (preg_match('/(amount|total|qty|quantity|price|cost|salary|days|hours|minutes)/', $name)) {
            return 'number';
        }

        if ($name === 'id' || str_ends_with($name, '_id')) {
            return 'id';
        }

        return 'text';
    }

    public static function modelClass(string $modelType): ?string
    {
        if (! array_key_exists($modelType, self::MODELS)) {
            return null;
        }

        $class = "App\\Models\\{$modelType}";

        return class_exists($class) ? $class : null;
    }

    /**
     * Only BelongsTo / MorphTo — HasMany would explode into non-flat exports.
     *
     * @return list<string>
     */
    protected static function relationshipMethods(Model $model): array
    {
        $reflection = new ReflectionClass($model);

        return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method) => $method->getDeclaringClass()->getName() === $model::class)
            ->filter(fn (ReflectionMethod $method) => $method->getNumberOfRequiredParameters() === 0)
            ->filter(function (ReflectionMethod $method) use ($model) {
                try {
                    $result = $method->invoke($model);

                    return $result instanceof BelongsTo || $result instanceof MorphTo;
                } catch (\Throwable) {
                    return false;
                }
            })
            ->map(fn (ReflectionMethod $method) => $method->getName())
            ->values()
            ->all();
    }

    protected static function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
