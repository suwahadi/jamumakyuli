<?php

use App\Http\Controllers\OrderInvoiceController;
use App\Http\Controllers\Payment\MidtransNotificationController;
use App\Http\Controllers\Payment\PaymentRedirectController;
use App\Models\Order;
use App\Models\Page;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::livewire('/', 'pages::storefront.home')->name('home');
Route::livewire('/products', 'pages::storefront.catalog')->name('products.index');
Route::livewire('/product/{product:slug}', 'pages::storefront.product')
    ->name('products.show');
Route::livewire('/cart', 'pages::storefront.cart')->name('cart.index');
Route::livewire('/wishlist', 'pages::storefront.wishlist')->name('wishlist.index');
Route::livewire('/checkout', 'pages::storefront.checkout')->name('checkout');
Route::livewire('/track', 'pages::storefront.track')->name('track');
Route::livewire('/kontak', 'pages::storefront.kontak')->name('kontak');
Route::livewire('/categories', 'pages::storefront.categories')->name('categories');
Route::livewire('/order/{order:uuid}', 'pages::storefront.order-success')->name('checkout.success');
Route::get('/order/{order:uuid}/invoice', OrderInvoiceController::class)->name('orders.invoice');

Route::post('/payments/midtrans/notification', MidtransNotificationController::class)
    ->middleware('throttle:120,1')
    ->name('payments.midtrans.notification');

Route::get('/payments/midtrans/finish', [PaymentRedirectController::class, 'finish'])
    ->name('payments.midtrans.finish');

if (! app()->isProduction()) {
    Route::get('/__email-preview/{template?}', function (?string $template = null) {
        $templates = [
            'new-order-customer' => 'Pesanan diterima (customer)',
            'new-order-admin' => 'Pesanan baru (admin)',
            'payment-paid' => 'Pembayaran lunas (customer)',
            'payment-paid-admin' => 'Pembayaran lunas (admin)',
            'order-shipped' => 'Pesanan dikirim (customer)',
            'order-delivered' => 'Pesanan selesai (customer)',
            'payment-alert' => 'Peringatan pembayaran (admin)',
            'reset-password' => 'Atur ulang kata sandi (auth)',
        ];

        if ($template === null) {
            $links = collect($templates)
                ->map(fn ($label, $key) => '<li style="margin:6px 0"><a href="/__email-preview/'.$key.'">'.$label.'</a> <code style="color:#888">'.$key.'</code></li>')
                ->implode('');

            return '<div style="font:15px/1.6 system-ui;padding:32px;max-width:640px"><h1 style="font-size:18px">Pratinjau Email</h1><p style="color:#666">Merender template dengan order terbaru, tanpa mengirim email.</p><ul style="list-style:none;padding:0">'.$links.'</ul></div>';
        }

        abort_unless(isset($templates[$template]), 404);

        if ($template === 'reset-password') {
            return view('emails.auth.reset-password', [
                'url' => url(route('password.reset', [
                    'token' => Str::random(64),
                    'email' => 'budi@contoh.id',
                ], false)),
                'email' => 'budi@contoh.id',
                'nama' => 'Budi Santoso',
                'expireMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
        }

        $order = Order::query()
            ->with('items.product', 'items.variant')
            ->latest()
            ->firstOrFail();

        return view('emails.orders.'.$template, [
            'order' => $order,
            'reason' => 'Nominal pembayaran tidak cocok dengan total order. Perlu review.',
        ]);
    })->name('email.preview');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');
    Route::livewire('dashboard/orders', 'pages::dashboard.orders.index')->name('dashboard.orders.index');
    Route::livewire('dashboard/order/{order:order_number}', 'pages::dashboard.orders.show')->name('dashboard.orders.show');
    Route::livewire('dashboard/notifications', 'pages::dashboard.notifications')->name('dashboard.notifications.index');
    Route::livewire('dashboard/wishlist', 'pages::dashboard.wishlist')->name('dashboard.wishlist');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';

Route::livewire('/{page:slug}', 'pages::storefront.page')
    ->name('pages.show')
    ->where('page', '(?!('.implode('|', Page::RESERVED_SLUGS).')$)[a-z0-9][a-z0-9-]*');
