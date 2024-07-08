<?php

namespace App;

use App\Actions\Site\SiteCreateAction;
use App\Models\Announcement;
use App\Models\AuthorRole;
use App\Models\Block;
use App\Models\Committee;
use App\Models\CommitteeRole;
use App\Models\Conference;
use App\Models\MailTemplate;
use App\Models\Sponsor;
use App\Models\NavigationMenu;
use App\Models\PaymentItem;
use App\Models\Proceeding;
use App\Models\Scopes\ConferenceScope;
use App\Models\Scopes\SerieScope;
use App\Models\Serie;
use App\Models\Setting;
use App\Models\Site;
use App\Models\SpeakerRole;
use App\Models\StaticPage;
use App\Models\Submission;
use App\Models\Timeline;
use App\Models\Topic;
use App\Models\Venue;
use App\Models\Version;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\Collection;

class Application extends LaravelApplication
{
    public const APP_VERSION = '1.0.0-alpha.1';

    public const PHP_MIN_VERSION = '8.1';

    public const CONTEXT_WEBSITE = 0;

    protected ?int $currentConferenceId = null;

    protected ?Site $site = null;

    protected ?Conference $currentConference = null;

    protected string $currentConferencePath;

    protected ?int $currentSerieId = null;

    protected ?Serie $currentSerie = null;

    public function isInstalled()
    {
        return config('app.installed');
    }

    public function getCodeVersion(): string
    {
        return static::APP_VERSION;
    }

    public function getVersion()
    {
        $version = new Version();
        $version->product_name = 'Leconfe';
        $version->product_folder = 'leconfe';
        $version->version = static::APP_VERSION;

        return $version;
    }

    public function getInstalledVersion(): string
    {
        if(!$this->isInstalled()){
            return throw new \Exception('Application is not installed');
        }

        return Version::application()?->version;
    }

    public function getPhpMinVersion()
    {
        return static::PHP_MIN_VERSION;
    }

    public function getCurrentConference(): ?Conference
    {
        if ($this->currentConferenceId && !$this->currentConference) {
            $this->currentConference = Conference::find($this->getCurrentConferenceId());
        }

        return $this->currentConference;
    }

    public function getCurrentConferenceId(): int
    {
        return $this->currentConferenceId ?? static::CONTEXT_WEBSITE;
    }

    public function setCurrentConferenceId(int $conferenceId)
    {
        $this->currentConferenceId = $conferenceId;
    }

    public function getCurrentSerieId(): ?int
    {
        return $this->currentSerieId;
    }

    public function setCurrentSerieId(int $serieId)
    {
        $this->currentSerieId = $serieId;
    }

    public function getCurrentSerie(): ?Serie
    {
        if ($this->currentSerieId && !$this->currentSerie) {
            $this->currentSerie = Serie::find($this->getCurrentSerieId());
        }

        return $this->currentSerie;
    }

    public function scopeCurrentConference(): void
    {
        $models = [
            Submission::class,
            Topic::class,
            NavigationMenu::class,
            AuthorRole::class,
            StaticPage::class,
            PaymentItem::class,
            Serie::class,
            Proceeding::class,
            MailTemplate::class,
        ];

        foreach ($models as $model) {
            $model::addGlobalScope(new ConferenceScope);
        }
    }

    public function scopeCurrentSerie(): void
    {
        $models = [
            Venue::class,
            Timeline::class,
            CommitteeRole::class,
            SpeakerRole::class,
            StaticPage::class,
            Sponsor::class,
            Committee::class,
            Announcement::class,
        ];

        foreach ($models as $model) {
            $model::addGlobalScope(new SerieScope);
        }
    }

    public function getNavigationItems(string $handle): Collection
    {
        return NavigationMenu::query()
            ->where('handle', $handle)
            ->with([
                'items' => function ($query) {
                    $query
                        ->ordered()
                        ->whereNull('parent_id')
                        ->with('children', function ($query) {
                            $query->ordered();
                        });
                },
            ])
            ->first()?->items ?? collect();
    }

    public function getSite(): Site
    {
        if (!$this->site) {
            $this->site = Site::getSite() ?? SiteCreateAction::run();
        }

        return $this->site;
    }

    public function isReportingErrors(): bool
    {
        try {
            if ($this->isProduction() && !$this->hasDebugModeEnabled() && Setting::set('send_error_report', true)) {
                return true;
            }
        } catch (\Throwable $th) {
            //
        }

        return false;
    }
}
