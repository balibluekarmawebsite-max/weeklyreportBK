<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_week_id', 'label', 'room_nights', 'revenue', 'sort_order'])]
class OwnerRepeaterMonth extends Model
{
    protected function casts(): array
    {
        return ['room_nights' => 'integer', 'revenue' => 'float', 'sort_order' => 'integer'];
    }

    /** ADR = revenue / room nights (null-safe). */
    public function adr(): ?float
    {
        return $this->room_nights ? (float) $this->revenue / $this->room_nights : null;
    }

    /** @return BelongsTo<ReportWeek, $this> */
    public function reportWeek(): BelongsTo
    {
        return $this->belongsTo(ReportWeek::class);
    }
}
