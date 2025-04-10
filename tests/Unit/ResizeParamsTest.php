<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Resampler\ResizeParams;

#[CoversClass(ResizeParams::class)]
final class ResizeParamsTest extends TestCase
{
    public function testConstructor(): void
    {
        $resizeParams = new ResizeParams(10, 20, 30, 40, 50, 60, 70, 80, true);

        $this->assertSame(10, $resizeParams->width);
        $this->assertSame(20, $resizeParams->height);
        $this->assertSame(30, $resizeParams->canvasWidth);
        $this->assertSame(40, $resizeParams->canvasHeight);
        $this->assertSame(50, $resizeParams->srcX);
        $this->assertSame(60, $resizeParams->srcY);
        $this->assertSame(70, $resizeParams->dstX);
        $this->assertSame(80, $resizeParams->dstY);
        $this->assertTrue($resizeParams->isChanging);
    }

    public function testFromArray(): void
    {
        $resizeParams = ResizeParams::fromArray([
            'width' => 80,
            'height' => 100,
            'canvas-width' => 50,
            'canvas-height' => 60,
            'src-x' => 40,
            'src-y' => -20,
            'dst-x' => -15,
            'dst-y' => 10,
            'different' => false,
        ]);

        $this->assertSame(80, $resizeParams->width);
        $this->assertSame(100, $resizeParams->height);
        $this->assertSame(50, $resizeParams->canvasWidth);
        $this->assertSame(60, $resizeParams->canvasHeight);
        $this->assertSame(40, $resizeParams->srcX);
        $this->assertSame(-20, $resizeParams->srcY);
        $this->assertSame(-15, $resizeParams->dstX);
        $this->assertSame(10, $resizeParams->dstY);
        $this->assertFalse($resizeParams->isChanging);
    }

    public function testReadOnlyProperties(): void
    {
        $resizeParams = new ResizeParams(100, 100, 100, 100, 0, 0, 0, 0, false);

        $this->expectException(\Error::class);
        /** @phpstan-ignore-next-line */
        $resizeParams->width = 200;
    }

    public function testObjectImmutability(): void
    {
        $params = [
            'width' => 100,
            'height' => 100,
            'canvas-width' => 100,
            'canvas-height' => 100,
            'src-x' => 0,
            'src-y' => 0,
            'dst-x' => 0,
            'dst-y' => 0,
            'different' => false,
        ];

        $resizeParams = ResizeParams::fromArray($params);

        // Modify the original array
        $params['width'] = 200;

        // Verify the object's properties remain unchanged
        $this->assertSame(100, $resizeParams->width);
    }
}
