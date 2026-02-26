<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Top Queries (Search Console)
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">Query</th>
                        <th class="py-2">Clicks</th>
                        <th class="py-2">Impressions</th>
                        <th class="py-2">CTR</th>
                        <th class="py-2">Position</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->queries() as $row)
                        <tr class="border-b">
                            <td class="py-2">{{ $row['label'] }}</td>
                            <td class="py-2">{{ number_format($row['clicks']) }}</td>
                            <td class="py-2">{{ number_format($row['impressions']) }}</td>
                            <td class="py-2">{{ number_format($row['ctr'] * 100, 2) }}%</td>
                            <td class="py-2">{{ number_format($row['position'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-3 text-gray-500" colspan="5">No data available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
