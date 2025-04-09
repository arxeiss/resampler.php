<?php

declare(strict_types=1);

namespace Resampler\Exceptions;

class FileException extends \Resampler\Exceptions\Exception
{
    protected ?string $filePath;

    public function __construct(string $message, ?string $filePath = null)
    {
        parent::__construct($message . ': ' . $filePath);

        $this->filePath = $filePath;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
}
