<?php

namespace App\Filament\Admin\Pages;

use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AboutBank extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Lainnya';

    protected static ?string $navigationLabel = 'Tentang Bank';

    protected static ?string $title = 'Tentang Bank';

    protected static ?string $slug = 'tentang-bank';

    protected string $view = 'filament.admin.pages.about-bank';

    public function getMisi(): array
    {
        return [
            'Memberikan pelayanan yang aman, tertib, cepat, dan ramah.',
            'Membudayakan menabung dan pengelolaan keuangan yang bijak.',
            'Meningkatkan keterampilan dan kompetensi siswa di bidang perbankan.',
            'Membentuk siswa yang jujur, disiplin, teliti, dan bertanggung jawab.',
        ];
    }

    public function getPeraturan(): array
    {
        return [
            ['icon' => 'heroicon-o-users', 'teks' => 'Tidak diperkenankan menabung atas nama orang lain.'],
            ['icon' => 'heroicon-o-circle-stack', 'teks' => 'Tidak diperkenankan menabung atas nama orang lain.'],
            ['icon' => 'heroicon-o-arrow-path', 'teks' => 'Selalu cek jumlah saldo sebelum meninggalkan lokasi.'],
        ];
    }
}
