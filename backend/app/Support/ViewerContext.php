<?php

namespace App\Support;

/**
 * Per-request record of what a read-only accountant may see. When no
 * restriction is set (owners, or a grant covering every year) nothing is
 * filtered.
 */
final class ViewerContext
{
    /** @var list<string>|null */
    private ?array $financialYearIds = null;

    /** @param list<string>|null $ids */
    public function restrictFinancialYears(?array $ids): void
    {
        $this->financialYearIds = $ids;
    }

    /** @return list<string>|null */
    public function financialYearIds(): ?array
    {
        return $this->financialYearIds;
    }
}
