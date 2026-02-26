<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Overview</x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            @foreach ($this->stats() as $stat)
                <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-3">
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                    <p class="text-xl font-semibold">{{ $stat['value'] }}</p>
                    @if (! empty($stat['description']))
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $stat['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
