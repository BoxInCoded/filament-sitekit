<x-filament-panels::page>
    @php
        $progress = (int) round(($this->step / 5) * 100);
    @endphp

    @if ($this->googleFailed)
        <x-filament::section class="mb-4">
            <div class="text-sm text-danger-600">Google connection failed. Try again.</div>
        </x-filament::section>
    @endif

    <div class="mb-6 space-y-3">
        <div class="flex items-center gap-2 text-xs flex-wrap">
            <span class="px-2 py-1 rounded {{ $this->googleConnected ? 'bg-success-100 text-success-700' : 'bg-gray-100 dark:bg-gray-800' }}">1 Google</span>
            <span class="px-2 py-1 rounded {{ $this->analyticsConfigured ? 'bg-success-100 text-success-700' : 'bg-gray-100 dark:bg-gray-800' }}">2 Analytics</span>
            <span class="px-2 py-1 rounded {{ $this->searchConsoleConfigured ? 'bg-success-100 text-success-700' : 'bg-gray-100 dark:bg-gray-800' }}">3 Search Console</span>
            <span class="px-2 py-1 rounded {{ $this->trackingDetected ? 'bg-success-100 text-success-700' : 'bg-gray-100 dark:bg-gray-800' }}">4 Tracking</span>
            <span class="px-2 py-1 rounded {{ $this->googleConnected && $this->analyticsConfigured && $this->searchConsoleConfigured ? 'bg-success-100 text-success-700' : 'bg-gray-100 dark:bg-gray-800' }}">5 Finish</span>
        </div>

        <div>
            <div class="h-2 rounded bg-gray-100 dark:bg-gray-800 overflow-hidden">
                <div class="h-full bg-primary-600" style="width: {{ $progress }}%"></div>
            </div>
            <div class="text-xs text-gray-500 mt-1">{{ $progress }}% complete</div>
        </div>
    </div>

    @if ($this->step === 1)
        <x-filament::section>
            <x-slot name="heading">Set up SiteKit</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">Connect Google Analytics and Search Console in minutes.</p>
            <div class="flex gap-2">
                @if ($this->googleConnected)
                    <x-filament::button wire:click="startSetup" icon="heroicon-o-arrow-right-circle" size="lg">Continue Setup</x-filament::button>
                @else
                    <x-filament::button wire:click="startSetup" icon="heroicon-o-play" size="lg">Start Setup</x-filament::button>
                @endif
            </div>
        </x-filament::section>
    @elseif ($this->step === 2)
        <x-filament::section>
            <x-slot name="heading">Step 2: Analytics property</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">We couldn’t auto-detect your property. Select one below.</p>
            <div class="space-y-4">
                <select wire:model="ga4_property_id" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    <option value="">Select GA4 property</option>
                    @foreach ($this->ga4Options as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <x-filament::button wire:click="saveGa4">Save & Continue</x-filament::button>
                </div>

                <div class="pt-4 border-t space-y-3">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="installTrackingAutomatically"> Install tracking automatically</label>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Tracking type</label>
                            <select wire:model="trackingType" class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm">
                                <option value="ga4">GA4</option>
                                <option value="gtm">GTM</option>
                            </select>
                        </div>

                        @if ($trackingType === 'ga4')
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Measurement ID</label>
                                <input wire:model="trackingMeasurementId" type="text" class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm" placeholder="G-XXXXXXXXXX">
                                <p class="text-xs text-gray-500">If unavailable, you can install tracking later in Settings.</p>
                            </div>
                        @else
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Container ID</label>
                                <input wire:model="trackingContainerId" type="text" class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm" placeholder="GTM-XXXXXXX">
                                <p class="text-xs text-gray-500">If unavailable, you can install tracking later in Settings.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </x-filament::section>
    @elseif ($this->step === 3)
        <x-filament::section>
            <x-slot name="heading">Step 3: Search Console site</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">We couldn’t auto-detect your Search Console site. Select one below.</p>
            <div class="space-y-4">
                <select wire:model="gsc_site_url" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                    <option value="">Select Search Console site</option>
                    @foreach ($this->gscOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <x-filament::button wire:click="saveGsc">Save & Continue</x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Setup completed</x-slot>

            <div class="space-y-2 text-sm mb-4">
                <div class="flex items-center gap-2"><span class="text-success-600">✔</span> Google connected</div>
                <div class="flex items-center gap-2"><span class="text-success-600">✔</span> Analytics configured</div>
                <div class="flex items-center gap-2"><span class="text-success-600">✔</span> Search Console configured</div>
                @if ($this->trackingDetected)
                    <div class="flex items-center gap-2"><span class="text-success-600">✔</span> Tracking detected</div>
                @else
                    <div class="flex items-center gap-2"><span class="text-warning-600">•</span> Tracking missing</div>
                @endif

                @if ($installTrackingAutomatically)
                    <div class="flex items-center gap-2"><span class="text-success-600">✔</span> Tracking auto-install enabled</div>
                @endif
            </div>

            <div class="flex gap-2">
                <x-filament::button wire:click="finish" icon="heroicon-o-check-circle">Go to Dashboard</x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
