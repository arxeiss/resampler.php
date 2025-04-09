<?php

declare(strict_types=1);

namespace Resampler\Enums;

enum ResampleMethod
{
    /**
     * Use resize/resample for size changing.
     */
    case Resize;

    /**
     * Use crop for size changing.
     */
    case Crop;

    /**
     * Make rectangle and origin image put inside.
     */
    case Rectangle;
}
