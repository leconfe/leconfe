<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_MAILABLE = 'App\\Mail\\Templates\\NewPaperUploadedMail';

    private const NEW_MAILABLE = 'App\\Mail\\Templates\\NewReviewFileUploadedMail';

    public function up(): void
    {
        $this->replaceMailable(self::OLD_MAILABLE, self::NEW_MAILABLE);
    }

    public function down(): void
    {
        $this->replaceMailable(self::NEW_MAILABLE, self::OLD_MAILABLE);
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
};
