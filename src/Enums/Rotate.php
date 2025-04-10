<?php

declare(strict_types=1);

namespace Resampler\Enums;

enum Rotate: int
{
    /**
     * No rotation.
     */
    case DEG_0 = 0;

    /**
     * Rotate by 90 degrees clockwise. PHP rotate image by anticlockwise, so this is negative value.
     */
    case DEG_90_CW = -90;

    /**
     * Rotate by 90 degrees counterclockwise. PHP rotate image by anticlockwise, so this is positive value.
     */
    case DEG_90_CCW = 90;

    /**
     * Rotate by 180 degrees.
     */
    case DEG_180 = 180;
}
