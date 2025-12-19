<?php

namespace App\Models;

use App\Models\Enums\UserRole;
use GeneaLabs\LaravelModelCaching\Traits\Cachable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubmissionParticipant extends Model
{
    use Cachable, HasFactory;

    protected $table = 'submission_has_participants';

    protected $fillable = [
        'submission_id',
        'user_id',
        'role_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class)->withoutGlobalScopes();
    }

    public function scopeEditor(Builder $builder)
    {
        $editorIds = Role::where('name', [UserRole::ScheduledConferenceEditor, UserRole::TrackEditor])->pluck('id')->toArray();

        return $builder->where('role_id', $editorIds);
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
}
