<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_MAILABLE = 'App\\Mail\\Templates\\AcceptAbstractMail';

    private const NEW_MAILABLE = 'App\\Mail\\Templates\\SendForReviewMail';

    private const OLD_NOTIFICATION = 'App\\Notifications\\AbstractAccepted';

    private const NEW_NOTIFICATION = 'App\\Notifications\\SubmissionSentForReview';

    public function up(): void
    {
        $this->replaceMailable(self::OLD_MAILABLE, self::NEW_MAILABLE);
        $this->replaceNotificationType(self::OLD_NOTIFICATION, self::NEW_NOTIFICATION);
    }

    public function down(): void
    {
        $this->replaceMailable(self::NEW_MAILABLE, self::OLD_MAILABLE);
        $this->replaceNotificationType(self::NEW_NOTIFICATION, self::OLD_NOTIFICATION);
    }

    private function replaceMailable(string $from, string $to): void
    {
        foreach (['mail_templates', 'default_mail_templates'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'mailable')) {
                continue;
            }

            DB::table($table)
                ->where('mailable', $from)
                ->update(['mailable' => $to]);
        }
    }

    private function replaceNotificationType(string $from, string $to): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasColumn('notifications', 'type')) {
            return;
        }

        DB::table('notifications')
            ->where('type', $from)
            ->update(['type' => $to]);
    }
};
