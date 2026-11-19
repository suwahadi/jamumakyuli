<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class BusinessRuleException extends RuntimeException
{
    public static function insufficientStock(string $name, int $left): self
    {
        return new self('Stok '.$name.' tidak mencukupi. Sisa '.max(0, $left).'.');
    }
}
