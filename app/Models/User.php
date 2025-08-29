<?php

namespace App\Models;

use App\Mail\Templates\VerifyUserEmail;
use App\Models\Concerns\LocalizedMetable;
use App\Models\Enums\RegistrationPaymentState;
use App\Models\Enums\UserRole;
use Exception;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Mchev\Banhammer\Traits\Bannable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;
use Squire\Models\Country;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasMedia, HasName, MustVerifyEmail
{
    use Bannable,
        HasApiTokens,
        HasFactory,
        HasRoles,
        InteractsWithMedia,
        LocalizedMetable,
        Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'given_name',
        'family_name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            if ($user->submissions->count() > 0) {
                throw new Exception('User that has submission cannot be deleted.');
            }
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($publicName = $this->getLocalizedMeta('public_name')) {
                    return $publicName;
                }

                return Str::squish($this->given_name.' '.$this->family_name);
            },
        );
    }

    public function getFilamentName(): string
    {
        return $this->full_name;
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Replaced From original DatabaseNotification laravel
     * Get the entity's notifications.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function notifications()
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    public function canImpersonate()
    {
        return $this->can('User:loginAs');
    }

    public function canBeImpersonated()
    {
        if ($this->isBanned()) {
            return false;
        }

        if ($this->hasAnyRole([UserRole::Admin->value])) {
            return false;
        }

        return true;
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function registration(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function isRegisteredAsAuthor(): bool
    {
        $userRegistration = Registration::select('*')
            ->where('user_id', $this->id)
            ->first();

        if (! $userRegistration) {
            return false;
        }

        if (! ($userRegistration->registrationPayment)) {
            return false;
        }

        if ($userRegistration->registrationPayment->state !== RegistrationPaymentState::Paid->value) {
            return false;
        }

        if ($userRegistration->registrationPayment->level !== RegistrationType::LEVEL_AUTHOR) {
            return false;
        }

        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->hasMedia('profile')) {
            return $this->getFirstMediaUrl('profile', 'avatar');
        }

        $name = str($this->fullName)
            ->trim()
            ->explode(' ')
            ->map(fn (string $segment): string => filled($segment) ? mb_substr($segment, 0, 1) : '')
            ->join(' ');

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=FFFFFF&background=111827&font-size=0.33';
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('avatar')
            ->keepOriginalImageFormat()
            ->width(50);

        $this->addMediaConversion('thumb')
            ->keepOriginalImageFormat()
            ->width(400);

        $this->addMediaConversion('thumb-xl')
            ->keepOriginalImageFormat()
            ->width(800);
    }

    public function hasVerifiedEmail()
    {
        return ! is_null($this->email_verified_at);
    }

    public function authors()
    {
        return Author::email($this->email)->first();
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        Mail::to($this->getEmailForVerification())->send(new VerifyUserEmail($this));
    }

    /**
     * Assign the given role to the model.
     *
     * @param  string|int|array|Role|Collection|\BackedEnum  ...$roles
     * @return $this
     */
    public function assignRole(...$roles)
    {
        $roles = $this->collectRoles($roles);

        $model = $this->getModel();

        $conference = app()->getCurrentConference();
        $scheduledConference = app()->getCurrentScheduledConference();

        $teamPivot = [];

        if ($conference) {
            $teamPivot['conference_id'] = $conference->getKey();
        }

        if ($scheduledConference) {
            $teamPivot['scheduled_conference_id'] = $scheduledConference->getKey();
        }

        if ($model->exists) {
            $currentRoles = $this->roles->map(fn ($role) => $role->getKey())->toArray();

            $this->roles()->attach(array_diff($roles, $currentRoles), $teamPivot);
            $model->unsetRelation('roles');
        } else {
            $class = \get_class($model);

            $class::saved(
                function ($object) use ($roles, $model, $teamPivot) {
                    if ($model->getKey() != $object->getKey()) {
                        return;
                    }
                    $model->roles()->attach($roles, $teamPivot);
                    $model->unsetRelation('roles');
                }
            );
        }

        if (is_a($this, Permission::class)) {
            $this->forgetCachedPermissions();
        }

        return $this;
    }

    public function syncRoles(...$roles)
    {
        if ($this->getModel()->exists) {
            $this->roles()->detach($this->roles->filter(fn ($role) => $role->name != UserRole::Admin->value)->pluck('id')->toArray());
            $this->setRelation('roles', collect());
        }

        return $this->assignRole($roles);
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ($this->getWildcardClass()) {
            return $this->hasWildcardPermission($permission, $guardName);
        }

        $permission = $this->filterPermission($permission, $guardName);

        return $this->roleHasDefaultPermission($permission) || $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission);
    }

    public function roleHasDefaultPermission($permission)
    {
        return $this->roles->contains(
            function ($role) use ($permission) {
                return $role->hasDefaultPermission($permission);
            }
        );
    }

    public function isRegisteredAsParticipant(): bool
    {
        return Participant::query()
            ->where('email', $this->email)
            ->exists();
    }

    public function participant()
    {
        return $this->hasOne(Participant::class, 'email', 'email');
    }

    protected function getAllDefaultMeta(): array
    {
        return [
            'enable_new_announcement_email' => true
        ];
    }
}
