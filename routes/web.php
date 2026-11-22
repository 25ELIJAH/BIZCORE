<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\InvoiceController;
use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Customers\Show as CustomerShow;
use App\Livewire\Sales\Create as SalesCreate;
use App\Livewire\Sales\Index as SalesIndex;
use App\Livewire\Sales\Show as SalesShow;
use App\Livewire\Purchasing\Form as PurchaseOrderForm;
use App\Livewire\Purchasing\Index as PurchaseOrdersIndex;
use App\Livewire\Purchasing\Show as PurchaseOrderShow;
use App\Livewire\Suppliers\Index as SuppliersIndex;
use App\Livewire\Suppliers\Show as SupplierShow;
use App\Livewire\Finance\Categories as FinanceCategories;
use App\Livewire\Finance\Entries as FinanceEntries;
use App\Http\Controllers\ReportExportController;
use App\Livewire\Dashboard;
use App\Livewire\Reports\Index as ReportsIndex;
use App\Livewire\Reports\Show as ReportShow;
use App\Livewire\Hr\Attendance as HrAttendance;
use App\Livewire\Hr\Employees as HrEmployees;
use App\Livewire\Hr\Leave as HrLeave;
use App\Livewire\Inventory\Categories;
use App\Livewire\Inventory\Products;
use App\Livewire\Inventory\StockMovements;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('no-company', [CompanySwitchController::class, 'none'])->name('company.none');

    // Everything below runs inside a validated company context.
    Route::middleware('company')->group(function () {
        Route::get('/', Dashboard::class)->name('home');
        Route::post('company/switch', [CompanySwitchController::class, 'store'])->name('company.switch');

        Route::prefix('inventory')->name('inventory.')->middleware('can:inventory.view')->group(function () {
            Route::get('products', Products::class)->name('products');
            Route::get('categories', Categories::class)->name('categories');
            Route::get('movements', StockMovements::class)->name('movements');
        });

        Route::get('activity', \App\Livewire\Activity\Index::class)->name('activity')->middleware('can:activity_logs.view');
        Route::get('customers', CustomersIndex::class)->name('customers.index')->middleware('can:customers.view');
        Route::get('customers/{customer}', CustomerShow::class)->name('customers.show')->middleware('can:customers.view');
        Route::get('suppliers', SuppliersIndex::class)->name('suppliers.index')->middleware('can:suppliers.view');
        Route::get('suppliers/{supplier}', SupplierShow::class)->name('suppliers.show')->middleware('can:suppliers.view');

        Route::prefix('hr')->name('hr.')->group(function () {
            Route::get('employees', HrEmployees::class)->name('employees')->middleware('can:employees.view');
            Route::get('attendance', HrAttendance::class)->name('attendance')->middleware('can:attendance.manage');
            Route::get('leave', HrLeave::class)->name('leave');
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', ReportsIndex::class)->name('index');
            Route::get('{report}/export/{format}', ReportExportController::class)->name('export');
            Route::get('{report}', ReportShow::class)->name('show');
        });

        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/', FinanceEntries::class)->name('entries')->middleware('can:finance.view');
            Route::get('categories', FinanceCategories::class)->name('categories')->middleware('can:finance.categories.manage');
        });

        Route::prefix('purchase-orders')->name('purchase-orders.')->middleware('can:purchase_orders.view')->group(function () {
            Route::get('/', PurchaseOrdersIndex::class)->name('index');
            Route::get('create', PurchaseOrderForm::class)->name('create')->middleware('can:purchase_orders.manage');
            Route::get('{purchaseOrder}/edit', PurchaseOrderForm::class)->name('edit')->middleware('can:purchase_orders.manage');
            Route::get('{purchaseOrder}', PurchaseOrderShow::class)->name('show');
        });

        Route::prefix('sales')->name('sales.')->middleware('can:sales.view')->group(function () {
            Route::get('/', SalesIndex::class)->name('index');
            Route::get('create', SalesCreate::class)->name('create')->middleware('can:sales.create');
            Route::get('{sale}', SalesShow::class)->name('show');
            Route::get('{sale}/invoice', [InvoiceController::class, 'show'])->name('invoice');
        });
    });
});
