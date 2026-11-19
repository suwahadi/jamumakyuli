<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class LabelPengirimanController extends Controller
{
    public function __invoke(Order $order): View
    {
        abort_unless(in_array($order->status, OrderStatus::berpenghasilan(), true), 404);

        return view('orders.label', ['order' => $order->load('items.product.variants', 'items.variant')]);
    }
}
