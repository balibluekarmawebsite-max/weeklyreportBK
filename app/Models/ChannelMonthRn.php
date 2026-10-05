<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'report_week_id', 'year', 'source_label', 'sort_order',
    'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
])]
class ChannelMonthRn extends Model
{
    protected $table = 'channel_month_rn';

    public const MONTHS = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];

    protected function casts(): array
    {
        $casts = ['year' => 'integer', 'sort_order' => 'integer'];
        foreach (self::MONTHS as $m) {
            $casts[$m] = 'integer';
        }

        return $casts;
    }

    /** Year-to-date total room nights for this source. */
    public function ytd(): int
    {
        return array_sum(array_map(fn ($m) => (int) $this->{$m}, self::MONTHS));
    }

    /** @return BelongsTo<ReportWeek, $this> */
    public function reportWeek(): BelongsTo
    {
        return $this->belongsTo(ReportWeek::class);
    }
}
