<?php

namespace App\Filament\Support;

use App\Models\AuditLog;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;

class ImportModal
{
    public static function make(
        string $importer,
        string $templateFilename,
        Closure $templateRows,
        string $auditLabel,
        string $unit,
        ?Closure $afterImport = null,
        string $icon = 'heroicon-o-arrow-down-tray',
    ): Action {
        return Action::make('import')
            ->label('Import')
            ->icon($icon)
            ->color('gray')
            ->visible(fn () => Auth::user()?->role === 'admin')
            ->modalHeading('Import Data')
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Simpan')
            ->modalCancelActionLabel('Batalkan')
            ->modalAlignment(Alignment::Start)
            ->modalFooterActionsAlignment(Alignment::End)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->schema([
                FileUpload::make('file')
                    ->label('Unggah File')
                    ->placeholder('Seret dan lepas file di sini, atau <span class="filepond--label-action">Pilih File</span>')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(2048)
                    ->required(),
            ])
            ->modalFooterActions(fn (Action $action): array => [
                $action->getModalSubmitAction(),

                Action::make('unduhFormat')
                    ->label('Unduh Format')
                    ->color('gray')
                    ->outlined()
                    ->action(fn () => response()->streamDownload(function () use ($templateRows) {
                        $out = fopen('php://output', 'w');

                        foreach ($templateRows() as $row) {
                            fputcsv($out, $row);
                        }

                        fclose($out);
                    }, $templateFilename)),

                $action->getModalCancelAction(),
            ])
            ->action(function (array $data, $livewire) use ($importer, $auditLabel, $unit, $afterImport) {
                $path = $data['file'];
                $import = new $importer();

                try {
                    Excel::import($import, $path, 'local');
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('File tidak bisa dibaca')
                        ->body('Pastikan file berformat CSV atau Excel dengan judul kolom sesuai format. Klik Unduh Format untuk melihat contohnya.')
                        ->danger()
                        ->send();

                    return;
                } finally {
                    Storage::disk('local')->delete($path);
                }

                if ($import->imported > 0) {
                    AuditLog::record($auditLabel, null, $import->imported . ' ' . $unit);

                    if ($afterImport) {
                        $afterImport($livewire);
                    }
                }

                $import->notification()->send();
            });
    }
}
