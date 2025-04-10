<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Resampler\Enums\ResampleMethod;
use Resampler\ResizeParams;
use Resampler\Utils;

#[CoversClass(Utils::class)]
#[CoversClass(ResizeParams::class)]
final class UtilsTest extends TestCase
{
    /**
     * @return array<string, array{int|null, int, int, int, int}>
     */
    public static function qualityProvider(): array
    {
        return [
            'null value returns default' => [null, 1, 9, 8, 8],
            'value below min returns default' => [-1, 0, 100, 85, 85],
            'value above max returns default' => [101, 0, 100, 85, 85],
            'valid value returns same' => [50, 0, 100, 85, 50],
            'min boundary' => [0, 0, 100, 85, 0],
            'max boundary' => [100, 0, 100, 85, 100],
        ];
    }

    #[DataProvider('qualityProvider')]
    public function testGetQuality(?int $quality, int $min, int $max, int $default, int $expected): void
    {
        $result = Utils::getQuality($quality, $min, $max, $default);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, int|false}>
     */
    public static function typeFromExtensionProvider(): array
    {
        return [
            'jpg extension' => ['image.jpg', \IMAGETYPE_JPEG],
            'jpeg extension' => ['photo.jpeg', \IMAGETYPE_JPEG],
            'png extension' => ['icon.png', \IMAGETYPE_PNG],
            'gif extension' => ['animation.gif', \IMAGETYPE_GIF],
            'uppercase extension' => ['IMAGE.JPG', \IMAGETYPE_JPEG],
            'no extension' => ['filename', false],
            'empty string' => ['', false],
            'unsupported extension' => ['image.bmp', false],
            'multiple dots' => ['my.awesome.jpg', \IMAGETYPE_JPEG],
        ];
    }

