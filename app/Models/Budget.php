<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'year', 'month', 'rn_sold', 'occupancy', 'arr', 'revenue'])]
class Budget extends Model
{
    protected function casts(): array
    {
        return [
            'rn_sold' => 'integer',
            'occupancy' => 'decimal:3',
            'arr' => 'decimal:2',
            'revenue' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
