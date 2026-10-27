<?php

namespace App\Filament\Admin\Resources\Transactions;

use App\Enums\TransactionType;
use App\Filament\Admin\Resources\Transactions\Pages;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Transaction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use UnitEnum;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | UnitEnum | null $navigationGroup = 'Operasional';
    protected static ?string $pluralModelLabel = 'Transaksi';
    protected static ?string $modelLabel = 'Transaksi';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Forms\Components\ToggleButtons::make('type')
                    ->hiddenLabel()
                    ->options(TransactionType::options())
                    ->default('setor_tunai')
                    ->grouped()
                    ->required()
                    ->live(),

                Forms\Components\Select::make('customer_id')
                    ->label('Nasabah')
                    ->relationship(
                        name: 'customer',
                        titleAttribute: 'full_name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true),
                    )
                    ->rule(Rule::exists('customers', 'id')->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->disabledOn('edit')
                    ->required(),

                Forms\Components\TextInput::make('amount')
                    ->label('Nominal')
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->minValue(1000)
                    ->mask(RawJs::make('$money($input, \',\', \'.\')'))
                    ->stripCharacters('.')
                    ->rule(function (Get $get, string $operation) {
                        return function (string $attribute, $value, \Closure $fail) use ($get, $operation) {
                            if ($operation !== 'create') {
                                return;
                            }

                            $type = $get('type');
                            $customerId = $get('customer_id');

                            if (in_array($type, ['tarik_tunai', 'tarik_kas']) && $customerId) {
                                $customer = Customer::find($customerId);

                                if ($customer && $value > $customer->balance) {
                                    $fail('Saldo nasabah tidak mencukupi. Saldo saat ini: Rp ' . number_format($customer->balance, 0, ',', '.'));
                                }
                            }
                        };
                    }),

                Forms\Components\TextInput::make('processed_by')
                    ->label('Diproses oleh')
                    ->placeholder('Nama pegawai')
                    ->maxLength(255)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchPlaceholder('Cari nasabah atau tanggal...')
            ->toolbarActions([
                ActionGroup::make(
                    collect([null, ...TransactionType::cases()])
                        ->map(fn (?TransactionType $type) => Action::make('type_' . ($type?->value ?? 'all'))
                            ->label($type?->label() ?? 'Semua Jenis Transaksi')
                            ->action(function ($livewire) use ($type) {
                                $livewire->typeFilter = $type?->value;
                                $livewire->resetPage();

                                if (method_exists($livewire, 'syncStatsFilters')) {
                                    $livewire->syncStatsFilters();
                                }
                            }))
                        ->all()
                )
                    ->button()
                    ->label(fn ($livewire) => TransactionType::tryFrom($livewire->typeFilter ?? '')?->label()
                        ?? 'Semua Jenis Transaksi')
                    ->icon('heroicon-m-chevron-down')
                    ->iconPosition('after')
                    ->color('gray'),
            ])
            ->modifyQueryUsing(fn (Builder $query, $livewire) => $query
                ->when(Auth::user()?->role === 'teller', fn ($q) => $q->whereDate('created_at', today()->toDateString()))
                ->when($livewire->typeFilter ?? null, fn ($q, $t) => $q->where('type', $t))
                ->when($livewire->dateFrom ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($livewire->dateUntil ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
            ->paginated([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $search = strtolower(trim($search));

                        if ($search === '') {
                            return $query;
                        }

                        $months = [
                            'jan' => 1, 'januari' => 1,
                            'feb' => 2, 'februari' => 2,
                            'mar' => 3, 'maret' => 3,
                            'apr' => 4, 'april' => 4,
                            'mei' => 5,
                            'jun' => 6, 'juni' => 6,
                            'jul' => 7, 'juli' => 7,
                            'agu' => 8, 'agustus' => 8, 'agt' => 8,
                            'sep' => 9, 'september' => 9,
                            'okt' => 10, 'oktober' => 10,
                            'nov' => 11, 'november' => 11,
                            'des' => 12, 'desember' => 12,
                        ];

                        $words = preg_split('/\s+/', $search);
                        $parsedDay = null;
                        $parsedMonth = null;
                        $parsedYear = null;

                        foreach ($words as $word) {
                            $cleanWord = trim($word, '.,/-');

                            if (isset($months[$cleanWord])) {
                                $parsedMonth = $months[$cleanWord];
                            } elseif (is_numeric($cleanWord)) {
                                $val = (int) $cleanWord;

                                if ($val > 31 || strlen($cleanWord) === 4) {
                                    $parsedYear = $val;
                                } elseif ($val >= 1 && $val <= 31) {
                                    if ($parsedDay === null) {
                                        $parsedDay = $val;
                                    } elseif ($parsedMonth === null && $val <= 12) {
                                        $parsedMonth = $val;
                                    }
                                }
                            }
                        }

                        if ($parsedDay !== null || $parsedMonth !== null || $parsedYear !== null) {
                            return $query->where(function (Builder $q) use ($parsedDay, $parsedMonth, $parsedYear) {
                                if ($parsedDay !== null) {
                                    $q->whereRaw("DATE_FORMAT(created_at, '%e') = ?", [$parsedDay]);
                                }
                                if ($parsedMonth !== null) {
                                    $q->whereRaw("DATE_FORMAT(created_at, '%c') = ?", [$parsedMonth]);
                                }
                                if ($parsedYear !== null) {
                                    $q->whereRaw("YEAR(created_at) = ?", [$parsedYear]);
                                }
                            });
                        }

                        $term = str_replace(['/', '.', ' '], '-', $search);
                        $like = '%' . addcslashes($term, '%_\\') . '%';

                        return $query->whereRaw("DATE_FORMAT(created_at, '%d-%m-%Y') LIKE ?", [$like]);
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.full_name')
                    ->label('Nasabah')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis Transaksi')
                    ->badge()
                    ->formatStateUsing(fn (TransactionType $state) => $state->label())
                    ->color(fn (TransactionType $state) => $state->color())
                    ->icon(fn (TransactionType $state) => match ($state) {
                        TransactionType::SetorTunai => 'heroicon-m-plus-circle',
                        TransactionType::TarikTunai => 'heroicon-m-minus-circle',
                        TransactionType::TarikKas => 'heroicon-m-x-circle',
                    }),

                Tables\Columns\TextColumn::make('processed_by')
                    ->label('Diproses oleh'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make()
                    ->label('Detail')
                    ->modalHeading('Detail Transaksi')
                    ->modalCancelAction(false)
                    ->modalWidth('md')
                    ->stickyModalHeader(),

                EditAction::make()
                    ->label('Edit')
                    ->modalHeading('Edit Transaksi')
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('Simpan')
                    ->modalCancelActionLabel('Batalkan')
                    ->modalAlignment(Alignment::Start)
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->visible(fn (Transaction $record) => Auth::user()?->role === 'admin'
                        && (bool) $record->customer?->is_active)
                    ->mutateFormDataUsing(function (array $data): array {
                        unset($data['customer_id']);

                        return $data;
                    })
                    ->before(function (Transaction $record, array $data, EditAction $action) {
                        $type = ($data['type'] ?? null) instanceof TransactionType
                            ? $data['type']
                            : TransactionType::tryFrom((string) ($data['type'] ?? ''));

                        if (! $type) {
                            return;
                        }

                        if ($record->balanceIfChangedTo($type, (int) ($data['amount'] ?? 0)) < 0) {
                            Notification::make()
                                ->title('Saldo nasabah tidak mencukupi')
                                ->body('Perubahan ini membuat saldo nasabah menjadi minus.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->after(function (Transaction $record, $livewire) {
                        $livewire->dispatch('transaction-saved');

                        AuditLog::record(
                            'Edit Transaksi',
                            $record,
                            $record->transaction_code . ' · ' . ($record->customer?->full_name ?? '-'),
                        );
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('amount')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0),

                TextEntry::make('type')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (TransactionType $state) => $state->label())
                    ->color(fn (TransactionType $state) => $state->color())
                    ->icon(fn (TransactionType $state) => match ($state) {
                        TransactionType::SetorTunai => 'heroicon-m-check-circle',
                        TransactionType::TarikTunai => 'heroicon-m-arrow-down-circle',
                        TransactionType::TarikKas => 'heroicon-m-exclamation-circle',
                    }),

                TextEntry::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i'),

                TextEntry::make('customer.full_name')
                    ->label('Nasabah'),

                TextEntry::make('customer.account_number')
                    ->label('No. Rekening'),

                TextEntry::make('processed_by')
                    ->label('Diproses Oleh'),

                TextEntry::make('transaction_code')
                    ->label('ID Transaksi')
                    ->columnSpanFull(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}
