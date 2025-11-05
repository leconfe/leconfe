<?php

namespace Database\Seeders\Developments;

use App\Models\Conference;
use App\Models\ScheduledConference;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class ScheduledConferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Conference::lazy()->each(function (Conference $conference) {
            $date = now()->subYear(2);

            ScheduledConference::factory()
                ->count(3)
                ->for($conference)
                ->state(new Sequence(
                    function (Sequence $sequence) use ($conference, $date) {
                        $date->addYear();
                        $now = now();

                        return [
                            'title' => $conference->name.' '.$date->year,
                            'path' => $date->year,
                            'date_start' => $date->copy(),
                            'date_end' => $date->copy()->addMonth(3),
                        ];
                    },
                ))
                ->create();
        });
    }
}
