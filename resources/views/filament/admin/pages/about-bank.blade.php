<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Visi &amp; Misi</x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-300">
            Menjadi Bank Mini Sekolah yang profesional, terpercaya, dan edukatif sebagai sarana
            pembelajaran praktik perbankan serta membangun budaya literasi dan pengelolaan
            keuangan bagi seluruh warga sekolah.
        </p>

        <div class="mt-6 grid gap-x-16 gap-y-6 border-t border-gray-200 pt-6 md:grid-cols-2 dark:border-white/10">
            @foreach ($this->getMisi() as $i => $item)
                <div class="flex items-start text-sm text-neutral-700 dark:text-gray-200">
                    <span class="w-10 shrink-0 text-base text-gray-500">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="max-w-xs">{{ $item }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Peraturan Bank Mini Sekolah</x-slot>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($this->getPeraturan() as $item)
                <div class="flex items-center gap-3 rounded-lg bg-neutral-100 p-4 text-sm text-neutral-700 dark:bg-white/10 dark:text-gray-200">
                    <x-filament::icon :icon="$item['icon']" class="h-5 w-5 shrink-0 text-gray-500" />
                    <span>{{ $item['teks'] }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
