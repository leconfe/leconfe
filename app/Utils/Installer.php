<?php

namespace App\Utils;

use App\Actions\User\UserCreateAction;
use App\Events\AppInstalled;
use App\Models\Enums\UserRole;
use App\Models\User;
use App\Models\Version;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\spin;

class Installer
{
    public function __construct(
        public array $params,
        public ?Command $command = null,
    ) {}

    public function run()
    {
        try {
            $this->configureEnv();
            $this->configureMigration();
            $this->configureSite();
            $this->configureAccount();
            $this->configureApplication();
            $this->configureOptimization();
        } catch (\Throwable $th) {
            $this->clearCache();
            $this->removeEnvFile();

            throw $th;
        }
    }

    private function configureMigration()
    {
        try {
            Schema::dropAllTables();
            $this->call('migrate:fresh', [
                '--force' => true,
                '--seed' => true,
            ]);
        } catch (\Throwable $th) {
            Schema::dropAllTables();

            throw $th;
        }
    }

    public function configureOptimization()
    {
        $this->call('optimize:clear');
        $this->iconCache();
    }

    public function clearCache()
    {
        $this->call('optimize:clear');
        $this->call('modelCache:clear');
    }

    public function call(string $command, array $arguments = [], ?string $info = null)
    {
        if ($this->command) {
            $callableCommand = fn () => $this->command->callSilently($command, $arguments);
            $info ? spin($callableCommand, $info) : $callableCommand();
        } else {
            Artisan::call($command, $arguments);
        }
    }

    public function iconCache(): void
    {
        $factory = app(\BladeUI\Icons\Factory::class);
        $manifest = app(\BladeUI\Icons\IconsManifest::class);

        $manifest->write($factory->all());
    }

    private function configureEnv()
    {
        $envs = [
            'APP_DEBUG' => 'false',
            'APP_URL' => $this->readParam('url') ?? url('/'),
            'APP_KEY' => 'base64:'.base64_encode(Encrypter::generateKey(config('app.cipher'))),
            'APP_TIMEZONE' => $this->readParam('timezone'),
            'DB_CONNECTION' => $this->readParam('db_connection'),
            'DB_HOST' => $this->readParam('db_host'),
            'DB_PORT' => $this->readParam('db_port'),
            'DB_DATABASE' => $this->readParam('db_name'),
            'DB_USERNAME' => $this->readParam('db_username'),
            'DB_PASSWORD' => $this->readParam('db_password'),
        ];

        $this->copyStubToPath('env', base_path('.env'), $envs);

    }

    /**
     * @param  array<string, string>  $replacements
     */
    public function copyStubToPath(string $stub, string $targetPath, array $replacements = []): void
    {
        $filesystem = app(Filesystem::class);

        $stubPath = base_path('stubs'.DIRECTORY_SEPARATOR."{$stub}.stub");

        $stub = str($filesystem->get($stubPath));

        foreach ($replacements as $key => $replacement) {
            $stub = $stub->replace("{{ {$key} }}", $replacement);
        }

        $stub = (string) $stub;

        $this->writeFile($targetPath, $stub);
    }

    public function writeFile(string $path, string $contents): void
    {
        $filesystem = app(Filesystem::class);

        $filesystem->ensureDirectoryExists(
            pathinfo($path, PATHINFO_DIRNAME),
        );

        $filesystem->put($path, $contents);
    }

    public function readParam(string $key)
    {
        return $this->params[$key] ?? null;
    }

    private function configureAccount(): User
    {
        try {
            DB::beginTransaction();

            $user = UserCreateAction::run([
                'given_name' => $this->readParam('given_name'),
                'family_name' => $this->readParam('family_name'),
                'email' => $this->readParam('email'),
                'password' => $this->readParam('password'),
            ]);

            $user->assignRole(UserRole::Admin->value);

            auth()->login($user);

            DB::commit();
        } catch (\Throwable $th) {

            DB::rollBack();

            throw $th;
        }

        return $user;
    }

    private function configureSite()
    {
        $site = app()->getSite();
        $site->setMeta('newsletter', $this->readParam('newsletter'));
        $site->setMeta('survey_referral_source', $this->readParam('survey_referral_source'));
        $site->setMeta('survey_important_features', $this->readParam('survey_important_features'));
        $site->save();
    }

    private function removeEnvFile()
    {
        $filesystem = app(Filesystem::class);

        $filesystem->delete(base_path('.env'));
    }

    private function configureApplication()
    {
        Version::application();
        AppInstalled::dispatch();
    }
}
