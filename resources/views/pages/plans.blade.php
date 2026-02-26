<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Choose your plan</x-slot>

            <div class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
                <p>Current plan: <span class="font-medium">{{ $this->currentPlanLabel() }}</span></p>
                <p>Unlock more value with Pro when you are ready.</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Plan comparison</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="py-2">Feature</th>
                            <th class="py-2">Free</th>
                            <th class="py-2">
                                <span class="inline-flex items-center gap-1">
                                    Pro
                                    <span class="rounded bg-primary-500/10 px-2 py-0.5 text-xs text-primary-700 dark:text-primary-300">Recommended</span>
                                </span>
                            </th>
                            <th class="py-2">Agency</th>
                            <th class="py-2">Enterprise</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->features as $row)
                            <tr class="border-b">
                                <td class="py-2">{{ $row['feature'] }}</td>
                                <td class="py-2">{{ $row['free'] ? '✓' : '—' }}</td>
                                <td class="py-2 font-medium text-primary-700 dark:text-primary-300">{{ $row['pro'] ? '✓' : '—' }}</td>
                                <td class="py-2">{{ $row['agency'] ? '✓' : '—' }}</td>
                                <td class="py-2">{{ $row['enterprise'] ? '✓' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">How to upgrade</x-slot>

            <div class="space-y-3">
                <p class="text-sm text-gray-600 dark:text-gray-300">Set your plan in environment config:</p>
                <pre class="rounded-lg border p-3 text-sm"><code>SITEKIT_LICENSE=pro</code></pre>

                <div class="flex items-center gap-2">
                    <x-filament::button size="sm">Upgrade to Pro</x-filament::button>
                    <span class="text-xs text-gray-500">Then run config cache clear if needed.</span>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
