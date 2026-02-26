@php
    $manager = app(\BoxinCode\FilamentSiteKit\SiteKitAccountManager::class);
    $accounts = $manager->allForUser();
    $current = $manager->current();
@endphp

@if ($accounts->count() > 1)
    <form method="GET" action="{{ route('filament-sitekit.accounts.switch') }}" class="hidden md:flex items-center gap-2 mr-2">
        <input type="hidden" name="redirect" value="{{ url()->current() }}">
        <label class="text-xs text-gray-500 dark:text-gray-300 whitespace-nowrap">Active Account:</label>
        <select
            name="account_id"
            class="fi-input block rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm"
            onchange="this.form.submit()"
        >
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected(optional($current)->id === $account->id)>
                    {{ $account->display_name ?: $account->email }}
                </option>
            @endforeach
        </select>
    </form>
@endif
