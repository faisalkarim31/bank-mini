<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

trait AppliesDateFilter
{
    use InteractsWithPageFilters;

    protected function applyDateFilter(Builder $query, string $column = 'created_at'): Builder
    {
        return $query
            ->when($this->pageFilters['startDate'] ?? null, fn ($q, $d) => $q->whereDate($column, '>=', $d))
            ->when($this->pageFilters['endDate'] ?? null, fn ($q, $d) => $q->whereDate($column, '<=', $d));
    }
}
