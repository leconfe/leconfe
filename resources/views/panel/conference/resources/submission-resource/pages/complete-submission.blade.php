<x-filament::page>
    <div class="mx-auto max-w-xl w-full space-y-6">
        <h1 class="font-bold text-2xl text-center">{{ __('translation.submissions.completeSubsmissionBladeComplete') }}</h1>
        <x-filament::card>
          <p class="text-center">{{ __('translation.submissions.completeSubsmissionBladeText') }}</p>
          <br/>
          <p class="text-center">
            {{ __('translation.submissions.completeSubsmissionBladeGoTo') }} <a href="{{ App\Panel\Conference\Resources\SubmissionResource::getUrl('index') }}" class="text-primary-700">{{ __('translation.submissions.completeSubsmissionBladeSubmissionsPage') }}</a> 
            {{ __('translation.submissions.completeSubsmissionBladeCheck') }}
          </p>
        </x-filament::card>
    </div>
</x-filament::page>
