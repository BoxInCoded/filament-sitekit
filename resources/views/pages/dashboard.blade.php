<x-filament-panels::page>
    @include('filament-sitekit::components.upgrade-banner')

    @php
        $metricLabel = match($this->chartMetric) {
            'sessions' => 'Sessions',
            'pageviews' => 'Pageviews',
            'clicks' => 'Clicks',
            'impressions' => 'Impressions',
            default => 'Users',
        };
    @endphp

    <x-filament::section class="mb-4">
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <div>
                <span class="font-medium">Current period:</span>
                {{ $this->period === '90d' ? 'Last 90 days' : ($this->period === '28d' ? 'Last 28 days' : 'Last 7 days') }}
            </div>
            <div>
                <span class="font-medium">Chart metric:</span> {{ $metricLabel }}
            </div>
        </div>
    </x-filament::section>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        @foreach ($this->keyMetrics as $metric)
            @php
                $accentClass = match($metric['label']) {
                    'Users' => 'text-primary-600',
                    'Sessions' => 'text-info-600',
                    'Pageviews' => 'text-success-600',
                    'Clicks' => 'text-warning-600',
                    'Impressions' => 'text-danger-600',
                    default => 'text-primary-600',
                };
            @endphp

            <x-filament::section>
                <x-slot name="heading">
                    <span class="{{ $accentClass }}">{{ $metric['label'] }}</span>
                </x-slot>
                <div class="text-2xl font-semibold">{{ number_format((float) $metric['value']) }}</div>

                @if (!empty($metric['sparkline']))
                    @php
                        $sparkValues = collect($metric['sparkline'])->map(fn($v) => (float) $v)->values();
                        $max = max($sparkValues->max() ?? 1, 1);
                        $count = max($sparkValues->count() - 1, 1);
                        $points = $sparkValues->map(function ($value, $index) use ($max, $count) {
                            $x = ($index / $count) * 100;
                            $y = 22 - (($value / $max) * 22);
                            return number_format((float) $x, 2, '.', '') . ',' . number_format((float) $y, 2, '.', '');
                        })->implode(' ');
                    @endphp

                    <div class="mt-2">
                        <svg viewBox="0 0 100 24" class="w-full h-8">
                            <polyline
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                class="{{ $accentClass }}"
                                points="{{ $points }}"
                            />
                        </svg>
                    </div>
                @endif

                <div class="text-xs text-gray-500 mt-1">
                    @if ($metric['delta'] !== null)
                        <span class="inline-flex items-center gap-1 {{ $metric['delta'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                            <span>{{ $metric['delta'] >= 0 ? '↑' : '↓' }}</span>
                            <span>{{ $metric['delta'] > 0 ? '+' : '' }}{{ number_format($metric['delta'], 2) }}%</span>
                        </span>
                        <span class="text-gray-500">vs previous period</span>
                    @else
                        Compared with previous period will appear when enough history exists.
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mt-6">
        <x-filament::section>
            <x-slot name="heading">Traffic acquisition</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2">Source / Medium</th>
                            <th class="py-2">Sessions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->trafficAcquisition as $row)
                            <tr class="border-b">
                                <td class="py-2">{{ $row['source'] }}</td>
                                <td class="py-2">{{ number_format($row['sessions']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-3" colspan="2">
                                    <div class="rounded-lg border border-dashed p-4 text-center space-y-2">
                                        <p class="text-sm text-gray-500">No acquisition data yet.</p>
                                        <div class="flex items-center justify-center gap-2">
                                            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSetupWizard::getUrl() }}" size="xs" color="gray" icon="heroicon-o-sparkles">
                                                Run setup
                                            </x-filament::button>
                                            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitModules::getUrl() }}" size="xs" icon="heroicon-o-squares-2x2">
                                                Check modules
                                            </x-filament::button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <a href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitModules::getUrl() }}" class="text-primary-600 text-sm mt-3 inline-block">
                View module details
            </a>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Top content</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2">Page</th>
                            <th class="py-2">Views</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->topContent as $row)
                            <tr class="border-b">
                                <td class="py-2">{{ $row['page'] }}</td>
                                <td class="py-2">{{ number_format((float) $row['views']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-3" colspan="2">
                                    <div class="rounded-lg border border-dashed p-4 text-center space-y-2">
                                        <p class="text-sm text-gray-500">No top content data yet.</p>
                                        <div class="flex items-center justify-center gap-2">
                                            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSettings::getUrl() }}" size="xs" icon="heroicon-o-cog-6-tooth">
                                                Configure GA4
                                            </x-filament::button>
                                            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitDiagnostics::getUrl() }}" size="xs" color="gray" icon="heroicon-o-wrench-screwdriver">
                                                Open diagnostics
                                            </x-filament::button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <a href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSettings::getUrl() }}" class="text-primary-600 text-sm mt-3 inline-block">
                Configure content tracking
            </a>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Search traffic</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2">Query</th>
                            <th class="py-2">Clicks</th>
                            <th class="py-2">Impressions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->searchTraffic as $row)
                            <tr class="border-b">
                                <td class="py-2">{{ $row['label'] }}</td>
                                <td class="py-2">{{ number_format($row['clicks']) }}</td>
                                <td class="py-2">{{ number_format($row['impressions']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-3" colspan="3">
                                    <div class="rounded-lg border border-dashed p-4 text-center space-y-2">
                                        <p class="text-sm text-gray-500">No search traffic data yet.</p>
                                        <div class="flex items-center justify-center gap-2">
                                            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSettings::getUrl() }}" size="xs" icon="heroicon-o-cog-6-tooth">
                                                Configure Search Console
                                            </x-filament::button>
                                            <x-filament::button tag="a" href="{{ route('filament-sitekit.google.connect') }}" size="xs" color="gray" icon="heroicon-o-link">
                                                Reconnect Google
                                            </x-filament::button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <a href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitDiagnostics::getUrl() }}" class="text-primary-600 text-sm mt-3 inline-block">
                Open diagnostics
            </a>
        </x-filament::section>
    </div>
</x-filament-panels::page>
