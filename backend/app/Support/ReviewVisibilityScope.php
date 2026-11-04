<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** A review is visible only while its document or transaction is visible. */
final class ReviewVisibilityScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app(ViewerContext::class)->financialYearIds() === null) {
            return;
        }

        $builder->where(function (Builder $query): void {
            $query->whereHas('document')->orWhereHas('transaction');
        });
    }
}
