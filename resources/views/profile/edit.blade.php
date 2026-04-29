<x-app-layout>
    <x-slot name="title">{{ __('Profile') }}</x-slot>

    <div class="max-w-2xl space-y-6">

        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
            @include('profile.partials.update-password-form')
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
            @include('profile.partials.delete-user-form')
        </div>

    </div>
</x-app-layout>
