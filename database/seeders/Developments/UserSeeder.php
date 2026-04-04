<?php

namespace Database\Seeders\Developments;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Application;
use App\Models\Conference;
use App\Models\Enums\UserRole;
use App\Models\ScheduledConference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $admin = \App\Models\User::factory()->create([
            'given_name' => 'admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin'),
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $users = \App\Models\User::factory(100)->create();

        $conferences = Conference::all();
        $conferenceRoles = collect(UserRole::conferenceRoles());
        foreach ($conferences as $key => $conference) {
            app()->setCurrentConferenceId($conference->getKey());

            $users->random(2)->each(fn ($user) => $user->assignRole($conferenceRoles->random(2)));
        }

        $scheduledConferences = ScheduledConference::all();
        $scheduledConferenceRoles = collect([
            UserRole::Reviewer,
            UserRole::Author,
            UserRole::ScheduledConferenceEditor,
            UserRole::TrackEditor,
        ]);

        foreach ($scheduledConferences as $key => $scheduledConference) {
            app()->setCurrentConferenceId($scheduledConference->conference_id);
            app()->setCurrentScheduledConferenceId($scheduledConference->getKey());

            $users->random(50)->each(fn ($user) => $user->assignRole($scheduledConferenceRoles->random()));
        }
    }
}
