<?php

namespace App\Actions\Leconfe;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsAction;

use function Laravel\Prompts\alert;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\spin;

class QuickInstall
{
    use AsAction;

    public function handle()
    {
        return app()->getInstalledVersion();
    }

    public function asCommand(Command $command): void
    {
        $confirmUpgrade = $command->option('confirm') ?: confirm('Are you sure you want run quick install? (y/n)');
        if (! $confirmUpgrade) {
            alert('Quick install cancelled!');

            return;
        }

        if(Schema::hasTable('migrations')){
            alert('Leconfe already installed, skip process.');

            return;
        }

        $adminPassword = env('APP_ADMIN_PASSWORD');
        if (blank($adminPassword) || $adminPassword === 'admin') {
            alert('Quick install requires APP_ADMIN_PASSWORD to be set to a non-default value.');

            return;
        }

        $data = [
            'given_name' => 'Admin',
            'email' => env('APP_ADMIN_EMAIL', 'admin@leconfe.com'),
            'password' => Hash::make($adminPassword),
        ];

        try {
            alert('Installing Leconfe...');
            (new \App\Utils\Installer($data, $command))->run();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function getCommandSignature(): string
    {
        return 'leconfe:quick-install {--C|confirm}';
    }

    public function getCommandDescription(): string
    {
        return 'Quick Install Leconfe';
    }
}
