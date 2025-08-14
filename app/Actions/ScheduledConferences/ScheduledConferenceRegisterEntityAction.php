<?php

namespace App\Actions\ScheduledConferences;

use App\Models\ScheduledConference;
use Lorisleiva\Actions\Concerns\AsAction;

class ScheduledConferenceRegisterEntityAction
{
  use AsAction;

  public function handle(ScheduledConference $scheduledConference)
  {
    $scheduledConference->registerEntity();
  }
}
