<?php

namespace App;

use App\Models\Site;
use App\Models\Topic;
use App\Models\Track;
use App\Models\Session;
use App\Models\Version;
use App\Models\Timeline;
use App\Models\Committee;
use App\Models\AuthorRole;
use App\Models\Conference;
use App\Models\Proceeding;
use App\Models\StaticPage;
use App\Models\Submission;
use App\Models\SpeakerRole;
use App\Models\Stakeholder;
use App\Models\Announcement;
use App\Models\MailTemplate;
use App\Models\Registration;
use App\Models\CommitteeRole;
use App\Models\NavigationMenu;
use App\Models\RegistrationType;
use App\Models\StakeholderLevel;
use App\Models\SubmissionFileType;
use Illuminate\Support\Collection;
use App\Models\RegistrationPayment;
use App\Models\ScheduledConference;
use App\Actions\Site\SiteCreateAction;
use App\Models\PaymentManual;
use App\Models\RegistrationAttendance;
use App\Models\Scopes\ConferenceScope;
use App\Models\Scopes\ScheduledConferenceScope;
use Illuminate\Foundation\Application as LaravelApplication;

class Application extends LaravelApplication
{
    public const APP_VERSION = '1.0.4';

    public const PHP_MIN_VERSION = '8.1';

    public const CONTEXT_WEBSITE = 0;

    protected ?int $currentConferenceId = null;

    protected ?Site $site = null;

    protected ?Conference $currentConference = null;

    protected string $currentConferencePath;

    protected ?int $currentScheduledConferenceId = null;

    protected ?ScheduledConference $currentScheduledConference = null;

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
        
        if($this->currentConference && $this->currentConference->getKey() !== $this->getCurrentConferenceId()){
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

    public function getCurrentScheduledConferenceId(): ?int
    {
        return $this->currentScheduledConferenceId;
    }

    public function setCurrentScheduledConferenceId(int $scheduledConferenceId)
    {
        $this->currentScheduledConferenceId = $scheduledConferenceId;
    }

    public function getCurrentScheduledConference(): ?ScheduledConference
    {
        if ($this->currentScheduledConferenceId && !$this->currentScheduledConference) {
            $this->currentScheduledConference = ScheduledConference::find($this->getCurrentScheduledConferenceId());
        }

        if($this->currentScheduledConference && $this->currentScheduledConference->getKey() !== $this->getCurrentScheduledConferenceId()){
            $this->currentScheduledConference = ScheduledConference::find($this->getCurrentScheduledConferenceId());
        }

        return $this->currentScheduledConference;
    }

    public function scopeCurrentConference(): void
    {
        $models = [
            Submission::class,
            NavigationMenu::class,
            AuthorRole::class,
            Topic::class,
            StaticPage::class,
            ScheduledConference::class,
            Proceeding::class,
            MailTemplate::class,
            Stakeholder::class,
            StakeholderLevel::class,
        ];

        foreach ($models as $model) {
            $model::addGlobalScope(new ConferenceScope);
        }
    }

    public function scopeCurrentScheduledConference(): void
    {
        $models = [
            Timeline::class,
            CommitteeRole::class,
            SpeakerRole::class,
            StaticPage::class,
            Committee::class,
            Announcement::class,
            Topic::class,
            NavigationMenu::class,
            SubmissionFileType::class,
            Track::class,
            Submission::class,
            Stakeholder::class,
            StakeholderLevel::class,
            RegistrationType::class,
            Registration::class,
            RegistrationAttendance::class,
            RegistrationPayment::class,
            PaymentManual::class,
            Session::class,
        ];

        foreach ($models as $model) {
            $model::addGlobalScope(new ScheduledConferenceScope);
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
            if ($this->isProduction() && !$this->hasDebugModeEnabled() && config('app.report_errors')) {
                return true;
            }
        } catch (\Throwable $th) {
            //
        }

        return false;
    }

    public function getLoginUrl(): string
    {
        if(app()->getCurrentScheduledConference()){
            return route('livewirePageGroup.scheduledConference.pages.login');
        }

        if(app()->getCurrentConference()){
            return route('livewirePageGroup.conference.pages.login');
        }

        return route('livewirePageGroup.website.pages.login');
    }

    public function isUpgrading(): bool
    {
        try {
            return version_compare($this->getInstalledVersion(), $this->getCodeVersion(), '<');
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function getUniqueIdentifier(): string
    {
        $site = $this->getSite();
        $uniqueId = $site->getMeta('unique_identifier');

        if (!$uniqueId) {
            $uniqueId = uniqid();
            $site->setMeta('unique_identifier', $uniqueId);
        }

        return $uniqueId;
    }
}
