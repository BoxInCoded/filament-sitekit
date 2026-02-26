<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Plan</x-slot>
            <div class="text-sm">
                <span class="font-medium">Current plan:</span> {{ $this->currentPlan }}
            </div>
        </x-filament::section>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-filament::section>
                <x-slot name="heading">Connection</x-slot>
                <div class="space-y-1 text-sm">
                    <p><span class="font-medium">Status:</span> {{ $this->connectionStatus }}</p>
                    <p><span class="font-medium">Name:</span> {{ $this->connectedName ?? '—' }}</p>
                    <p><span class="font-medium">Email:</span> {{ $this->connectedEmail ?? '—' }}</p>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Analytics</x-slot>
                <p class="text-sm">Status: {{ $this->ga4Status }}</p>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Search Console</x-slot>
                <p class="text-sm">Status: {{ $this->gscStatus }}</p>
            </x-filament::section>
        </div>

        {{ $this->form }}

        <x-filament::section>
            <x-slot name="heading">Tracking</x-slot>

            <div class="space-y-4">
                <div class="flex items-center gap-3 text-sm">
                    <span class="font-medium">Status:</span>
                    @if ($this->trackingInstalled)
                        <span class="px-2 py-1 rounded bg-success-500/10 text-success-700 dark:text-success-300">Installed</span>
                    @else
                        <span class="px-2 py-1 rounded bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200">Not installed</span>
                    @endif
                </div>

                @if ($this->trackingInstalled)
                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        <p><span class="font-medium">Type:</span> {{ strtoupper($this->trackingType) }}</p>
                        <p>
                            <span class="font-medium">ID:</span>
                            {{ $this->trackingType === 'ga4' ? ($this->trackingMeasurementId ?: '—') : ($this->trackingContainerId ?: '—') }}
                        </p>
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">GA4 Measurement ID</label>
                        <input wire:model="trackingMeasurementId" type="text" class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm" placeholder="G-XXXXXXXXXX">
                        <x-filament::button size="sm" wire:click="installGa4Tracking">Install GA4</x-filament::button>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">GTM Container ID</label>
                        <input wire:model="trackingContainerId" type="text" class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm" placeholder="GTM-XXXXXXX">
                        <x-filament::button size="sm" wire:click="installGtmTracking">Install GTM</x-filament::button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::button
                        size="sm"
                        color="gray"
                        wire:click="previewAndVerify"
                        x-on:click="window.open(@js($this->resolvedWebsiteUrl), '_blank')"
                    >
                        Preview & Verify
                    </x-filament::button>

                    <x-filament::button size="sm" color="gray" wire:click="recheckTracking">Re-check</x-filament::button>

                    @if ($this->trackingEnabled)
                        <x-filament::button size="sm" color="gray" wire:click="setTrackingEnabled(false)">Disable tracking</x-filament::button>
                    @else
                        <x-filament::button size="sm" color="gray" wire:click="setTrackingEnabled(true)">Enable tracking</x-filament::button>
                    @endif

                    <x-filament::button size="sm" color="danger" wire:click="removeTracking">Remove tracking</x-filament::button>
                </div>

                <div class="text-xs text-gray-500 space-y-1">
                    <p>Open your website and verify tracking is present.</p>
                    <p>If not detected, try “Re-check” or use manual install (if needed).</p>
                    <p>Last checked: {{ $this->trackingLastVerifyAt ?? 'Never' }}</p>
                    <p>Status: {{ $this->trackingLastVerifyAt ? ($this->trackingLastDetected ? 'Tracking detected ✅' : 'Tracking not detected ⚠️') : 'Not checked yet' }}</p>
                    <p>Last result: {{ $this->trackingLastSummary }}</p>
                </div>

                @if ($this->trackingInstalled && $this->trackingLastVerifyAt && ! $this->trackingLastDetected && $this->manualSnippet !== '')
                    <details class="rounded-lg border p-4">
                        <summary class="cursor-pointer text-sm font-medium">Manual install (if needed)</summary>

                        <div class="mt-3 space-y-2">
                            @if ($this->manualHeadSnippet !== '')
                                <label class="text-xs font-medium">Head snippet</label>
                                <textarea id="sitekit-manual-head" readonly class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-xs" rows="6">{{ $this->manualHeadSnippet }}</textarea>
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    wire:click="notifySnippetCopied"
                                    x-on:click="navigator.clipboard.writeText(document.getElementById('sitekit-manual-head').value)"
                                >
                                    Copy
                                </x-filament::button>
                            @endif

                            @if ($this->manualBodySnippet !== '')
                                <label class="text-xs font-medium">Body noscript snippet</label>
                                <textarea id="sitekit-manual-body" readonly class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-xs" rows="4">{{ $this->manualBodySnippet }}</textarea>
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    wire:click="notifySnippetCopied"
                                    x-on:click="navigator.clipboard.writeText(document.getElementById('sitekit-manual-body').value)"
                                >
                                    Copy
                                </x-filament::button>
                            @endif
                        </div>
                    </details>
                @endif
            </div>
        </x-filament::section>

        @if ($this->multiAccountLocked)
            <x-filament::section>
                <x-slot name="heading">Multi-account</x-slot>

                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Multi-account management is available in Pro.</p>

                    <x-filament::button
                        tag="a"
                        href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitPlans::getUrl() }}"
                        size="sm"
                    >
                        View plans
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        <div class="flex items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Save Settings
            </x-filament::button>

            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSetupWizard::getUrl() }}" color="gray" icon="heroicon-o-sparkles">
                Go to setup wizard
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
