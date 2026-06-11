<?php

namespace Tests\Unit\Classes;

use App\Classes\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_can_set_causer_from_model(): void
    {
        $user = new User;
        $user->id = 1;
        $submission = new \App\Models\Submission;
        $submission->id = 1;

        $log = Log::make(
            subject: $submission,
            name: 'email',
            description: 'Test email',
        )->by($user);

        $this->assertEquals(1, $log->causerId);
        $this->assertEquals(User::class, $log->causerType);
    }

    public function test_log_serializes_causer_scalars(): void
    {
        $user = new User;
        $user->id = 1;
        $submission = new \App\Models\Submission;
        $submission->id = 1;

        $log = Log::make(
            subject: $submission,
            name: 'email',
            description: 'Test email',
        )->by($user);

        $serialized = serialize($log);
        $unserialized = unserialize($serialized);

        $this->assertEquals(1, $unserialized->causerId);
        $this->assertEquals(User::class, $unserialized->causerType);
    }

    public function test_log_by_anonymous_sets_null_causer(): void
    {
        $submission = new \App\Models\Submission;
        $submission->id = 1;

        $log = Log::make(
            subject: $submission,
            name: 'email',
            description: 'Test email',
        )->byAnonymous();

        $this->assertNull($log->causerId);
        $this->assertNull($log->causerType);
    }
}
