<?php

namespace App\Providers;

use App\Actions\Leconfe\CheckLatestVersion;
use App\Application;
use App\Classes\Setting;
use App\Console\Kernel as ConsoleKernel;
use App\Events\UserLoggedIn;
use App\Http\Kernel as HttpKernel;
use App\Listeners\SubmissionEventSubscriber;
use App\Managers\MetaTagManager;
use App\Managers\SidebarManager;
use App\Models\Conference;
use App\Models\ScheduledConference;
use App\Routing\CustomUrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Illuminate\Events\queueable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(SidebarManager::class, function () {
            return new SidebarManager;
        });

        $this->app->scoped('metatag', function () {
            return new MetaTagManager;
        });

        $this->app->bind(Setting::class, function ($app) {
            return new Setting;
        });

        // Use a custom URL generator to accomodate multi context.
        // This implementation is copied from Illuminate\Routing\RoutingServiceProvider::registerUrlGenerator
        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();

            // The URL generator needs the route collection that exists on the router.
            // Keep in mind this is an object, so we're passing by references here
            // and all the registered routes will be available to the generator.
            $app->instance('routes', $routes);

            return new CustomUrlGenerator(
                $routes,
                $app->rebinding(
                    'request',
                    function ($app, $request) {
                        $app['url']->setRequest($request);
                    }
                ),
                $app['config']['app.asset_url']
            );
        });

        $this->app->extend(Http::class, function ($service, $app) {
            return $service->withHeaders([
                'Leconfe-Version' => app()->getCodeVersion(),
                'User-Agent' => 'Leconfe/'.app()->getCodeVersion(),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->setupModel();
        $this->setupStorage();
        $this->extendStr();
        $this->extendBlade();
        $this->detectConference();
        $this->handleEvent();
    }

    protected function handleEvent()
    {
        Event::listen(queueable(function (UserLoggedIn $event) {
            try {
                CheckLatestVersion::run();
            } catch (\Throwable $th) {
                //
            }
        }));
        Event::subscribe(SubmissionEventSubscriber::class);
    }

    protected function extendBlade(): void
    {
        Blade::directive('hook', function (string $name) {
            return '<?php $output = null; \App\Facades\Hook::call('."$name".',[&$output]); echo $output; ?>';
        });
    }

    protected function extendStr(): void
    {
        /**
         * Add macro to Str class to mask email address.
         */
        Str::macro('maskEmail', function ($email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }

            $mail_parts = explode('@', $email);

            $domain_parts = explode('.', $mail_parts[1]);

            $mail_parts[0] = Str::mask($mail_parts[0], '*', 2, strlen($mail_parts[0])); // show first 2 letters and last 1 letter
            $domain_parts[0] = Str::mask($domain_parts[0], '*', 2, strlen($domain_parts[0])); // same here
            $mail_parts[1] = implode('.', $domain_parts);

            return implode('@', $mail_parts);
        });
    }

    protected function setupModel(): void
    {
        // As these are concerned with application correctness,
        // leave them enabled all the time.
        // Model::preventAccessingMissingAttributes();
        // Model::preventSilentlyDiscardingAttributes();

        // Since this is a performance concern only, don’t halt
        // production for violations.
        Model::preventLazyLoading(! $this->app->isProduction());
    }

    protected function setupMorph(): void
    {
        Relation::enforceMorphMap([
            //
        ]);
    }

    protected function setupLog(): void
    {
        if ($this->app->isProduction()) {
            return;
        }

        // Log a warning if we spend more than 1000ms on a single query.
        DB::listen(function ($query) {
            if ($query->time > 1000) {
                Log::warning('An individual database query exceeded 1 second.', [
                    'sql' => $query->sql,
                ]);
            }
        });

        if ($this->app->runningInConsole()) {
            // Log slow commands.
            $this->app[ConsoleKernel::class]->whenCommandLifecycleIsLongerThan(
                5000,
                function ($startedAt, $input, $status) {
                    Log::warning('A command took longer than 5 seconds.');
                }
            );
        } else {
            // Log slow requests.
            $this->app[HttpKernel::class]->whenRequestLifecycleIsLongerThan(
                5000,
                function ($startedAt, $request, $response) {
                    Log::warning('A request took longer than 5 seconds.');
                }
            );
        }
    }

    protected function setupStorage(): void
    {
        // Create a temporary URL for a file in the local storage disk.
        Storage::disk('local')->buildTemporaryUrlsUsing(function ($path, $expiration, $options) {
            return URL::temporarySignedRoute(
                'local.temp',
                $expiration,
                array_merge($options, ['path' => $path])
            );
        });
    }

    protected function detectConference(): void
    {
        if ($this->app->runningInConsole() || ! $this->app->isInstalled()) {
            return;
        }
        $this->app->scopeCurrentConference();

        $pathInfos = explode('/', request()->getPathInfo());
        $conferencePath = $pathInfos[1] ?? null;

        $isOnScheduledPath = isset($pathInfos[2]) && $pathInfos[2] == 'scheduled' && isset($pathInfos[3]) && ! blank($pathInfos[3]);
        $scheduledConferencePath = $pathInfos[3] ?? null;

        // Detect conference from URL path
        if ($conferencePath) {

            $conference = Conference::query()
                ->with(['media', 'meta'])
                ->where('path', $pathInfos[1])->first();

            $conference ? $this->app->setCurrentConferenceId($conference->getKey()) : $this->app->setCurrentConferenceId(Application::CONTEXT_WEBSITE);

            if (! $conference && $isOnScheduledPath) {
                abort(404);
            }

            // Detect scheduledConference from URL path when conference is set
            if ($conference && $isOnScheduledPath) {
                if ($scheduledConference = ScheduledConference::where('path', $scheduledConferencePath)->first()) {
                    $this->app->setCurrentScheduledConferenceId($scheduledConference->getKey());
                    $this->app->scopeCurrentScheduledConference();
                }
            }
        }

        // Scope livewire update path to current conference
        $currentConference = $this->app->getCurrentConference();
        if ($currentConference) {
            // Scope livewire update path to current serie
            $currentScheduledConference = $this->app->getCurrentScheduledConference();
            if ($isOnScheduledPath && $currentScheduledConference && $currentScheduledConference->path === $scheduledConferencePath) {
                Livewire::setUpdateRoute(
                    fn ($handle) => Route::post($currentConference->path.'/scheduled/'.$currentScheduledConference->path.'/livewire/update', $handle)->middleware('web')
                );
            } else {
                Livewire::setUpdateRoute(fn ($handle) => Route::post($currentConference->path.'/livewire/update', $handle)->middleware('web'));
            }
        }
    }
}
