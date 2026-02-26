<x-filament-panels::page>
    @include('filament-sitekit::components.upgrade-banner')

    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">Tracking Verification</x-slot>

            <div class="space-y-3 text-sm">
                <p class="text-gray-600 dark:text-gray-300">Open your website and verify tracking is present.</p>

                @if ($this->trackingVerification !== [])
                    <div class="rounded-lg border p-3">
                        <p><span class="font-medium">Last check:</span> {{ $this->trackingVerificationCheckedAt ?? '—' }}</p>
                        <p><span class="font-medium">URL:</span> {{ $this->trackingVerification['url'] ?? '—' }}</p>
                        <p><span class="font-medium">Status:</span> {{ $this->trackingVerification['status_code'] ?? 'n/a' }}</p>
                        <p><span class="font-medium">Result:</span> {{ $this->trackingDetected() ? 'Tracking detected ✅' : 'Tracking not detected ⚠️' }}</p>
                        <p class="text-gray-600 dark:text-gray-300">{{ $this->trackingVerification['message'] ?? '' }}</p>
                    </div>

                    @if (!($this->trackingVerification['reachable'] ?? true))
                        <div class="rounded-lg border border-warning-300 bg-warning-50 dark:bg-warning-900/20 p-3 text-warning-800 dark:text-warning-200">
                            Website is unreachable. Check Website URL in Settings and try again.
                        </div>
                    @endif
                @else
                    <p class="text-gray-500">No verification result yet.</p>
                @endif

                <x-filament::button size="sm" wire:click="verifyTrackingNow">Verify now</x-filament::button>
            </div>
        </x-filament::section>

        @forelse ($this->groupedIssues as $module => $issues)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <span>{{ $module }}</span>
                        @if (!empty($this->groupStatuses[$module]))
                            <span class="text-xs px-2 py-1 rounded {{ $this->severityBadgeClass($this->groupStatuses[$module]) }}">
                                {{ strtoupper($this->groupStatuses[$module]) }}
                            </span>
                        @endif
                    </div>
                </x-slot>

                <div class="space-y-3">
                    @forelse ($issues as $issue)
                        <div class="rounded-lg border p-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-semibold">{{ $issue['title'] }}</h3>
                                <span class="text-xs px-2 py-1 rounded {{ $this->severityBadgeClass($issue['level']) }}">
                                    {{ strtoupper($issue['level']) }}
                                </span>
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $issue['description'] }}</p>

                            @if (!empty($issue['action_url']))
                                <a href="{{ $issue['action_url'] }}" class="text-primary-600 text-sm mt-2 inline-block">Recommended action</a>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-lg border p-4 text-sm text-gray-500">No checks for this group.</div>
                    @endforelse
                </div>
            </x-filament::section>
        @empty
            <div class="rounded-lg border border-success-500 p-4">
                <h3 class="font-semibold">All checks passed</h3>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">No issues detected for enabled connectors.</p>
            </div>
        @endforelse

        @if (app(\BoxinCode\FilamentSiteKit\SiteKitLicense::class)->isFree())
            <x-filament::section>
                <x-slot name="heading">Advanced diagnostics (Pro)</x-slot>

                <details class="rounded-lg border p-4">
                    <summary class="cursor-pointer text-sm font-medium">See what you unlock in Pro</summary>

                    <div class="mt-3 space-y-3">
                        <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                            @foreach ($this->proTeaserItems as $item)
                                <li>• {{ $item }}</li>
                            @endforeach
                        </ul>

                        <x-filament::button
                            tag="a"
                            href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitPlans::getUrl() }}"
                            size="sm"
                        >
                            View plans
                        </x-filament::button>
                    </div>
                </details>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
