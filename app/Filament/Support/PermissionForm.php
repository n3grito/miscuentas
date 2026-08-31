<?php

namespace App\Filament\Support;

use App\Support\PermissionGroups;
use Filament\Forms;
use Filament\Forms\Components\Component;

class PermissionForm
{
    /**
     * Campos de selección de permisos agrupados por módulo funcional.
     *
     * @return array<int, Component>
     */
    public static function fields(string $statePath): array
    {
        $components = [];

        foreach (PermissionGroups::groupedOptions() as $module => $options) {
            if ($options === []) {
                continue;
            }

            $key = PermissionGroups::keyFor($module);
            $path = "{$statePath}.{$key}";

            $components[] = Forms\Components\Section::make($module)
                ->description(count($options) . ' permisos')
                ->schema([
                    Forms\Components\CheckboxList::make($path)
                        ->label($module)
                        ->options($options)
                        ->columns(3)
                        ->searchable()
                        ->bulkToggleable(),
                ])
                ->aside(false)
                ->collapsible();
        }

        return $components;
    }
}