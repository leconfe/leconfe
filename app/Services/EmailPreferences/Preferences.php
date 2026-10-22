<?php

namespace App\Services\EmailPreferences;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class Preferences
{
    public static function key(int $conferenceId, int $scheduledConferenceId, string $category): string
    {
        return "email_preferences.{$conferenceId}.{$scheduledConferenceId}.{$category}";
    }

    public function explicit(User $user, int $conferenceId, int $scheduledConferenceId, string $category): ?bool
    {
        if ($conferenceId <= 0 || $scheduledConferenceId <= 0) {
            return null;
        }

        // Query the relation directly so long-running queue workers see the
        // latest choice even when the notifiable carries loaded metadata.
        return $user->meta()
            ->where('key', static::key($conferenceId, $scheduledConferenceId, $category))
            ->first()
            ?->value;
    }

    public function enabled(User $user, int $conferenceId, int $scheduledConferenceId, string $category): bool
    {
        $value = $this->explicit($user, $conferenceId, $scheduledConferenceId, $category);

        if ($value !== null) {
            return $value;
        }

        // Operational emails are sent by default. Announcements fall back to the
        // former global opt-in, so existing opt-outs keep being respected.
        if ($category === Categories::ANNOUNCEMENT) {
            return (bool) $user->meta()->where('key', 'enable_new_announcement_email')->first()?->value;
        }

        return true;
    }

    /**
     * @param  array<string, bool>  $values
     */
    public function save(User $user, int $conferenceId, int $scheduledConferenceId, array $values): void
    {
        abort_unless(app(ContextAccess::class)->allows($user, $conferenceId, $scheduledConferenceId), 403);

        foreach ($values as $category => $value) {
            if (! in_array($category, Categories::all(), true) || ! is_bool($value)) {
                throw ValidationException::withMessages(['data' => __('email_preferences.invalid')]);
            }
        }

        $user->getConnection()->transaction(function () use ($user, $conferenceId, $scheduledConferenceId, $values) {
            // Serialize concurrent saves for the same user without touching
            // unrelated metadata.
            $fresh = User::query()->lockForUpdate()->findOrFail($user->getKey());

            $fresh->setManyMeta(collect($values)
                ->mapWithKeys(fn (bool $value, string $category) => [
                    static::key($conferenceId, $scheduledConferenceId, $category) => $value,
                ])
                ->all());
        });
    }
}
