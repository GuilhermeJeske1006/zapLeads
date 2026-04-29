<x-guest-layout>
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-white">{{ __('messages.login_title') }}</h2>
        <p class="text-gray-400 mt-1 text-sm">{{ __('messages.login_subtitle') }}</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded border-gray-700 bg-gray-800 text-green-600 shadow-sm focus:ring-green-500 focus:ring-offset-gray-950" name="remember">
                <span class="ms-2 text-sm text-gray-400">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-green-400 hover:text-green-300 transition-colors" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <x-primary-button class="w-full justify-center py-2.5 text-sm">
            {{ __('Log in') }}
        </x-primary-button>

        @if (Route::has('register'))
            <p class="text-center text-sm text-gray-400">
                {{ __('messages.no_account') }}
                <a href="{{ route('register') }}" class="text-green-400 hover:text-green-300 font-medium transition-colors">
                    {{ __('messages.create_account') }}
                </a>
            </p>
        @endif
    </form>
</x-guest-layout>
