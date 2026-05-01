<?php

declare(strict_types=1);

namespace Storage;

use Contracts\FileStorageInterface;

class LocalFileStorage implements FileStorageInterface
{
    private string $uploadDir;

    public function __construct(?string $uploadDir = null)
    {
        $this->uploadDir = $uploadDir ?? dirname(__DIR__) . '/uploads/';
    }

    public function store(string $tmpPath, string $storedName): bool
    {
        return move_uploaded_file($tmpPath, $this->uploadDir . $storedName);
    }

    public function exists(string $storedName): bool
    {
        return file_exists($this->uploadDir . $storedName);
    }

    public function delete(string $storedName): bool
    {
        $path = $this->uploadDir . $storedName;
        if (!file_exists($path)) {
            return true;
        }

        return unlink($path);
    }

    public function getAbsolutePath(string $storedName): string
    {
        return $this->uploadDir . $storedName;
    }
}
