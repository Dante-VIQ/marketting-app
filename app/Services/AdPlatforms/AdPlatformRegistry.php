<?php

namespace App\Services\AdPlatforms;

class AdPlatformRegistry
{
    public function __construct(private array $platforms = [])
    {
    }

    /** Build from config('ad_platforms.drivers'): name => class-string implementing AdPlatformInterface. */
    public static function fromConfig(): self
    {
        $platforms = [];
        foreach (config('ad_platforms.drivers', []) as $name => $class) {
            $platforms[$name] = app($class);
        }

        return new self($platforms);
    }

    public function has(string $name): bool
    {
        return isset($this->platforms[strtolower($name)]);
    }

    /** @throws AdPlatformException */
    public function get(string $name): AdPlatformInterface
    {
        $name = strtolower($name);
        if (!isset($this->platforms[$name])) {
            throw new AdPlatformException("Ad platform '{$name}' is not supported.");
        }

        return $this->platforms[$name];
    }

    public function names(): array
    {
        return array_keys($this->platforms);
    }
}
