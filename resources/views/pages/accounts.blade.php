<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Accounts</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b">
                        <th class="py-2">Account name</th>
                        <th class="py-2">Email</th>
                        <th class="py-2">Provider</th>
                        <th class="py-2">Users count</th>
                        <th class="py-2">Created date</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->accounts as $account)
                        @php
                            $sharingEnabled = app(\BoxinCode\FilamentSiteKit\SiteKitLicense::class)->allowsAccountSharing();
                            $canSwitch = \Illuminate\Support\Facades\Gate::allows('switch', $account);
                            $canEdit = \Illuminate\Support\Facades\Gate::allows('update', $account);
                            $canReconnect = \Illuminate\Support\Facades\Gate::allows('reconnect', $account);
                            $canDelete = \Illuminate\Support\Facades\Gate::allows('delete', $account);
                            $canManageUsers = $sharingEnabled && \Illuminate\Support\Facades\Gate::allows('manageUsers', $account);
                        @endphp

                        <tr class="border-b align-top">
                            <td class="py-2">{{ $account->name ?: ($account->display_name ?: ($account->email ?: ('Account #' . $account->id))) }}</td>
                            <td class="py-2">{{ $account->email }}</td>
                            <td class="py-2">{{ strtoupper($account->provider) }}</td>
                            <td class="py-2">{{ max(((int) ($account->shared_users_count ?? 0)), 1) }}</td>
                            <td class="py-2">{{ optional($account->created_at)->format('Y-m-d') }}</td>
                            <td class="py-2">
                                <div class="flex flex-wrap gap-2">
                                    @if ($this->editingAccountId === $account->id)
                                        <x-filament::button size="xs" wire:click="saveEdit">Save</x-filament::button>
                                    @else
                                        @if ($canSwitch)
                                            <x-filament::button size="xs" color="gray" wire:click="switchAccount({{ $account->id }})">Switch</x-filament::button>
                                        @endif
                                        @if ($canEdit)
                                            <x-filament::button size="xs" color="gray" wire:click="startEdit({{ $account->id }})">Edit name</x-filament::button>
                                        @endif
                                        @if ($canReconnect)
                                            <x-filament::button size="xs" color="gray" wire:click="reconnectAccount({{ $account->id }})">Reconnect</x-filament::button>
                                        @endif
                                        @if ($canDelete)
                                            <x-filament::button size="xs" color="danger" wire:click="deleteAccount({{ $account->id }})">Delete</x-filament::button>
                                        @endif
                                        @if ($canManageUsers)
                                            <x-filament::button size="xs" color="gray" wire:click="startManageUsers({{ $account->id }})">Manage users</x-filament::button>
                                        @elseif (! $sharingEnabled)
                                            <span class="text-xs text-warning-700 dark:text-warning-300">Upgrade to Pro</span>
                                        @endif
                                    @endif
                                </div>

                                @if ($this->editingAccountId === $account->id)
                                    <div class="mt-2">
                                        <input wire:model="editingName" type="text" class="fi-input w-full" placeholder="Account name">
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-gray-500">
                                No accounts connected yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @if ($this->managingAccountId)
        <x-filament::section>
            <x-slot name="heading">Manage users</x-slot>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="number"
                            wire:model="shareUserId"
                            placeholder="User ID"
                        />
                    </x-filament::input.wrapper>

                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model="shareRole">
                            <option value="viewer">Viewer</option>
                            <option value="admin">Admin</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>

                    <div class="flex gap-2">
                        <x-filament::button wire:click="addSharedUser">Add user</x-filament::button>
                        <x-filament::button color="gray" wire:click="stopManageUsers">Close</x-filament::button>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2">User</th>
                            <th class="py-2">Email</th>
                            <th class="py-2">Role</th>
                            <th class="py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->managedUsers as $user)
                            <tr class="border-b align-top">
                                <td class="py-2">{{ $user['name'] }}</td>
                                <td class="py-2">{{ $user['email'] ?: '—' }}</td>
                                <td class="py-2">
                                    <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-800">
                                        {{ strtoupper($user['role']) }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    @if ($user['role'] !== 'owner')
                                        <div class="flex gap-2">
                                            <x-filament::button size="xs" color="gray" wire:click="updateSharedUserRole({{ $user['id'] }}, 'admin')">Set admin</x-filament::button>
                                            <x-filament::button size="xs" color="gray" wire:click="updateSharedUserRole({{ $user['id'] }}, 'viewer')">Set viewer</x-filament::button>
                                            <x-filament::button size="xs" color="danger" wire:click="removeSharedUser({{ $user['id'] }})">Remove</x-filament::button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-gray-500">No shared users yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
