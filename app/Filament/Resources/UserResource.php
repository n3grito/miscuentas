<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Support\PermissionForm;
use App\Models\User;
use App\Support\PermissionGroups;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $slug = 'users';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del usuario')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Forms\Components\Select::make('theme')
                            ->label('Tema')
                            ->options([
                                'default' => 'Predeterminado',
                                'light' => 'Claro',
                                'dark' => 'Oscuro',
                                'system' => 'Sistema',
                            ])
                            ->default('default'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Roles')
                    ->schema([
                        Forms\Components\CheckboxList::make('roles')
                            ->relationship('roles', 'name')
                            ->label('Asignar roles')
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
                Forms\Components\Section::make('Resumen de accesos efectivos')
                    ->description('Permisos totales del usuario combinando sus roles y sus permisos directos.')
                    ->schema([
                        Forms\Components\Placeholder::make('permisos_efectivos')
                            ->label('')
                            ->content(function (?User $record, $get): string {
                                $roleIds = array_map(
                                    fn ($id) => (int) $id,
                                    array_filter((array) $get('roles'))
                                );

                                $directIds = PermissionGroups::extractIds((array) $get('direct_permissions'));

                                $count = \Spatie\Permission\Models\Permission::query()
                                    ->whereIn('id', array_merge($roleIds, $directIds))
                                    ->count();

                                return $count === 0
                                    ? 'Sin accesos asignados todavía.'
                                    : "{$count} permisos activos entre roles y permisos directos.";
                            }),
                    ]),
                Forms\Components\Section::make('Permisos directos por módulo')
                    ->description('Opcional: concede accesos adicionales a este usuario sin crear un rol. Se organizan por módulo igual que en los roles.')
                    ->collapsible()
                    ->schema(PermissionForm::fields('direct_permissions')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('effective_permissions')
                    ->label('Permisos efectivos')
                    ->badge()
                    ->color('success')
                    ->state(fn (User $record): int => $record->getAllPermissions()->count()),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Último acceso')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Nunca'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Rol'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}