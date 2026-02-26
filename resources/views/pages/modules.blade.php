<x-filament-panels::page>
    @include('filament-sitekit::components.upgrade-banner')

    @php
        $currentAccount = app(\BoxinCode\FilamentSiteKit\SiteKitAccountManager::class)->current();
        $canConfigure = $currentAccount ? \Illuminate\Support\Facades\Gate::allows('configureConnectors', $currentAccount) : true;
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach ($this->modules as $module)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <span>{{ $module['label'] }}</span>
                        @if (! empty($module['locked']))
                            <span class="text-xs px-2 py-1 rounded bg-primary-500/10 text-primary-700 dark:text-primary-300">PRO</span>
                        @endif
                    </div>
                </x-slot>

                <div class="space-y-3">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ ! empty($module['locked']) ? 'Locked on your current plan. Upgrade to unlock this module.' : $module['description'] }}
                    </p>

                    <div>
                        @php
                            $statusClass = match($module['status']) {
                                'ready' => 'bg-success-500/10 text-success-700 dark:text-success-300',
                                'needs_setup' => 'bg-warning-500/10 text-warning-700 dark:text-warning-300',
                                'disconnected' => 'bg-danger-500/10 text-danger-700 dark:text-danger-300',
                                'error' => 'bg-danger-500/20 text-danger-800 dark:text-danger-200',
                                'locked' => 'bg-warning-500/20 text-warning-800 dark:text-warning-200',
                                default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
                            };
                        @endphp

                        <span class="text-xs px-2 py-1 rounded {{ $statusClass }}">
                            @switch($module['status'])
                                @case('ready') Ready @break
                                @case('disconnected') Disconnected @break
                                @case('error') Error @break
                                @case('locked') Locked @break
                                @case('disabled') Disabled @break
                                @default Needs setup
                            @endswitch
                        </span>

                    </div>

                    @if (! empty($module['locked']))
                        <div class="flex items-center gap-2">
                            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitPlans::getUrl() }}" size="sm">
                                Upgrade to Pro
                            </x-filament::button>
                            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitPlans::getUrl() }}" size="sm" color="gray">
                                View plans
                            </x-filament::button>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitSettings::getUrl() }}" size="sm" color="gray">
                                Configure
                            </x-filament::button>

                            <x-filament::button tag="a" href="{{ route('filament-sitekit.google.connect') }}" size="sm" color="gray">
                                Reconnect
                            </x-filament::button>

                            <x-filament::button tag="a" href="{{ \BoxinCode\FilamentSiteKit\Filament\Pages\SiteKitDiagnostics::getUrl() }}" size="sm" color="gray">
                                View details
                            </x-filament::button>

                            @if ($module['toggleable'] && $canConfigure)
                                @if ($module['status'] === 'disabled')
                                    <x-filament::button wire:click="enableModule('{{ $module['key'] }}')" size="sm">
                                        Enable
                                    </x-filament::button>
                                @else
                                    <x-filament::button wire:click="disableModule('{{ $module['key'] }}')" size="sm" color="danger">
                                        Disable
                                    </x-filament::button>
                                @endif
                            @else
                                @if ($module['toggleable'])
                                    <x-filament::button size="sm" color="gray" disabled>
                                        Locked
                                    </x-filament::button>
                                @endif
                            @endif
                        </div>
                    @endif

                    @if ($module['key'] === 'ga4' && empty($module['locked']))
                        <div class="space-y-2 pt-2">
                            <label class="text-sm font-medium">Property selector</label>
                            <select wire:model="ga4PropertyId" class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm" @disabled(! $canConfigure)>
                                <option value="">Select GA4 property</option>
                                @foreach ($this->ga4Options as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            @if ($canConfigure)
                                <x-filament::button size="sm" wire:click="saveGa4Config">Save</x-filament::button>
                            @endif
                        </div>
                    @endif

                    @if ($module['key'] === 'gsc' && empty($module['locked']))
                        <div class="space-y-2 pt-2">
                            <label class="text-sm font-medium">Site selector</label>
                            <select wire:model="gscSiteUrl" class="fi-input block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm" @disabled(! $canConfigure)>
                                <option value="">Select Search Console site</option>
                                @foreach ($this->gscOptions as $url => $label)
                                    <option value="{{ $url }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            @if ($canConfigure)
                                <x-filament::button size="sm" wire:click="saveGscConfig">Save</x-filament::button>
                            @endif
                        </div>
                    @endif
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
