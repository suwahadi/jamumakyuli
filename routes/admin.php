<?php

use App\Http\Controllers\Admin\LabelPengirimanController;
use App\Http\Controllers\Admin\TransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');

    Route::livewire('orders', 'pages::admin.orders.index')->name('orders.index');
    Route::livewire('orders/{order:order_number}', 'pages::admin.orders.show')->name('orders.show');
    Route::get('orders/{order:order_number}/label', LabelPengirimanController::class)->name('orders.label');

    Route::livewire('products', 'pages::admin.products.index')->name('products.index');
    Route::livewire('products/create', 'pages::admin.products.form')->name('products.create');
    Route::livewire('products/{product}/edit', 'pages::admin.products.form')->name('products.edit');

    Route::livewire('categories', 'pages::admin.categories.index')->name('categories.index');
    Route::livewire('pages', 'pages::admin.pages.index')->name('pages.index');
    Route::livewire('slides', 'pages::admin.slides.index')->name('slides.index');
    Route::livewire('testimonials', 'pages::admin.testimonials.index')->name('testimonials.index');
    Route::livewire('about-images', 'pages::admin.about-images.index')->name('about-images.index');
    Route::livewire('settings', 'pages::admin.settings')->name('settings');

    Route::livewire('users', 'pages::admin.users.index')->name('users.index');

    Route::livewire('notifications', 'pages::admin.notifications.index')->name('notifications.index');

    Route::livewire('reports/sales', 'pages::admin.reports')->name('reports.sales');
    Route::livewire('stock-opname', 'pages::admin.stock-opname')->name('stock-opname.index');
    Route::livewire('transfer/products', 'pages::admin.transfer')->name('transfer.products');
    Route::livewire('transfer/users', 'pages::admin.transfer')->name('transfer.users');
    Route::get('transfer/products/export', [TransferController::class, 'exportProducts'])->name('transfer.products.export');
    Route::get('transfer/users/export', [TransferController::class, 'exportUsers'])->name('transfer.users.export');
});
