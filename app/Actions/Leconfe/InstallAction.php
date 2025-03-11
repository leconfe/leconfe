<?php

namespace App\Actions\Leconfe;

use App\Utils\PermissionChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Jackiedo\Timezonelist\Facades\Timezonelist;
use Lorisleiva\Actions\Concerns\AsAction;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class InstallAction
{
    use AsAction;

    public function handle(array $params)
    {
        $upgrade = new \App\Utils\Installer($params);
        $upgrade->run();
    }

    public function asCommand(Command $command): void
    {
        if (app()->isInstalled()) {
            error('Leconfe is already installed');

            return;
        }

        info('Welcome to leconfe installer.');

        $permissionChecker = app(PermissionChecker::class);

        $folderPermission = collect($permissionChecker->checkFolders([
            'storage/framework/' => 'storage/framework/',
            'storage/logs/' => 'storage/logs/',
            'storage/app/public/' => 'storage/app/public/',
            'bootstrap/cache/' => 'bootstrap/cache/',
        ]));

        info('Checking folder permissions...');

        table(
            ['folder', 'permission'],
            $folderPermission->map(fn ($permission, $folder) => [$folder, $permission ? '✅' : '❌'])->values()->toArray()
        );

        // check if folder is not writable
        if ($folderPermission->contains(false)) {
            error('Some folders are not writable or not exist. Please fix it before continue.');

            return;
        }

        info('Account information');

        $data['given_name'] = text('What is your given name?', required: true);
        $data['family_name'] = text('What is your family name?', required: true);
        $data['email'] = text(
            label: 'What is your email?',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Please enter a valid email address.'
        );

        // Keep prompting password until a valid match is entered
        while (true) {
            $password = password('What is your password?');
            if (! $password) {
                break;
            }

            $password_confirmation = password('Please confirm your password?', required: true);
            // Compare password
            if ($password === $password_confirmation) {
                // Passwords match, break out of the loop
                $data['password'] = $password;
                break;
            } else {
                error('Password confirmation does not match.');
            }
        }

        $data['newsletter'] = confirm(
            label: 'Subscribe to newsletter',
            default: true,
            yes: 'Yes',
            no: 'No',
            hint: 'Receive updates, tips, and important announcements'
        );

        info('Application information');
        $data['url'] = text('What is your application url?', placeholder: 'http://localhost', required: true);

        info('Timezone information');
        info('The timezone that application gonna use.');
        $timezones = collect(Timezonelist::splitGroup(false)->toArray(false));
        $data['timezone'] = search(
            label: 'Select timezone',
            options: fn (string $value) => strlen($value) > 0
                ? $timezones->filter(fn ($timezone) => str_contains(strtolower($timezone), strtolower($value)))->toArray()
                : $timezones->toArray(),
            placeholder: 'Asia/Makassar',
            required: true
        );

        info('Database information');

        while (true) {
            $data['db_connection'] = 'mysql';
            $data['db_username'] = text('What is your database username?', required: true);
            $data['db_password'] = password('What is your database password?', required: true);
            $data['db_name'] = text('What is your database name?', default: 'leconfe', required: true);
            $data['db_host'] = text('What is your database host?', default: '127.0.0.1', required: true);
            $data['db_port'] = text('What is your database port?', default: '3306', required: true);

            try {
                spin(fn () => $this->reconnectDbWithNewData($data), 'Testing database connection...');

                info('Database connection success.');

                break;
            } catch (\Throwable $th) {
                error('Cannot connect to database with provided information. Please check again.');
                error($th->getMessage());
            }
        }

        info('Please review your information before continue.');
        table(
            ['key', 'value'],
            collect($data)->map(fn ($value, $key) => [$key, $value])->toArray()
        );

        if (! confirm('Are you sure to continue?')) {
            return;
        }

        try {

            spin(
                fn () => (new \App\Utils\Installer($data, $command))->run(),
                'Installing application...'
            );
        } catch (\Throwable $th) {
            throw $th;
        }

        info('Application installed.');
    }

    private function prepareDatabaseConnection($data): array
    {
        $connectionArray = config('database.connections.mysql', []);

        return array_merge($connectionArray, [
            'driver' => $data['db_connection'],
            'database' => $data['db_name'],
            'host' => $data['db_host'],
            'port' => $data['db_port'],
            'username' => $data['db_username'],
            'password' => $data['db_password'],
        ]);
    }

    protected function reconnectDbWithNewData($data)
    {
        $connectionArray = $this->prepareDatabaseConnection($data);

        Config::set('database.connections.mysql', $connectionArray);

        DB::purge();

        // reconnect to database with new settings
        DB::reconnect();

        DB::connection()->getPdo();
    }

    public function getCommandSignature(): string
    {
        return 'leconfe:install';
    }

    public function getCommandDescription(): string
    {
        return 'Install leconfe application';
    }
}
