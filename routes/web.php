<?php

use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome', [
        'appName' => config('app.name', 'MisCuentas'),
    ]);
})->name('landing');

/*
 * Alias requerido por el middleware 'auth' de Laravel:
 * redirige invitados al acceso del panel.
 */
Route::redirect('/login', '/admin/login')->name('login');

/*
 * Impresión de facturas: requiere sesión activa y permiso view_invoice.
 * La vista abre el diálogo de impresión automáticamente.
 */
Route::middleware(['auth'])
    ->get('/admin/invoices/{invoice}/print', function (Invoice $invoice) {
        if (! auth()->user()?->can('view_invoice')) {
            abort(403);
        }

        $invoice->load(['sale.items.product', 'thirdParty', 'currency']);

        return response()->view('invoices.print', ['invoice' => $invoice]);
    })
    ->name('invoices.print');