    #[DataProvider('typeFromExtensionProvider')]
    public function testGetTypeFromExtension(string $filename, int|false $expected): void
    {
        $result = Utils::getTypeFromExtension($filename);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{int, int, int, int, ResampleMethod, bool, array<string, int|bool>}>
     */
    public static function resizeParamsProvider(): array
    {
        $sameSize = static fn (ResampleMethod $resampleType, bool $scaleUp) => [
            100,
            100,
            100,
            100,
            $resampleType,
            $scaleUp,
            [
                'width' => 100,
                'height' => 100,
                'canvas-width' => 100,
                'canvas-height' => 100,
                'src-x' => 0,
                'src-y' => 0,
                'dst-x' => 0,
                'dst-y' => 0,
                'different' => false,
            ],
        ];

        $cropRectangleNoScaleUpBiggerResult = static fn (ResampleMethod $resampleType) => [
            400,
            300,
            600,
            600,
            $resampleType,
            false,
            [
                'width' => 400,
                'height' => 300,
                'canvas-width' => 600,
                'canvas-height' => 600,
                'src-x' => 0,
                'src-y' => 0,
                'dst-x' => 100,
                'dst-y' => 150,
                'different' => true,
            ],
        ];

        return [
            'same size - no scale up - resize' => $sameSize(ResampleMethod::Resize, false),
            'same size - scale up - resize' => $sameSize(ResampleMethod::Resize, true),
            'same size - no scale up - crop' => $sameSize(ResampleMethod::Crop, false),
            'same size - scale up - crop' => $sameSize(ResampleMethod::Crop, true),
            'same size - no scale up - rectangle' => $sameSize(ResampleMethod::Rectangle, false),
            'same size - scale up - rectangle' => $sameSize(ResampleMethod::Rectangle, true),

            'resize - height will be smaller than wanted' => [
                2000,
                1000,
                300,
                20000,
                ResampleMethod::Resize,
                true,
                [
                    'width' => 300,
                    'height' => 150,
                    'canvas-width' => 300,
                    'canvas-height' => 150,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 0,
                    'dst-y' => 0,
                    'different' => true,
                ],
            ],
            'resize - width will be smaller than wanted' => [
                2000,
                1000,
                40000,
                100,
                ResampleMethod::Resize,
                true,
                [
                    'width' => 200,
                    'height' => 100,
                    'canvas-width' => 200,
                    'canvas-height' => 100,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 0,
                    'dst-y' => 0,
                    'different' => true,
                ],
            ],
            'resize scale up - height will be smaller than wanted' => [
                200,
                100,
                400,
                400,
                ResampleMethod::Resize,
                true,
                [
                    'width' => 400,
                    'height' => 200,
                    'canvas-width' => 400,
                    'canvas-height' => 200,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 0,
                    'dst-y' => 0,
                    'different' => true,
                ],
            ],
            'resize scale up - width will be smaller than wanted' => [
                100,
                200,
                400,
                400,
                ResampleMethod::Resize,
                true,
                [
                    'width' => 200,
                    'height' => 400,
                    'canvas-width' => 200,
                    'canvas-height' => 400,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 0,
                    'dst-y' => 0,
                    'different' => true,
                ],
            ],
            'resize - not different when scale up is false and bigger result is requested' => [
                100,
                100,
                200,
                200,
                ResampleMethod::Resize,
                false,
                [
                    'width' => 100,
                    'height' => 100,
                    'canvas-width' => 100,
                    'canvas-height' => 100,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 0,
                    'dst-y' => 0,
                    'different' => false,
                ],
            ],

            'crop - cutting x-axis' => [
                800,
                600,
                300,
                300,
                ResampleMethod::Crop,
                false,
                [
                    'width' => 400,
                    'height' => 300,
                    'canvas-width' => 300,
                    'canvas-height' => 300,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => -50,
                    'dst-y' => 0,
                    'different' => true,
                ],
            ],
            'crop - cutting y-axis' => [
                600,
                800,
                300,
                300,
                ResampleMethod::Crop,
                false,
                [
                    'width' => 300,
                    'height' => 400,
                    'canvas-width' => 300,
                    'canvas-height' => 300,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 0,
                    'dst-y' => -50,
                    'different' => true,
                ],
            ],
            'crop no scale up with smaller image' => $cropRectangleNoScaleUpBiggerResult(ResampleMethod::Crop),
            'crop scale up with smaller image - cutting x-axis' => [
                400,
                300,
                600,
                600,
                ResampleMethod::Crop,
                true,
                [
                    'width' => 800,
                    'height' => 600,
                    'canvas-width' => 600,
                    'canvas-height' => 600,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => -100,
                    'dst-y' => 0,
                    'different' => true,
                ],
            ],
            'crop scale up with smaller image - cutting y-axis' => [
                200,
                400,
                600,
                600,
                ResampleMethod::Crop,
                true,
                [
                    'width' => 600,
                    'height' => 1200,
                    'canvas-width' => 600,
                    'canvas-height' => 600,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 0,
                    'dst-y' => -300,
                    'different' => true,
                ],
            ],

            'rectangle - space on y-axis' => [
                800,
                600,
                300,
                300,
                ResampleMethod::Rectangle,
                false,
                [
                    'width' => 300,
                    'height' => 225,
                    'canvas-width' => 300,
                    'canvas-height' => 300,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 0,
                    'dst-y' => 38,
                    'different' => true,
                ],
            ],
            'rectangle - space on x-axis' => [
                600,
                800,
                300,
                300,
                ResampleMethod::Rectangle,
                false,
                [
                    'width' => 225,
                    'height' => 300,
                    'canvas-width' => 300,
                    'canvas-height' => 300,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 38,
                    'dst-y' => 0,
                    'different' => true,
                ],
            ],

            'rectangle no scale up with smaller image' => $cropRectangleNoScaleUpBiggerResult(
                ResampleMethod::Rectangle,
            ),

            'rectangle scale up with smaller image - space on y-axis' => [
                400,
                300,
                600,
                600,
                ResampleMethod::Rectangle,
                true,
                [
                    'width' => 600,
                    'height' => 450,
                    'canvas-width' => 600,
                    'canvas-height' => 600,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 0,
                    'dst-y' => 75,
                    'different' => true,
                ],
            ],
            'rectangle scale up with smaller image - space on x-axis' => [
                200,
                400,
                600,
                600,
                ResampleMethod::Rectangle,
                true,
                [
                    'width' => 300,
                    'height' => 600,
                    'canvas-width' => 600,
                    'canvas-height' => 600,
                    'src-x' => 0,
                    'src-y' => 0,
                    'dst-x' => 150,
                    'dst-y' => 0,
                    'different' => true,
                ],
            ],
        ];
    }

    /**
     * @param array<string, int> $expected
     */
    #[DataProvider('resizeParamsProvider')]
    public function testGetResizeParams(
        int $originWidth,
        int $originHeight,
        int $maxWidth,
        int $maxHeight,
        ResampleMethod $resampleType,
        bool $scaleUp,
        array $expected,
    ): void {
        $result = Utils::getResizeParams($originWidth, $originHeight, $maxWidth, $maxHeight, $resampleType, $scaleUp);

        $this->assertInstanceOf(ResizeParams::class, $result);
        $this->assertSame($expected['width'], $result->width, 'width');
        $this->assertSame($expected['height'], $result->height, 'height');
        $this->assertSame($expected['canvas-width'], $result->canvasWidth, 'canvasWidth');
        $this->assertSame($expected['canvas-height'], $result->canvasHeight, 'canvasHeight');
        $this->assertSame($expected['src-x'], $result->srcX, 'srcX');
        $this->assertSame($expected['src-y'], $result->srcY, 'srcY');
        $this->assertSame($expected['dst-x'], $result->dstX, 'dstX');
        $this->assertSame($expected['dst-y'], $result->dstY, 'dstY');
        $this->assertSame($expected['different'], $result->isChanging, 'isChanging');
    }

    /**
     * @return array<string, array{string|int, int}>
     */
    public static function memoryLimitProvider(): array
    {
        // If an ini_set call or a direct PHP INI directive attempts to set a memory_limit value smaller than 2 MB,
        // PHP now rejects this value, and emits a PHP warning.
        return [
            'bytes' => ['150000000', 150000000],
            'kilobytes' => ['128000K', 128000 * 1024],
            'megabytes' => ['128M', 128 * 1024 * 1024],
            'gigabytes' => ['2G', 2 * 1024 * 1024 * 1024],
            'lowercase suffix' => ['128m', 128 * 1024 * 1024],
        ];
    }

    #[DataProvider('memoryLimitProvider')]
    public function testGetMemoryLimit(string|int $iniValue, int $expected): void
    {
        $originalValue = \ini_get('memory_limit');
        \ini_set('memory_limit', $iniValue);

        try {
            $result = Utils::getMemoryLimit();
            $this->assertSame($expected, $result);
        } finally {
            \ini_set('memory_limit', $originalValue);
        }
    }

    /**
     * @return array<string, array{int, int, int, bool, int}>
     */
    public static function memoryCheckProvider(): array
    {
        return [
            'jpeg image' => [3000, 2000, \IMAGETYPE_JPEG, false, 31_200_000],
            'png image' => [1500, 1500, \IMAGETYPE_PNG, false, 20_700_000],
            'gif image' => [3000, 2000, \IMAGETYPE_GIF, false, 14_400_000],
            'saving png' => [1800, 1800, \IMAGETYPE_PNG, true, 12_960_000],
        ];
    }

    #[DataProvider('memoryCheckProvider')]
    public function testHasEnoughMemory(
        int $width,
        int $height,
        int $mimeType,
        bool $saving,
        int $expectedMemUsage,
    ): void {
        // Set a fixed memory limit for testing
        $originalLimit = \ini_get('memory_limit');
        $offset = 100_000;

        try {
            // More than required
            \ini_set('memory_limit', $expectedMemUsage + \memory_get_usage(true) + $offset);
            $this->assertTrue(Utils::hasEnoughMemory($width, $height, $mimeType, $saving));

            // Less than required
            \ini_set('memory_limit', $expectedMemUsage + \memory_get_usage(true) - $offset);
            $this->assertFalse(Utils::hasEnoughMemory($width, $height, $mimeType, $saving));
        } finally {
            \ini_set('memory_limit', $originalLimit);
        }
    }

    public function testUnlimitedMemoryAlwaysReturnsTrue(): void
    {
        $originalLimit = \ini_get('memory_limit');
        \ini_set('memory_limit', '-1');

        try {
            $result = Utils::hasEnoughMemory(99999, 99999, \IMAGETYPE_PNG, false);
            $this->assertTrue($result);
        } finally {
            \ini_set('memory_limit', $originalLimit);
        }
    }
}
