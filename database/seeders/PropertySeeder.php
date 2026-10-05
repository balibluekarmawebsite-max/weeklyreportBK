<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        Property::updateOrCreate(
            ['code' => 'BKDS'],
            [
                'name' => 'Blue Karma Dijiwa Seminyak',
                // TODO: confirm the real total room count (used for occupancy %).
                'rooms_count' => 60,
                'currency' => 'IDR',
                'timezone' => 'Asia/Makassar',
                'primary_color' => '#0F3D3E',
                'accent_color' => '#C9A24B',
                'export_footer' => 'Confidential — Blue Karma Dijiwa Seminyak',
                'is_active' => true,
            ],
        );
    }
}
