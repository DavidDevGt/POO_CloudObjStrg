<?php

declare(strict_types=1);

namespace Contracts;

interface FileStorageInterface
{
    public function store(string $tmpPath, string $storedName): bool;
    public function exists(string $storedName): bool;
    public function delete(string $storedName): bool;
    public function getAbsolutePath(string $storedName): string;
}
