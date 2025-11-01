<div class="h-screen w-full max-w-4xl mx-auto flex flex-col" x-cloak>
    <div class="space-y-4 pt-16 pb-20 px-1 sm:px-4">
        <div class="avatar w-full">
            <div class="w-24 rounded-full mx-auto">
                <img src="{{ asset('logo.png') }}" />
            </div>
        </div>
        <p class="font-semibold ">Leconfe v{{ app()->getCodeVersion() }}</p>
        <form wire:submit='install'>
            <div class="card bg-white text-sm">
                <div class="card-body space-y-6 p-4 sm:p-8">
                    @error('install')
                        <div class="alert alert-error">
                            <x-heroicon-o-exclamation-circle class="stroke-current shrink-0 h-6 w-6" />
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <div class="introduction">
                        Thank you for downloading the <b>Leconfe</b>, a project by <a class="link link-primary link-hover"
                            target="_blank" href="https://openjournaltheme.com">Open Journal Theme</a>
                    </div>
                    <div class="system-requirement space-y-2">
                        <h2 class="text-lg not-italic font-semibold leading-7 text-black">System Requirements</h2>
                        <p class="w-full text-sm not-italic leading-snug ">Leconfe has a few server
                            requirements. Make sure that your server has the required php version and other requirement
                            mentioned below.</p>
                        <ul class="max-w-2xl text-sm bg-white border border-gray-200 rounded-lg">
                            <li
                                class="w-full px-4 py-2 border-b border-gray-200 rounded-t-lg flex justify-between flex-wrap">
                                <div>
                                    <a class="link link-primary link-hover" target="_blank"
                                        href="https://php.net">PHP</a>
                                    >= {{ app()->getPhpMinVersion() }}
                                </div>
                                <div class="flex gap-2">
                                    <span>Current Version : <b>{{ PHP_VERSION }}</b></span>
                                </div>
                            </li>
                            <li class="w-full px-4 py-2 border-b border-gray-200 rounded-t-lg flex justify-between flex-wrap">
                                <div>
                                    <a class="link link-primary link-hover" target="_blank"
                                        href="https://en.wikipedia.org/wiki/MySQL#Release_history">MySQL</a> >= 5.7
                                    or
                                    <a class="link link-primary link-hover" target="_blank"
                                        href="https://www.postgresql.org/support/versioning/">PostgreSQL</a> >= 11.0
                                </div>
                            </li>
                            <li class="w-full px-4 py-2 border-gray-200 rounded-t-lg flex justify-between flex-wrap">
                                <div>
                                    <a class="link link-primary link-hover" target="_blank"
                                        href="https://www.sqlite.org/changes.html">SQLite</a> >= 3.26.0
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="folder-permission">
                        <div class="mt-2.5 mb-6 space-y-2">
                            <h2 class="text-lg not-italic font-semibold leading-7 text-black">Permissions</h2>
                            <p class="w-full text-sm not-italic leading-snug ">
                                Below is the list of folder permissions which are required in order for the app to work.
                                If
                                the permission check fails, make sure to update your folder permissions.
                            </p>
                        </div>
                        @if (!empty($this->folders))
                            <ul class="max-w-2xl text-sm bg-white border border-gray-200 rounded-lg">
                                @foreach ($this->folders as $folder => $status)
                                    <li @class([
                                        'w-full px-4 py-2 border-gray-200 flex justify-between flex-wrap',
                                        'rounded-t-lg' => $loop->first,
                                        'rounded-b-lg' => $loop->last,
                                        'border-b' => !$loop->last,
                                    ])>
                                        <div>
                                            {{ $folder }}
                                        </div>
                                        <div>
                                            @if ($status)
                                                <x-heroicon-s-check-circle class="text-primary w-5 h-5" />
                                            @else
                                                <x-heroicon-s-x-circle class="text-red-500 w-5 h-5" />
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="account">
                        <div class="mt-2.5 mb-6 space-y-2">
                            <h2 class="text-lg not-italic font-semibold leading-7 text-black">Account Information</h2>
                            <p class="w-full text-sm not-italic leading-snug">
                                This user account will become your admin account and have complete access to the system.
                                Also you can change the details anytime after logging in.
                            </p>
                        </div>
                        <div class="space-y-4">
                            <div class="grid sm:grid-cols-6 gap-4">
                                <div class="form-control sm:col-span-3 gap-2">
                                    <label class="label-text">
                                        Given Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" class="input input-bordered input-sm"
                                        wire:model="form.given_name" required />
                                    @error('form.given_name')
                                        <div class="text-red-600 text-sm">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                                <div class="form-control sm:col-span-3 gap-2">
                                    <label class="label-text">
                                        Family Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" class="input input-bordered input-sm"
                                        wire:model="form.family_name" required />
                                    @error('form.family_name')
                                        <div class="text-red-600 text-sm">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                                <div class="form-control sm:col-span-6 gap-2">
                                    <label class="label-text">
                                        Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" class="input input-bordered input-sm"
                                        wire:model="form.email" />
                                    @error('form.email')
                                        <div class="text-red-600 text-sm">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                                <div class="form-control sm:col-span-3 gap-2">
                                    <label class="label-text">
                                        Password <span class="text-red-500">*</span>
                                    </label>
                                    <input type="password" class="input input-bordered input-sm"
                                        wire:model="form.password" required />
                                    @error('form.password')
                                        <div class="text-red-600 text-sm">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                                <div class="form-control sm:col-span-3 gap-2">
                                    <label class="label-text">
                                        Password Confirmation <span class="text-red-500">*</span>
                                    </label>
                                    <input type="password" class="input input-bordered input-sm"
                                        wire:model="form.password_confirmation" required />
                                    @error('form.password_confirmation')
                                        <div class="text-red-600 text-sm">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                                <div class="form-control sm:col-span-3 gap-2">
                                    <div class="flex items-center">
                                        <input type="checkbox" value="1" wire:model='form.newsletter' class="checkbox checkbox-sm">
                                        <div class="ms-2">
                                            <label for="checked-checkbox" class="text-sm font-medium text-gray-900">Subscribe to newsletter</label>
                                            <p class="text-gray-700"> 
                                                Receive updates, tips, and important announcements
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="database space-y-4">
                        <div class="mt-2.5 mb-6 space-y-2">
                            <h2 class="text-lg not-italic font-semibold leading-7 text-black">Database</h2>
                            <p class="w-full text-sm not-italic leading-snug ">
                                Leconfe needs to access a SQL database to save its information. You can find the list of
                                supported databases in the system requirements mentioned above. In the spaces provided
                                below, please input the necessary settings for establishing a connection to the
                                database.
                            </p>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="form-control gap-2">
                                <label class="label-text">
                                    Database Connection <span class="text-red-500">*</span>
                                </label>
                                <select class="select select-sm select-bordered" wire:model="form.db_connection"
                                    required>
                                    <option value="mysql">MySQL</option>
                                    <option value="pgsql">PostgreSQL</option>
                                </select>
                                @error('form.db_connection')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-control gap-2">
                                <label class="label-text">
                                    Database Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" class="input input-bordered input-sm" wire:model="form.db_name"
                                    required />
                                @error('form.db_name')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-control gap-2">
                                <label class="label-text">
                                    Database Username <span class="text-red-500">*</span>
                                </label>
                                <input type="text" class="input input-bordered input-sm"
                                    wire:model="form.db_username" required />
                                @error('form.db_username')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-control gap-2">
                                <label class="label-text">
                                    Database Password
                                </label>
                                <input type="password" class="input input-bordered input-sm"
                                    wire:model="form.db_password"/>
                                @error('form.db_password')
                                    <div class="text-red-600 text-sm">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-4">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="form-control gap-2">
                                    <label class="label-text">
                                        Database Host <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" class="input input-bordered input-sm"
                                        wire:model="form.db_host" required />
                                    @error('form.db_host')
                                        <div class="text-red-600 text-sm">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>
                            <div class="flex w-full gap-4 justify-between">
                                <div class="flex items-center gap-2">
                                    @if (session('testConnection'))
                                        <x-heroicon-o-check class="stroke-current shrink-0 h-6 w-6 text-green-500" />
                                        <span class="text-green-500">Successfully Connected</span>
                                    @endif
                                </div>
                                <div>
                                    <button type="submit" class="text-primary text-sm"
                                        wire:click.prevent="testConnection">
                                        Test Connection</button>
                                </div>
                            </div>
                        </div>
                        @error('form.error')
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-x-mark class="stroke-current shrink-0 h-6 w-6 text-red-500" />
                                <span class="text-red-500">{{ $message }}</span>
                            </div>
                        @enderror
                    </div>
                    <div class="survey space-y-6">
                        <div class="mt-2.5 mb-6 space-y-2">
                            <h2 class="text-lg not-italic font-semibold leading-7 text-black">Survey</h2>
                            <p class="w-full text-sm not-italic leading-snug ">
                                Please take a moment to answer the following question.
                            </p>
                        </div>
                        <div class="form-control sm:col-span-3 gap-2">
                            <label class="label-text">
                                How did you hear about our platform? <span class="text-red-500">*</span>
                            </label>
                            <div>
                                <label class="label cursor-pointer justify-normal gap-2">
                                    <input type="radio" name="referral_source" class="radio radio-xs" value="search_engine" wire:model="form.referral_source" />
                                    <span class="label-text">Search engine</span>
                                </label>
                                <label class="label cursor-pointer justify-normal gap-2">
                                    <input type="radio" name="referral_source" class="radio radio-xs" value="social_media" wire:model="form.referral_source" />
                                    <span class="label-text">Social Media</span>
                                </label>
                                <label class="label cursor-pointer justify-normal gap-2">
                                    <input type="radio" name="referral_source" class="radio radio-xs" value="friend_referral" wire:model="form.referral_source" />
                                    <span class="label-text">Referral from a friend</span>
                                </label>
                                <label class="label cursor-pointer justify-normal gap-2">
                                    <input type="radio" name="referral_source" class="radio radio-xs" value="other" wire:model="form.referral_source" />
                                    <span class="label-text">Other</span>
                                    <input class="input input-xs input-bordered" wire:model="form.other_referral_source" />
                                </label>
                            </div>
                            @error('form.referral_source')
                                <div class="text-red-600 text-sm">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="form-control sm:col-span-3 gap-2">
                            <label class="label-text">
                                What features are most important to you? (Select all that apply)
                            </label>
                            <div>
                                <label class="label cursor-pointer justify-normal gap-2">
                                    <input type="checkbox" class="checkbox checkbox-xs" value="custom_conference_website" wire:model="form.important_features"/>
                                    <span class="label-text">Custom conference website</span>
                                </label>
                                <label class="label cursor-pointer justify-normal gap-2">
                                    <input type="checkbox" class="checkbox checkbox-xs" value="participant_registration" wire:model="form.important_features"/>
                                    <span class="label-text">Participant Registration Management</span>
                                </label>
                                <label class="label cursor-pointer justify-normal gap-2">
                                    <input type="checkbox" class="checkbox checkbox-xs" value="paper_submission" wire:model="form.important_features"/>
                                    <span class="label-text">Paper Submission Workflow</span>
                                </label>
                                <label class="label cursor-pointer justify-normal gap-2">
                                    <input type="checkbox" class="checkbox checkbox-xs" value="proceedings_publication" wire:model="form.important_features"/>
                                    <span class="label-text">Proceedings Publication</span>
                                </label>
                                <label class="label cursor-pointer justify-normal gap-2">
                                    <input type="checkbox" class="checkbox checkbox-xs" value="other" wire:model="form.important_features"/>
                                    <span class="label-text">Other : </span>
                                    <input class="input input-xs input-bordered" wire:model="form.other_important_feature" />
                                </label>
                            </div>
                            @error('form.important_features')
                                <div class="text-red-600 text-sm">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-between mt-16">
                        <button class="btn btn-primary btn-outline btn-sm ml-auto" wire:loading.attr="disabled"
                            type="submit">
                            <span class="loading loading-spinner loading-xs" wire:loading wire:target='install'></span>
                            Install Leconfe
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
