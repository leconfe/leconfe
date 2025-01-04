<x-website::layouts.main>
    <div class="mb-6">
        <x-website::breadcrumbs :breadcrumbs="$this->getBreadcrumbs()" />
    </div>
    <div class="relative space-y-8">
        @if ($mailing_address)
            <div id="mailing-address">
                {{ $mailing_address }}
            </div>
        @endif
        <div class="grid sm:grid-cols-2 justify-items-start gap-y-8">
            <div id="chair-contact" class="space-y-2">
                <h2 class="font-bold">{{ __('general.principal_contact') }}</h2>
                <div class="text-sm">
                    <p>{{ $principal_contact_name }}</p>
                    @if ($principal_contact_affiliation)
                        <p>{{ $principal_contact_affiliation }}</p>
                    @endif
                </div>
                @if ($principal_contact_phone)
                    <div class="text-sm">
                        <p class="font-bold">{{ __('general.phone') }}</p>
                        <p>
                            {{ $principal_contact_phone }}
                        </p>
                    </div>
                @endif
                @if ($principal_contact_email)
                    <div class="text-sm">
                        <p class="font-bold">{{ __('general.email') }}</p>
                        <p>
                            {{ $principal_contact_email }}
                        </p>
                    </div>
                @endif
            </div>
            <div id="support-contact" class="space-y-2">
                <h2 class="font-bold">{{ __('general.technical_support_contact') }}</h2>
                <div class="text-sm">
                    <p>{{ $support_contact_name }}</p>
                </div>
                @if ($support_contact_phone)
                    <div class="text-sm">
                        <p class="font-bold">{{ __('general.phone') }}</p>
                        <p>
                            {{ $support_contact_phone }}
                        </p>
                    </div>
                @endif
                @if ($support_contact_email)
                    <div class="text-sm">
                        <p class="font-bold">{{ __('general.email') }}</p>
                        <p>
                            {{ $support_contact_email }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-website::layouts.main>
