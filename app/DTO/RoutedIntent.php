<?php

namespace App\DTO;

use App\Support\Mode;

final class RoutedIntent
{
    public function __construct(
        public readonly Mode $mode,
        public readonly string $input,
    ) {
    }
}
