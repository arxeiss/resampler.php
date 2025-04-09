<?php

declare(strict_types=1);

namespace Resampler;

final class ResizeParams
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly int $canvasWidth,
        public readonly int $canvasHeight,
        public readonly int $srcX,
        public readonly int $srcY,
        public readonly int $dstX,
        public readonly int $dstY,
        public readonly bool $isChanging,
    ) {
    }

    /**
     * @param array{
     *      width: int,
     *      height: int,
     *      canvas-width: int,
     *      canvas-height: int,
     *      src-x: int,
     *      src-y: int,
     *      dst-x: int,
     *      dst-y: int,
     *      different: bool
     * } $array
     * */
    public static function fromArray(array $array): static
    {
        return new static(
            $array['width'],
            $array['height'],
            $array['canvas-width'],
            $array['canvas-height'],
            $array['src-x'],
            $array['src-y'],
            $array['dst-x'],
            $array['dst-y'],
            $array['different'],
        );
    }
}
