<?php

declare(strict_types=1);

namespace Resampler;

use Resampler\Enums\ResampleMethod;

class Utils
{
    public static function getQuality(?int $quality, int $min, int $max, int $default): int
    {
        if ($quality === null || $quality < $min || $quality > $max) {
            return $default;
        }

        return $quality;
    }

    /**
     * Get mime type in format IMAGETYPE_XXX from filename
     */
    public static function getTypeFromExtension(string $file): int|false
    {
        $file = \strtolower($file);
        $dotPos = \strrpos($file, '.');
        if ($dotPos === false) {
            return false;
        }

        return match (\substr($file, $dotPos + 1)) {
            'jpg', 'jpeg' => \IMAGETYPE_JPEG,
            'png' => \IMAGETYPE_PNG,
            'gif' => \IMAGETYPE_GIF,
            default => false,
        };
    }

    /**
     * Get information for copyimageresample function.
     *
     * @param int  $originWidth  Original width of image.
     * @param int  $originHeight Original height of image.
     * @param int  $maxWidth     Maximal width of image, may be lower in case $scaleUP is false.
     * @param int  $maxHeight    Maximal height of image, may be lower in case $scaleUP is false.
     * @param bool $scaleUp      If true, image will be scaled up, if it is smaller than requested size.
     */
    public static function getResizeParams(
        int $originWidth,
        int $originHeight,
        int $maxWidth,
        int $maxHeight,
        ResampleMethod $resampleType,
        ?bool $scaleUp = false,
    ): ResizeParams {

        $newSize = [
            'width' => $originWidth,
            'height' => $originHeight,
            'canvas-width' => $originWidth,
            'canvas-height' => $originHeight,
            'src-x' => 0,
            'src-y' => 0,
            'dst-x' => 0,
            'dst-y' => 0,
            'different' => false,
        ];
        $ratio = $originHeight / $originWidth;

        $isSameSize = $originWidth === $maxWidth && $originHeight === $maxHeight;
        $requiredIsBigger = $originWidth < $maxWidth && $originHeight < $maxHeight;
        // When same size is true, we don't care which Resample Method is used.
        // But when is required image is bigger in both dimensions, we terminate only on Resize type and no scale up.
        // Because if method is Crop or Rectangle, we can always fill space around image no matter $scaleUp param.
        if ($isSameSize || ($requiredIsBigger && $scaleUp === false && $resampleType === ResampleMethod::Resize)) {
            return ResizeParams::fromArray($newSize);
        }

        $newSize['different'] = true;

        $newSize['width'] = $maxWidth;
        $newSize['height'] = (int)\round($maxWidth * $ratio);
        $newSize['canvas-width'] = $maxWidth;
        $newSize['canvas-height'] = $maxHeight;

        if ($resampleType === ResampleMethod::Crop) {
            // Detect if we need to crop by other dimension
            if ($newSize['height'] < $maxHeight) {
                $newSize['height'] = $maxHeight;
                $newSize['width'] = (int)\round($maxHeight / $ratio);
            }
            if ($originWidth < $newSize['canvas-width'] || $originHeight < $newSize['canvas-height']) {
                if (!$scaleUp) {
                    $newSize['height'] = $originHeight;
                    $newSize['width'] = $originWidth;
                }
            }
            $newSize['dst-x'] = -(int)\round(($newSize['width'] - $newSize['canvas-width']) / 2);
            $newSize['dst-y'] = -(int)\round(($newSize['height'] - $newSize['canvas-height']) / 2);

            return ResizeParams::fromArray($newSize);
        }

        // Same for Resize and Rectangle
        if ($newSize['height'] > $maxHeight) {
            $newSize['height'] = $maxHeight;
            $newSize['width'] = (int)\round($maxHeight / $ratio);
        }

        if ($resampleType === ResampleMethod::Rectangle) {
            if ($originWidth <= $maxWidth && $originHeight <= $maxHeight && !$scaleUp) {
                $newSize['height'] = $originHeight;
                $newSize['width'] = $originWidth;
            }
            $newSize['dst-x'] = (int)\round(($newSize['canvas-width'] - $newSize['width']) / 2);
            $newSize['dst-y'] = (int)\round(($newSize['canvas-height'] - $newSize['height']) / 2);

            return ResizeParams::fromArray($newSize);
        }

        $newSize['canvas-width'] = $newSize['width'];
        $newSize['canvas-height'] = $newSize['height'];

        return ResizeParams::fromArray($newSize);
    }

    public static function getMemoryLimit(): int
    {
        $val = \trim(\ini_get('memory_limit'));
        $unit = \strtolower($val[\strlen($val) - 1]);
        $val = \intval($val);

        switch ($unit) {
            case 'g':
                $val *= 1024;
                // no break to multiply as many times as needed
            case 'm':
                $val *= 1024;
                // no break to multiply as many times as needed
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Check if there is enough space in memory for image with specified width, height and mime type.
     * If width, height or mime type is not specified, values from loaded image are used.
     *
     * All those values were observed by trial and tests.
     *
     * @param bool $saving Counting memory for saving, only extra space is needed.
     */
    public static function hasEnoughMemory(int $width, int $height, int $imgIntMimeType, bool $saving = false): bool
    {
        $limit = static::getMemoryLimit();
        // There is no memory limit
        if ($limit <= 0) {
            return true;
        }

        $memoryToUse = $width * $height;

        if ($saving) {
            // We need extra space for saving PNG
            // Space is same as during loading, but now we have allocated
            // memory for image resource from GD library. Only difference will be extra used
            if ($imgIntMimeType === \IMAGETYPE_PNG) {
                $memoryToUse *= 9.2 - 5.2;
            }
        } else {
            $memoryToUse *= match ($imgIntMimeType) {
                \IMAGETYPE_PNG => 9.2,
                \IMAGETYPE_GIF => 2.4,
                default => 5.2,
            };
        }

        return $memoryToUse <= $limit - \memory_get_usage(true);
    }
}
