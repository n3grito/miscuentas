<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class StockAdjustment extends Page implements HasForms
{
    use InteractsWithForms, InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Inventario';

    protected static ?string $title = 'Ajuste de stock';

    protected static ?string $slug = 'inventario/ajuste-stock';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.stock-adjustment';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('adjust_inventory') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('product_id')
                    ->label('Producto')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(fn () => Product::query()
                        ->where('track_inventory', true)
                        ->where('type', '!=', 'service')
                        ->orderBy('name')
                        ->pluck('name', 'id')),
                Select::make('warehouse_id')
                    ->label('Almacén')
                    ->required()
                    ->searchable()
                    ->options(fn () => Warehouse::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')),
                Radio::make('type')
                    ->label('Tipo de ajuste')
                    ->required()
                    ->options([
                        'in' => 'Entrada (+)',
                        'out' => 'Salida (−)',
                    ])
                    ->default('in')
                    ->inline(),
                TextInput::make('quantity')
                    ->label('Cantidad')
                    ->required()
                    ->numeric()
                    ->minValue(0.0001)
                    ->step(0.0001),
                TextInput::make('unit_cost')
                    ->label('Costo unitario (solo entrada)')
                    ->numeric()
                    ->default(0)
                    ->step(0.000001),
                Textarea::make('reason')
                    ->label('Motivo')
                    ->required()
                    ->maxLength(500)
                    ->rows(3)
                    ->placeholder('Ej.: Ajuste por conteo físico, merma, devolución, corrección de stock.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $product = Product::findOrFail($data['product_id']);
        $quantity = (float) $data['quantity'];

        try {
            app(InventoryService::class)->adjust(
                $product,
                (int) $data['warehouse_id'],
                $data['type'],
                $quantity,
                (float) ($data['unit_cost'] ?? 0),
                reason: $data['reason'],
                createdBy: auth()->id(),
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('No se pudo realizar el ajuste')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Ajuste registrado')
            ->body(($data['type'] === 'in' ? 'Entrada de ' : 'Salida de ')
                .number_format($quantity, 4, ',', '.')
                .' — '.$product->name)
            ->success()
            ->send();

        $this->form->fill([
            'product_id' => null,
            'warehouse_id' => null,
            'type' => 'in',
            'quantity' => null,
            'unit_cost' => null,
            'reason' => null,
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Registrar ajuste')
                ->icon('heroicon-o-check-circle')
                ->submit('save'),
            Action::make('reset')
                ->label('Limpiar')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->form->fill([
                        'product_id' => null,
                        'warehouse_id' => null,
                        'type' => 'in',
                        'quantity' => null,
                        'unit_cost' => null,
                        'reason' => null,
                    ]);
                }),
        ];
    }
}