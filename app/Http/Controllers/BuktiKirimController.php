<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuktiKirimController extends Controller
{
    public function __invoke(Request $request, Order $order): StreamedResponse
    {
        $pengguna = $request->user();

        abort_unless($pengguna->isAdmin() || $pengguna->can('view', $order), 403);

        $disk = Storage::disk('local');
        abort_unless(filled($order->shipping_proof_path) && $disk->exists($order->shipping_proof_path), 404);

        return $disk->response($order->shipping_proof_path, null, [
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
