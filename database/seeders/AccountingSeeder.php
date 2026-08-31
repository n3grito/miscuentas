<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['code' => '100000', 'name' => 'ACTIVO', 'type' => Account::TYPE_ASSET, 'parent' => null],
            ['code' => '110000', 'name' => 'Caja General', 'type' => Account::TYPE_ASSET, 'parent' => '100000'],
            ['code' => '120000', 'name' => 'Bancos', 'type' => Account::TYPE_ASSET, 'parent' => '100000'],
            ['code' => '130000', 'name' => 'Cuentas por Cobrar', 'type' => Account::TYPE_ASSET, 'parent' => '100000'],
            ['code' => '140000', 'name' => 'Inventario', 'type' => Account::TYPE_ASSET, 'parent' => '100000'],
            ['code' => '200000', 'name' => 'PASIVO', 'type' => Account::TYPE_LIABILITY, 'parent' => null],
            ['code' => '210000', 'name' => 'Proveedores', 'type' => Account::TYPE_LIABILITY, 'parent' => '200000'],
            ['code' => '300000', 'name' => 'PATRIMONIO', 'type' => Account::TYPE_EQUITY, 'parent' => null],
            ['code' => '310000', 'name' => 'Capital Social', 'type' => Account::TYPE_EQUITY, 'parent' => '300000'],
            ['code' => '400000', 'name' => 'INGRESOS', 'type' => Account::TYPE_INCOME, 'parent' => null],
            ['code' => '410000', 'name' => 'Ventas de bienes', 'type' => Account::TYPE_INCOME, 'parent' => '400000'],
            ['code' => '500000', 'name' => 'COSTOS Y GASTOS', 'type' => Account::TYPE_EXPENSE, 'parent' => null],
            ['code' => '510000', 'name' => 'Costo de ventas', 'type' => Account::TYPE_EXPENSE, 'parent' => '500000'],
        ];

        foreach ($accounts as $accountData) {
            $account = Account::firstOrCreate(
                ['code' => $accountData['code']],
                [
                    'name' => $accountData['name'],
                    'type' => $accountData['type'],
                    'parent_id' => $accountData['parent'] ? Account::where('code', $accountData['parent'])->value('id') : null,
                    'is_active' => true,
                ]
            );

            if ($account->parent_id === null && $accountData['parent'] !== null) {
                $account->update(['parent_id' => Account::where('code', $accountData['parent'])->value('id')]);
            }
        }
    }
}