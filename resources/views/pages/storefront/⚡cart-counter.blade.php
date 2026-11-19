<?php

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $count = 0;

    public function mount(CartService $cart): void
    {
        $this->count = $cart->count();
    }

    #[On('cart-updated')]
    public function refresh(CartService $cart): void
    {
        $this->count = $cart->count();
    }
}; ?>

<span class="contents">
    @if ($count > 0)
        <span class="absolute -right-0.5 -top-0.5 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-brand-green px-1 text-[10px] font-bold leading-none text-white">
            {{ $count }}
        </span>
    @endif
</span>
