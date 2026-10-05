<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_week_id', 'label', 'rn_sold', 'gross_revenue', 'sort_order'])]
class RateCodeProduction extends Model
{
    protected function casts(): array
    {
        return ['rn_sold' => 'integer', 'gross_revenue' => 'float', 'sort_order' => 'integer'];
    }

    public function arr(): ?float
    {
        return $this->rn_sold ? (float) $this->gross_revenue / $this->rn_sold : null;
    }

    /** @return BelongsTo<ReportWeek, $this> */
    public function reportWeek(): BelongsTo
    {
        return $this->belongsTo(ReportWeek::class);
    }
}
