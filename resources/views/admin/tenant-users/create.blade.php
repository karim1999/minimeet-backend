@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <x-admin.page-header 
        title="Create Tenant User"
        subtitle="Add a new user to a tenant organization."
        :actions="[
            '<x-admin.button variant=\'secondary\' href=\'' . route('admin.tenant-users.index') . '\' icon=\'<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18\' />\'>Back to Users</x-admin.button>'
        ]"
    />

    <!-- Create Form -->
    <x-admin.card>
        <form action="{{ route('admin.tenant-users.store') }}" method="POST" class="divide-y divide-gray-200">
            @csrf

            <!-- Basic Information -->
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">User Information</h3>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Tenant Selection -->
                    <div class="sm:col-span-2">
                        <x-admin.form.select
                            name="tenant_id"
                            label="Tenant"
                            placeholder="Select a tenant..."
                            :required="true"
                            :value="old('tenant_id', $selectedTenant?->id)"
                        >
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}" {{ old('tenant_id', $selectedTenant?->id) == $tenant->id ? 'selected' : '' }}>
                                    {{ $tenant->id }} 
                                    @if($tenant->domains->count() > 0)
                                        ({{ $tenant->domains->pluck('domain')->implode(', ') }})
                                    @endif
                                </option>
                            @endforeach
                        </x-admin.form.select>
                    </div>

                    <!-- Full Name -->
                    <x-admin.form.input
                        name="name"
                        label="Full Name"
                        :required="true"
                        :value="old('name')"
                    />

                    <!-- Email -->
                    <x-admin.form.input
                        name="email"
                        type="email"
                        label="Email Address"
                        :required="true"
                        :value="old('email')"
                    />

                    <!-- Password -->
                    <x-admin.form.input
                        name="password"
                        type="password"
                        label="Password"
                        :required="true"
                    />

                    <!-- Confirm Password -->
                    <x-admin.form.input
                        name="password_confirmation"
                        type="password"
                        label="Confirm Password"
                        :required="true"
                    />

                    <!-- Role -->
                    <x-admin.form.select
                        name="role"
                        label="Role"
                        :required="true"
                        :value="old('role', 'member')"
                        :options="$roles"
                    />

                    <!-- Status -->
                    <x-admin.form.select
                        name="is_active"
                        label="Status"
                        :value="old('is_active', '1')"
                        :options="[
                            '1' => 'Active',
                            '0' => 'Inactive'
                        ]"
                    />
                </div>
            </div>

            <!-- Optional Information -->
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Additional Information (Optional)</h3>
                
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <!-- Department -->
                    <x-admin.form.input
                        name="department"
                        label="Department"
                        :value="old('department')"
                    />

                    <!-- Job Title -->
                    <x-admin.form.input
                        name="title"
                        label="Job Title"
                        :value="old('title')"
                    />

                    <!-- Phone -->
                    <div class="sm:col-span-2">
                        <x-admin.form.input
                            name="phone"
                            type="tel"
                            label="Phone Number"
                            :value="old('phone')"
                        />
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <x-slot name="footer">
                <div class="flex justify-end space-x-3">
                    <x-admin.button 
                        variant="secondary"
                        :href="route('admin.tenant-users.index')"
                    >
                        Cancel
                    </x-admin.button>
                    <x-admin.button 
                        type="submit"
                        variant="primary"
                        :icon="'<path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z\' />'"
                    >
                        Create User
                    </x-admin.button>
                </div>
            </x-slot>
        </form>
    </x-admin.card>

    @if ($errors->any())
        <x-admin.alert type="error" title="There were errors with your submission" :dismissible="true">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-admin.alert>
    @endif
</div>
@endsection