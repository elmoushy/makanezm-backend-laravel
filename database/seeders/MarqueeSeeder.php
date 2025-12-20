<?php

namespace Database\Seeders;

use App\Models\Marquee;
use Illuminate\Database\Seeder;

class MarqueeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $marquees = [
            [
                'text' => 'subscribe & save 15%',
                'is_active' => true,
                'order' => 1,
            ]
        ];

        foreach ($marquees as $marquee) {
            Marquee::create($marquee);
        }
    }
}
