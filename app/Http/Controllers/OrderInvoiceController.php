<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\View\View;

class OrderInvoiceController extends Controller
{
    public function __invoke(Order $order): View
    {
        abort_if($order->status === OrderStatus::CANCELLED, 404);

        return view('orders.invoice', [
            'order' => $order->load('items.product', 'items.variant', 'paymentAttempts'),
        ]);
    }
}
