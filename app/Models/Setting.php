<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'group', 'key', 'value'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /** @return BelongsTo<Property, $this> */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /** Read a single application-wide setting value. */
    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        return static::query()
            ->whereNull('property_id')
            ->where('group', $group)
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    /** Create or update a single application-wide setting value. */
    public static function put(string $group, string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['property_id' => null, 'group' => $group, 'key' => $key],
            ['value' => $value],
        );
    }
}
