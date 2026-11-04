<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Hides rows outside the financial years granted to the current accountant. */
final class FinancialYearVisibilityScope implements Scope
{
    public function __construct(private readonly string $column = 'financial_year_id') {}

    public function apply(Builder $builder, Model $model): void
    {
        $ids = app(ViewerContext::class)->financialYearIds();

        if ($ids === null) {
            return;
        }

        $builder->whereIn($model->qualifyColumn($this->column), $ids);
    }
}
