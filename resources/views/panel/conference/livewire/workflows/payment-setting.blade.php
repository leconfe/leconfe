<div class="space-y-6">
    <div class="flex items-center">
        <div class="flex space-x-3 justify-center items-center">
            <h3 class="text-xl font-semibold leading-6 text-gray-950 dark:text-white">
                {{ __('translation.workflowPaymentSetting.workflowPaymentSettingHeading') }}
            </h3>
        </div>
    </div>
    <form class="space-y-4">
        {{ $this->form }}
        {{ $this->submitAction() }}
    </form>
</div>
