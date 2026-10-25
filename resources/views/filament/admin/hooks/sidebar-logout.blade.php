<div class="border-t border-gray-200 p-3 dark:border-white/10">
    <form method="POST" action="{{ filament()->getLogoutUrl() }}">
        @csrf

        <button
            type="submit"
            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 dark:text-gray-200 dark:hover:bg-white/5"
        >
            <x-filament::icon icon="heroicon-o-arrow-left-on-rectangle" class="h-5 w-5 text-gray-500" />
            <span>Keluar</span>
        </button>
    </form>
</div>
