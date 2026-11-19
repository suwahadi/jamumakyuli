<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderPaidNotification;
use App\Notifications\PaymentAlertNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class WebNotificationService
{
    public function orderCreated(Order $order): void
    {
        $this->send($this->adminsWithCustomer($order), new NewOrderNotification($order));
    }

    public function orderPaid(Order $order): void
    {
        $customer = $this->customer($order);

        if ($customer !== null) {
            $this->send(collect([$customer]), new OrderPaidNotification($order));
        }
    }

    public function paymentAlert(Order $order, string $reason): void
    {
        $this->send($this->admins(), new PaymentAlertNotification($order, $reason));
    }

    private function send(Collection $recipients, object $notification): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Notification::send($recipients, $notification);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function adminsWithCustomer(Order $order): Collection
    {
        $recipients = $this->admins();

        $customer = $this->customer($order);

        if ($customer !== null) {
            $recipients = $recipients->push($customer)->unique('id')->values();
        }

        return $recipients;
    }

    private function admins(): Collection
    {
        return User::query()->where('role', UserRole::ADMIN->value)->get();
    }

    private function customer(Order $order): ?User
    {
        if ($order->user_id === null) {
            return null;
        }

        return User::query()->find($order->user_id);
    }
}
