<x-onboarding-layout :step="1">
    <div class="bg-gray-900 border border-gray-800 rounded-2xl p-8">
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-white mb-2">{{ __('messages.register_title') }}</h2>
            <p class="text-gray-400 mt-1 text-sm">{{ __('messages.register_subtitle') }}</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" class="block mt-1.5 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" class="block mt-1.5 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
            </div>

            <x-primary-button class="w-full justify-center py-2.5 text-sm">
                Continuar →
            </x-primary-button>

            <p class="text-center text-sm text-gray-400">
                {{ __('messages.already_registered') }}
                <a href="{{ route('login') }}" class="text-green-400 hover:text-green-300 font-medium transition-colors">
                    {{ __('Log in') }}
                </a>
            </p>
        </form>
    </div>
</x-onboarding-layout>
