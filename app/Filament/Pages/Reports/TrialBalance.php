<?php

namespace App\Filament\Pages\Reports;

use App\Models\Account;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalance extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'filament.pages.reports.trial-balance';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?string $title = 'Balance de comprobación';

    protected static ?string $slug = 'reportes/balance-comprobacion';

    protected static ?int $navigationSort = 4;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('view_reports'), 403);
    }

    protected function getTableQuery(): Builder
    {
        return Account::query()
            ->selectRaw('accounts.*, COALESCE(SUM(journal_entry_lines.debit), 0) as total_debits, COALESCE(SUM(journal_entry_lines.credit), 0) as total_credits')
            ->leftJoin('journal_entry_lines', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join) {
                $join->on('journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                    ->where('journal_entries.status', 'posted');
            })
            ->groupBy('accounts.id');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Cuenta'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Account::TYPE_ASSET => 'success',
                        Account::TYPE_LIABILITY => 'warning',
                        Account::TYPE_EQUITY => 'info',
                        Account::TYPE_INCOME => 'primary',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => Account::types()[$state] ?? $state),
                TextColumn::make('total_debits')
                    ->label('Debe')
                    ->numeric(2)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('total_credits')
                    ->label('Haber')
                    ->numeric(2)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('balance')
                    ->label('Saldo')
                    ->state(function ($record): float {
                        $debit = (float) ($record->total_debits ?? 0);
                        $credit = (float) ($record->total_credits ?? 0);
                        $naturalDebit = in_array($record->type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE], true);

                        return round($naturalDebit ? $debit - $credit : $credit - $debit, 6);
                    })
                    ->numeric(2)
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->defaultSort('code', 'asc')
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->headerActions([
                \Filament\Tables\Actions\Action::make('export')
                    ->label('Exportar CSV')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(function (): StreamedResponse {
                        return response()->streamDownload(function () {
                            $out = fopen('php://output', 'w');
                            fwrite($out, "\xEF\xBB\xBF");
                            fputcsv($out, ['Código', 'Cuenta', 'Tipo', 'Debe', 'Haber', 'Saldo']);

                            foreach ($this->getTableQuery()->orderBy('code')->get() as $account) {
                                $debit = (float) $account->total_debits;
                                $credit = (float) $account->total_credits;
                                $naturalDebit = in_array($account->type, [Account::TYPE_ASSET, Account::TYPE_EXPENSE], true);

                                fputcsv($out, [
                                    $account->code,
                                    $account->name,
                                    Account::types()[$account->type] ?? $account->type,
                                    number_format($debit, 2, '.', ''),
                                    number_format($credit, 2, '.', ''),
                                    number_format(round($naturalDebit ? $debit - $credit : $credit - $debit, 6), 2, '.', ''),
                                ]);
                            }

                            fclose($out);
                        }, 'balance-comprobacion.csv');
                    }),
            ])
            ->paginated([25, 50, 100]);
    }

    /**
     * Totales generales para el encabezado de la página.
     */
    protected function getTotals(): array
    {
        $rows = $this->getTableQuery()->get();

        $totalDebit = round((float) $rows->sum('total_debits'), 6);
        $totalCredit = round((float) $rows->sum('total_credits'), 6);

        return [
            'debits' => $totalDebit,
            'credits' => $totalCredit,
            'balanced' => abs($totalDebit - $totalCredit) < 0.000001,
        ];
    }

    public function getViewData(): array
    {
        return [
            'totals' => $this->getTotals(),
        ];
    }
}