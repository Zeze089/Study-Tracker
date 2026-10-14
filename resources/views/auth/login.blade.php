<x-guest-layout>
    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <div>
            <x-input-label for="email" value="Email" class="text-slate-700" />
            <x-text-input id="email" class="mt-2 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-teal-600 focus:ring-teal-600" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="voce@email.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Senha" class="text-slate-700" />

            <x-text-input id="password" class="mt-2 block h-11 w-full rounded-lg border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-teal-600 focus:ring-teal-600"
                            type="password"
                            name="password"
                            placeholder="Digite sua senha"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember" class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 shrink-0 rounded border-slate-300 text-teal-700 focus:ring-teal-600" />
            <span>Manter conectado</span>
        </label>

        <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
            Entrar
        </button>
    </form>
</x-guest-layout>
