<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['report_week_id', 'platform', 'metric_key', 'last_week', 'this_week', 'sort_order'])]
class SocialMediaMetric extends Model
{
    protected function casts(): array
    {
        return ['last_week' => 'integer', 'this_week' => 'integer', 'sort_order' => 'integer'];
    }

    public function growth(): ?int
    {
        if ($this->last_week === null || $this->this_week === null) {
            return null;
        }

        return $this->this_week - $this->last_week;
    }

    public function growthPercent(): ?float
    {
        if (! $this->last_week || $this->this_week === null) {
            return null;
        }

        return (($this->this_week - $this->last_week) / $this->last_week) * 100;
    }

    /** @return BelongsTo<ReportWeek, $this> */
    public function reportWeek(): BelongsTo
    {
        return $this->belongsTo(ReportWeek::class);
    }
}
