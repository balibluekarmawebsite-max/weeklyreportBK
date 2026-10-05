<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        // Room counts are estimates derived from the sample reports —
        // confirm the true licensed room count for each property.
        $properties = [
            ['code' => 'BKDS', 'name' => 'Blue Karma Dijiwa Seminyak', 'rooms_count' => 16],
            ['code' => 'BKDU', 'name' => 'Blue Karma Dijiwa Ubud', 'rooms_count' => 20],
            ['code' => 'BKV', 'name' => 'Blue Karma Villas', 'rooms_count' => 15],
        ];

        foreach ($properties as $p) {
            Property::updateOrCreate(
                ['code' => $p['code']],
                [
                    'name' => $p['name'],
                    'rooms_count' => $p['rooms_count'],
                    'currency' => 'IDR',
                    'timezone' => 'Asia/Makassar',
                    'primary_color' => '#0F3D3E',
                    'accent_color' => '#C9A24B',
                    'export_footer' => 'Confidential — '.$p['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
