<button
    type="button"
    x-data="themeToggle()"
    x-on:click="toggle()"
    x-bind:aria-label="theme === 'dark' ? 'Ativar modo claro' : 'Ativar modo escuro'"
    class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-600 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950"
>
    <svg x-show="theme !== 'dark'" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M12 3v2.25M12 18.75V21M4.64 4.64l1.59 1.59M17.77 17.77l1.59 1.59M3 12h2.25M18.75 12H21M4.64 19.36l1.59-1.59M17.77 6.23l1.59-1.59" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        <circle cx="12" cy="12" r="4.25" stroke="currentColor" stroke-width="1.8" />
    </svg>
    <svg x-show="theme === 'dark'" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path d="M20.25 15.28A8.25 8.25 0 0 1 8.72 3.75 8.25 8.25 0 1 0 20.25 15.28Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <span class="hidden sm:inline" x-cloak x-text="theme === 'dark' ? 'Claro' : 'Escuro'">Escuro</span>
</button>
