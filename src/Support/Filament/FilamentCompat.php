<?php

declare(strict_types=1);

namespace BoxinCode\FilamentSiteKit\Support\Filament;

class FilamentCompat
{
    /**
     * @param array<int, mixed> $args
     */
    public function callIfExists(object $object, string $method, array $args = []): mixed
    {
        if (! method_exists($object, $method)) {
            return null;
        }

        return $object->{$method}(...$args);
    }

    public function classExists(string $className): bool
    {
        return class_exists($className);
    }

    public function methodExists(object $object, string $method): bool
    {
        return method_exists($object, $method);
    }
}
