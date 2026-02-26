<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Top Pages
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">Page</th>
                        <th class="py-2">Views / Clicks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->pages() as $row)
                        <tr class="border-b">
                            <td class="py-2">{{ $row['page'] }}</td>
                            <td class="py-2">{{ number_format((float) $row['views']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-3 text-gray-500" colspan="2">No data available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
