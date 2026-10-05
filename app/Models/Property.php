<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'rooms_count', 'currency', 'timezone',
    'logo_path', 'primary_color', 'accent_color', 'export_footer', 'is_active',
])]
class Property extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rooms_count' => 'integer',
        ];
    }

    /** @return HasMany<ReportWeek, $this> */
    public function reportWeeks(): HasMany
    {
        return $this->hasMany(ReportWeek::class);
    }

    /** @return HasMany<Budget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /** @return HasMany<Channel, $this> */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    /** @return HasMany<RateCode, $this> */
    public function rateCodes(): HasMany
    {
        return $this->hasMany(RateCode::class);
    }

    /** @return HasMany<MarketSegment, $this> */
    public function marketSegments(): HasMany
    {
        return $this->hasMany(MarketSegment::class);
    }
}
