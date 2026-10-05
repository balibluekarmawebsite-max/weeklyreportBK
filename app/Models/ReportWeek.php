<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'property_id', 'start_date', 'end_date', 'year', 'week_number',
    'label', 'status', 'owner_id', 'locked_at', 'exported_at',
])]
class ReportWeek extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ReportStatus::class,
            'locked_at' => 'datetime',
            'exported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    /** @return HasMany<MonthlyStat, $this> */
    public function monthlyStats(): HasMany
    {
        return $this->hasMany(MonthlyStat::class)->orderBy('month');
    }

    /** @return HasMany<SegmentProduction, $this> */
    public function segmentProductions(): HasMany
    {
        return $this->hasMany(SegmentProduction::class)->orderBy('sort_order');
    }

    /** @return HasMany<RateCodeProduction, $this> */
    public function rateCodeProductions(): HasMany
    {
        return $this->hasMany(RateCodeProduction::class)->orderBy('sort_order');
    }

    /** @return HasMany<ChannelMonthRn, $this> */
    public function channelMonthRns(): HasMany
    {
        return $this->hasMany(ChannelMonthRn::class)->orderBy('year')->orderBy('sort_order');
    }

    /** @return HasMany<OwnerRepeaterMonth, $this> */
    public function ownerRepeaterMonths(): HasMany
    {
        return $this->hasMany(OwnerRepeaterMonth::class)->orderBy('sort_order');
    }

    /** @return HasMany<OwnerChannelMix, $this> */
    public function ownerChannelMix(): HasMany
    {
        return $this->hasMany(OwnerChannelMix::class)->orderBy('sort_order');
    }

    /** @return HasMany<OverviewBlock, $this> */
    public function overviewBlocks(): HasMany
    {
        return $this->hasMany(OverviewBlock::class)->orderBy('sort_order');
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('sort_order');
    }

    /** @return HasMany<SocialMediaMetric, $this> */
    public function socialMediaMetrics(): HasMany
    {
        return $this->hasMany(SocialMediaMetric::class)->orderBy('sort_order');
    }

    /** @return HasMany<Training, $this> */
    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class)->orderBy('sort_order');
    }

    /** @return HasMany<ActionPlan, $this> */
    public function actionPlans(): HasMany
    {
        return $this->hasMany(ActionPlan::class)->orderBy('sort_order');
    }
}
