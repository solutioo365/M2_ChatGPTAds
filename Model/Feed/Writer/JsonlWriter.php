<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Model\Feed\Writer;

class JsonlWriter
{
    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function write(array $items): string
    {
        $lines = [];
        foreach ($items as $item) {
            $lines[] = json_encode($this->normalize($item), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalize(array $item): array
    {
        $out = [];
        foreach ($item as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }
}
