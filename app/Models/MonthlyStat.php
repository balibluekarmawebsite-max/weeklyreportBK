<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'report_week_id', 'month', 'rn_sold',
    'occ_actual', 'occ_budget', 'occ_ly',
    'arr_actual', 'arr_budget', 'arr_ly',
    'rev_actual', 'rev_budget', 'rev_ly',
])]
class MonthlyStat extends Model
{
    protected function casts(): array
    {
        return [
            'rn_sold' => 'integer',
            'occ_actual' => 'float', 'occ_budget' => 'float', 'occ_ly' => 'float',
            'arr_actual' => 'float', 'arr_budget' => 'float', 'arr_ly' => 'float',
            'rev_actual' => 'float', 'rev_budget' => 'float', 'rev_ly' => 'float',
        ];
    }

    /** @return BelongsTo<ReportWeek, $this> */
    public function reportWeek(): BelongsTo
    {
        return $this->belongsTo(ReportWeek::class);
    }
}
