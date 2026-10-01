<?php

namespace App\Services;

use App\Models\ReportTemplate;
use App\Support\ReportFieldCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportGenerator
{
    public function download(ReportTemplate $template): StreamedResponse
    {
        $built = $this->buildCsv($template);

        return response()->streamDownload(
            fn () => print ($built['contents']),
            $built['filename'],
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * @param  (callable(int $done, int $total): void)|null  $onProgress
     * @return array{contents: string, filename: string}
     */
    public function buildCsv(ReportTemplate $template, ?callable $onProgress = null): array
    {
        $definition = $template->definition ?? [];
        $modelType = (string) ($definition['model_type'] ?? '');
        $modelClass = ReportFieldCatalog::modelClass($modelType);

        if (! $modelClass) {
            abort(422, 'Invalid report model.');
        }

        $fieldCatalog = ReportFieldCatalog::fieldsFor($modelType);
        $fieldConfigs = collect($definition['field_configs'] ?? [])
            ->filter(fn ($config) => filled($config['field'] ?? null));

        if ($fieldConfigs->isEmpty()) {
            abort(422, 'Add at least one field to export.');
        }

        $selectedFields = $fieldConfigs->pluck('field')->values();
        $query = $this->buildQuery($modelClass, $definition, $fieldConfigs);
        $total = (clone $query)->count();
        $done = 0;

        if ($onProgress) {
            $onProgress(0, max(1, $total));
        }

        $csv = Writer::createFromFileObject(new SplTempFileObject);

        $csv->insertOne(
            $fieldConfigs
                ->map(function (array $config) use ($fieldCatalog): string {
                    $field = $config['field'] ?? '';

                    return filled($config['heading'] ?? null)
                        ? (string) $config['heading']
                        : ($fieldCatalog[$field]['label'] ?? $field);
                })
                ->all()
        );

        $query->orderBy($query->getModel()->getKeyName())
            ->chunkById(200, function ($records) use ($selectedFields, $csv, &$done, $total, $onProgress): void {
                foreach ($records as $record) {
                    $csv->insertOne(
                        $selectedFields
                            ->map(fn (string $field) => $this->resolveFieldValue($record, $field))
                            ->all()
                    );
                    $done++;

                    if ($onProgress && ($done % 25 === 0 || $done >= $total)) {
                        $onProgress($done, max(1, $total));
                    }
                }
            });

        if ($onProgress) {
            $onProgress(max($done, $total > 0 ? $total : 1), max(1, $total));
        }

        return [
            'contents' => $csv->toString(),
            'filename' => str($template->name)->slug().'-'.now()->format('Y-m-d').'.csv',
        ];
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $definition
     */
    protected function buildQuery(string $modelClass, array $definition, Collection $fieldConfigs): Builder
    {
        /** @var Builder $query */
        $query = $modelClass::query();

        $relations = $fieldConfigs
            ->pluck('field')
            ->filter(fn (string $field) => str_contains($field, '.'))
            ->map(fn (string $field) => explode('.', $field, 2)[0])
            ->unique()
            ->values()
            ->all();

        if ($relations !== []) {
            $query->with($relations);
        }

        foreach ($fieldConfigs as $config) {
            $field = $config['field'] ?? null;
            $filterType = $config['filter_type'] ?? null;

            if (! filled($field) || ! filled($filterType) || str_contains($field, '.')) {
                continue;
            }

            $this->applyFilter($query, $field, $filterType, $config);
        }

        $fromDate = $definition['from_date'] ?? null;
        $toDate = $definition['to_date'] ?? null;

        if (filled($fromDate) && filled($toDate)) {
            $query->whereBetween('created_at', [
                Carbon::parse($fromDate)->startOfDay(),
                Carbon::parse($toDate)->endOfDay(),
            ]);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function applyFilter(Builder $query, string $field, string $filterType, array $config): void
    {
        match ($filterType) {
            'equals' => $query->where($field, $config['filter_value'] ?? null),
            'contains' => $query->where($field, 'like', '%'.($config['filter_value'] ?? '').'%'),
            'starts_with' => $query->where($field, 'like', ($config['filter_value'] ?? '').'%'),
            'ends_with' => $query->where($field, 'like', '%'.($config['filter_value'] ?? '')),
            'greater_than' => $query->where($field, '>', $config['filter_value'] ?? null),
            'less_than' => $query->where($field, '<', $config['filter_value'] ?? null),
            'between' => $query->whereBetween($field, [
                $config['filter_value_from'] ?? null,
                $config['filter_value_to'] ?? null,
            ]),
            'in' => $query->whereIn($field, $config['filter_values'] ?? []),
            default => null,
        };
    }

    protected function resolveFieldValue(object $record, string $field): string
    {
        if (! str_contains($field, '.')) {
            return $this->formatValue($record->{$field} ?? null);
        }

        $value = $record;

        foreach (explode('.', $field) as $segment) {
            $value = $value?->{$segment};
        }

        return $this->formatValue($value);
    }

    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \BackedEnum) {
            return method_exists($value, 'label') ? (string) $value->label() : (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return method_exists($value, 'label') ? (string) $value->label() : $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : $value::class;
        }

        return (string) $value;
    }
}
