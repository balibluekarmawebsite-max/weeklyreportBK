<?php

namespace App\Support;

use App\Models\Property;

/**
 * The property the user is currently viewing (chosen in the top-bar switcher,
 * remembered in the session), defaulting to the first active property.
 */
class Workspace
{
    public const SESSION_KEY = 'current_property_id';

    public static function currentProperty(): ?Property
    {
        $id = session(self::SESSION_KEY);

        if ($id) {
            $property = Property::where('is_active', true)->find($id);
            if ($property) {
                return $property;
            }
        }

        return Property::where('is_active', true)->orderBy('id')->first();
    }

    public static function setProperty(int $id): void
    {
        session([self::SESSION_KEY => $id]);
    }
}
