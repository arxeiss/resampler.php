<?php

declare(strict_types=1);

namespace Resampler\Enums;

enum Flip: int
{
    /**
     * No flip.
     */
    case None = 0;

    /**
     * Flip horizontally.
     */
    case Horizontal = \IMG_FLIP_HORIZONTAL;

    /**
     * Flip vertically.
     */
    case Vertical = \IMG_FLIP_VERTICAL;

    /**
     * Flip both horizontally and vertically.
     */
    case Both = \IMG_FLIP_BOTH;
}
