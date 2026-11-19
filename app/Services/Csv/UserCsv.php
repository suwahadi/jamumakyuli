<?php

declare(strict_types=1);

namespace App\Services\Csv;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserCsv implements CsvSchema
{
    public function columns(): array
    {
        return ['name', 'email', 'phone'];
    }

    public function rows(): iterable
    {
        return User::query()
            ->orderBy('email')
            ->select(['name', 'email', 'phone'])
            ->cursor()
            ->map(fn (User $user): array => [$user->name, $user->email, $user->phone]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function matchKey(): string
    {
        return 'email';
    }

    public function upsert(array $row): void
    {
        $user = User::query()->where('email', $row['email'])->first();

        if ($user) {
            $user->update([
                'name' => $row['name'],
                'phone' => filled($row['phone']) ? $row['phone'] : null,
            ]);

            return;
        }

        User::query()->create([
            'name' => $row['name'],
            'email' => $row['email'],
            'phone' => filled($row['phone']) ? $row['phone'] : null,
            'password' => Hash::make(Str::random(40)),
        ]);
    }

    public function nilaiKunci(array $data): string
    {
        return (string) ($data['email'] ?? '');
    }

    public function galatBaris(array $data): ?string
    {
        return null;
    }

    public function label(): string
    {
        return 'Pengguna';
    }
}
