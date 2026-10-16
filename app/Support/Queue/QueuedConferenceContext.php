<?php

namespace App\Support\Queue;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;

/**
 * Carries the conference context a job was queued from into the job payload, and makes
 * it the current context while a queue worker runs the job. Queued notifications and
 * jobs that build emails then resolve templates, settings and translated content for
 * the conference they came from.
 */
class QueuedConferenceContext
{
    public const PAYLOAD_KEY = 'leconfeConferenceContext';

    /**
     * The contexts that were current before each running job, innermost last.
     *
     * @var array<int, array{0: int|null, 1: int|null}>
     */
    protected static array $previous = [];

    public static function register(Dispatcher $events): void
    {
        static::$previous = [];

        Queue::createPayloadUsing(fn () => [
            static::PAYLOAD_KEY => app()->getConferenceContext(),
        ]);

        $events->listen(JobProcessing::class, function (JobProcessing $event) {
            static::$previous[] = app()->getConferenceContext();

            $context = $event->job->payload()[static::PAYLOAD_KEY] ?? null;

            if (is_array($context) && count($context) === 2) {
                app()->setConferenceContext(...array_map(
                    fn ($id) => is_numeric($id) ? (int) $id : null,
                    array_values($context),
                ));
            }
        });

        $restore = function () {
            if (static::$previous) {
                app()->setConferenceContext(...array_pop(static::$previous));
            }
        };

        $events->listen(JobProcessed::class, $restore);
        $events->listen(JobExceptionOccurred::class, $restore);
    }
}
