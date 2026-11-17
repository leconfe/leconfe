<?php

namespace App\Listeners;

use App\Models\ScheduledConference;
use App\Models\User;
use App\Services\EmailPreferences\Categories;
use App\Services\EmailPreferences\Preferences;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Events\MessageSending;

class FilterMutedEmailRecipients
{
    public function __construct(protected Preferences $preferences) {}

    /**
     * Drop recipients who muted this email category for the scheduled
     * conference of the mail subject. Returning false cancels the message.
     */
    public function handle(MessageSending $event): ?bool
    {
        $category = Categories::forMail($event->data['mailableClass'] ?? '');
        $subject = $event->data['log']->subject ?? null;

        if (! $category || ! $subject instanceof Model) {
            return null;
        }

        // Scope comes from the mail subject, never from the current panel or
        // authenticated user, so queued mail resolves the right event.
        $scheduledConference = ScheduledConference::withoutGlobalScopes()
            ->find((int) $subject->getAttribute('scheduled_conference_id'));

        if (! $scheduledConference) {
            return null;
        }

        $seen = [];
        $remaining = 0;

        foreach (['To', 'Cc', 'Bcc'] as $header) {
            $keep = [];

            foreach ($event->message->{'get'.$header}() as $address) {
                $email = mb_strtolower($address->getAddress());

                if (isset($seen[$email])) {
                    continue;
                }

                $seen[$email] = true;

                $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

                if (! $user || $this->preferences->enabled($user, $scheduledConference->conference_id, $scheduledConference->getKey(), $category)) {
                    $keep[] = $address;
                }
            }

            $event->message->{lcfirst($header)}(...$keep);
            $remaining += count($keep);
        }

        return $remaining === 0 ? false : null;
    }
}
