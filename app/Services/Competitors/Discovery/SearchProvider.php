<?php

namespace App\Services\Competitors\Discovery;

interface SearchProvider
{
    public function configured(): bool;

    /**
     * @return array<int,array{title:string,url:string,snippet:string}>
     * @throws \RuntimeException with a plain reason when the provider rejects the request
     */
    public function search(string $query, int $max = 10): array;
}
