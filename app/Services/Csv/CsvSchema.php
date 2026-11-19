<?php

declare(strict_types=1);

namespace App\Services\Csv;

interface CsvSchema
{
    public function columns(): array;

    public function rows(): iterable;

    public function rules(): array;

    public function matchKey(): string;

    public function nilaiKunci(array $data): string;

    public function galatBaris(array $data): ?string;

    public function upsert(array $row): void;

    public function label(): string;
}
