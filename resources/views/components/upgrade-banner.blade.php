@php
    $upgradeUi = app(\BoxinCode\FilamentSiteKit\Support\UpgradeUi::class);
    $license = app(\BoxinCode\FilamentSiteKit\SiteKitLicense::class);
@endphp

@if ($license->isFree() && $upgradeUi->shouldShowUpgradeBanner())
    <x-filament::section class="mb-4">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-semibold">Unlock Pro features</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">Add Search Console, Alerts, Insights, Multi-accounts and more.</p>
            </div>

            <div class="flex items-center gap-2">
                <x-filament::button
                    tag="a"
                    href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitPlans::getUrl() }}"
                    size="sm"
                >
                    View plans
                </x-filament::button>

                <form method="POST" action="{{ route('filament-sitekit.upgrade-banner.dismiss') }}">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
                    <x-filament::button type="submit" size="sm" color="gray">
                        Dismiss
                    </x-filament::button>
                </form>
            </div>
        </div>
    </x-filament::section>
@endif
