<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed;

class Money
{
    public function toMinorUnits(float $amount, string $currency): int
    {
        $decimals = $this->decimals($currency);
        return (int) round($amount * (10 ** $decimals));
    }

    public function formatAmount(float $amount, string $currency): string
    {
        $decimals = $this->decimals($currency);
        return number_format($amount, $decimals, '.', '') . ' ' . strtoupper($currency);
    }

    public function decimals(string $currency): int
    {
        return match (strtoupper($currency)) {
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' => 0,
            'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND' => 3,
            default => 2,
        };
    }
}
