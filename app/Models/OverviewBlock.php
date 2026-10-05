<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_week_id', 'key', 'heading', 'body', 'ai_draft', 'sort_order'])]
class OverviewBlock extends Model
{
    protected function casts(): array
    {
        return ['ai_draft' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<ReportWeek, $this> */
    public function reportWeek(): BelongsTo
    {
        return $this->belongsTo(ReportWeek::class);
    }
}
