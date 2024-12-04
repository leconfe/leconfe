<x-filament::page>
    <div class="w-full max-w-xl mx-auto space-y-6">
        <h1 class="text-2xl font-bold text-center">{{ __('general.submission_complete') }}</h1>
        <x-filament::card>
          <p class="text-center">{{ __('general.submitted_submission') }}</p>
          <br/>
          <p class="text-center">
            {!! __('general.go_to_submission_page', ['url' => App\Panel\ScheduledConference\Resources\SubmissionResource::getUrl("index") ]) !!}
          </p>
        </x-filament::card>
    </div>
</x-filament::page>
