<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Resampler\Color;
use Resampler\Resampler;
use Resampler\ResizeParams;
use Resampler\Utils;
use Spatie\Snapshots\MatchesSnapshots;

#[CoversClass(Resampler::class)]
#[CoversClass(Utils::class)]
#[CoversClass(Color::class)]
#[CoversClass(ResizeParams::class)]
final class ResamplerTest extends TestCase
{
    use MatchesSnapshots;

    /**
     * @return array<string, array{string, string}>
     */
    public static function exifProvider(): array
    {
        return [
            '1-0deg.jpg' => ['1-0deg.jpg', '1-0deg.jpg'],
            '3-180deg.jpg' => ['3-180deg.jpg', '3-180deg.jpg'],
            '6-90degCCW.jpg' => ['6-90degCCW.jpg', '6-90degCCW.jpg'],
            '8-90-deg.jpg' => ['8-90-deg.jpg', '8-90-deg.jpg'],
        ];
    }

    #[DataProvider('exifProvider')]
    public function testExifOrientation(string $source, string $expected): void
    {
        $expected = __DIR__ . '/../output/exif-rotation_' . $expected;
        Resampler::load(__DIR__ . '/../data/rotation/favicon-o' . $source, true)
            ->save($expected);

        $this->assertMatchesFileSnapshot($expected);
    }

    public function testRotateByExifWithNewCanvasReturned(): void
    {
        $pathBefore = __DIR__ . '/../output/before-rotate-by-exif.jpg';
        $pathAfter = __DIR__ . '/../output/after-rotate-by-exif.jpg';

        $r = Resampler::load(__DIR__ . '/../data/josh-hild-unsplash.jpg');

        $rNewCanvas = $r->rotateByExif(true);
        $r->save($pathBefore, 10);

        $this->assertSame($r->getHeight(), 640);
        $this->assertSame($r->getWidth(), 800);
        $this->assertMatchesFileSnapshot($pathBefore);

        $this->assertSame($rNewCanvas->getHeight(), 800);
        $this->assertSame($rNewCanvas->getWidth(), 640);
        $rNewCanvas->save($pathAfter, 200000);
        $this->assertMatchesFileSnapshot($pathAfter);

        // More rotations by exif does nothing
        for ($i = 0; $i < 5; $i += 1) {
            $rNewCanvas->rotateByExif();
            $this->assertSame($rNewCanvas->getHeight(), 800);
            $this->assertSame($rNewCanvas->getWidth(), 640);
        }
    }

    public function testResize(): void
    {
        \ob_start();
        Resampler::load(__DIR__ . '/../data/josh-hild-unsplash.jpg')
            ->resize(200, 200)
            ->output('jpg');

        $path = __DIR__ . '/../output/resize.jpg';
        \file_put_contents($path, \ob_get_clean());
        $this->assertMatchesFileSnapshot($path);
    }

    public function testResizeScaleUp(): void
    {
        \ob_start();
        Resampler::load(__DIR__ . '/../data/transparent.png')
            ->resize(200, 300, true)
            ->output('png');

        $path = __DIR__ . '/../output/resize-scale-up.png';
        \file_put_contents($path, \ob_get_clean());
        $this->assertMatchesFileSnapshot($path);
    }

    /**
     * @return array<string, array{string, string, int, int, bool}>
     */
    public static function cropProvider(): array
    {
        return [
            'crop no changing' => ['josh-hild-unsplash.jpg', 'crop-no-changes.jpg', 640, 800, false],
            'crop' => ['josh-hild-unsplash.jpg', 'crop.jpg', 400, 300, false],
            'crop scale up' => ['transparent.png', 'crop-scale-up.jpg', 200, 300, true],
            'crop no scale up' => ['transparent.png', 'crop-no-scale-up.png', 200, 300, false],
        ];
    }

    #[DataProvider('cropProvider')]
    public function testCrop(string $in, string $out, int $w, int $h, bool $scaleUp): void
    {
        Resampler::load(__DIR__ . '/../data/' . $in, true)
            ->crop($w, $h, $scaleUp)
            ->save(__DIR__ . '/../output/' . $out);
        $this->assertMatchesFileSnapshot(__DIR__ . '/../output/' . $out);
    }

    /**
     * @return array<string, array{string, string, int, int, bool}>
     */
    public static function rectangleProvider(): array
    {
        return [
            'rectangle' => ['ian-keefe-unsplash.jpg', 'rectangle.jpg', 500, 300, false],
            'rectangle scale up' => ['transparent.png', 'rectangle-scale-up.png', 200, 300, true],
            'rectangle no scale up' => ['gif.gif', 'rectangle-no-scale-up.gif', 700, 800, false],
        ];
    }

    #[DataProvider('rectangleProvider')]
    public function testRectangle(string $in, string $out, int $w, int $h, bool $scaleUp): void
    {
        Resampler::load(__DIR__ . '/../data/' . $in)
            ->setBackgroundColor(new Color('#ff555544'))
            ->rectangle($w, $h, $scaleUp)
            ->save(__DIR__ . '/../output/' . $out);
        $this->assertMatchesFileSnapshot(__DIR__ . '/../output/' . $out);
    }
}
