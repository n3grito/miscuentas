<?php

namespace App\Filament\Pages;

use App\Models\Currency;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

class Settings extends Page implements HasForms
{
    use InteractsWithForms, InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $title = 'Configuración';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $data = [];

        Setting::all()->each(function (Setting $setting) use (&$data) {
            $value = $setting->value;

            if ($setting->type === 'encrypted' && $value !== null) {
                $value = decrypt($value);
            }

            data_set($data, "{$setting->group}.{$setting->key}", $value);
        });

        $this->form->fill($data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Empresa')
                    ->description('Datos que aparecen en facturas, cotizaciones y reportes.')
                    ->schema([
                        TextInput::make('company.name')
                            ->label('Nombre de la empresa')
                            ->required(),
                        TextInput::make('company.tin')
                            ->label('Número de identificación fiscal (NIT)'),
                        TextInput::make('company.address')
                            ->label('Dirección'),
                        TextInput::make('company.phone')
                            ->label('Teléfono')
                            ->tel(),
                        TextInput::make('company.email')
                            ->label('Correo de contacto')
                            ->email(),
                        Select::make('company.currency_id')
                            ->label('Moneda base')
                            ->options(Currency::query()
                                ->where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->columns(2),
                Section::make('Correo (SMTP)')
                    ->description('Credenciales cifradas con el APP_KEY. Nunca se almacenan en texto plano.')
                    ->schema([
                        TextInput::make('smtp.host')
                            ->label('Host')
                            ->placeholder('smtp.gmail.com'),
                        TextInput::make('smtp.port')
                            ->label('Puerto')
                            ->numeric()
                            ->placeholder('587'),
                        TextInput::make('smtp.username')
                            ->label('Usuario'),
                        TextInput::make('smtp.password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable(),
                        Select::make('smtp.encryption')
                            ->label('Cifrado')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                '' => 'Sin cifrado',
                            ]),
                        TextInput::make('smtp.from_address')
                            ->label('Correo remitente')
                            ->email(),
                        TextInput::make('smtp.from_name')
                            ->label('Nombre del remitente'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $group => $values) {
            foreach ($values as $key => $value) {
                if ($group === 'smtp' && filled($value)) {
                    $value = encrypt($value);
                }

                Setting::updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $value, 'type' => $group === 'smtp' ? 'encrypted' : 'string']
                );
            }
        }

        Notification::make()
            ->title('Configuración guardada')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar configuración')
                ->submit('save'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('update_setting') ?? false;
    }
}