<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Resampler\Color;

#[CoversClass(Color::class)]
final class ColorTest extends TestCase
{
    public function testDefaultConstructor(): void
    {
        $color = new Color();

        $this->assertSame(0xff, $color->getR());
        $this->assertSame(0xff, $color->getG());
        $this->assertSame(0xff, $color->getB());
        $this->assertSame(127, $color->getA());
    }

    public function testTransparentColor(): void
    {
        $color = new Color('transparent');

        $this->assertSame(255, $color->getR());
        $this->assertSame(255, $color->getG());
        $this->assertSame(255, $color->getB());
        $this->assertSame(127, $color->getA());
    }

    /**
     * @return array<string, array{string, int, int, int, int}>
     */
    public static function validHexColorsProvider(): array
    {
        return [
            'RGB format' => ['#fff', 255, 255, 255, 0],
            'RGB black' => ['#000', 0, 0, 0, 0],
            'RRGGBB format' => ['#ffffff', 255, 255, 255, 0],
            'RRGGBB black' => ['#000000', 0, 0, 0, 0],
            'RRGGBBAA format' => ['#ffffffaa', 255, 255, 255, 85],
            'Mixed case' => ['#AbCdEf', 171, 205, 239, 0],
            'With alpha' => ['#ff00ff80', 255, 0, 255, 64],
        ];
    }

    #[DataProvider('validHexColorsProvider')]
    public function testValidHexColors(string $hex, int $r, int $g, int $b, int $a): void
    {
        $color = new Color($hex);

        $this->assertSame($r, $color->getR());
        $this->assertSame($g, $color->getG());
        $this->assertSame($b, $color->getB());
        $this->assertSame($a, $color->getA());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHexColorsProvider(): array
    {
        return [
            'Empty string' => [''],
            'Invalid format' => ['#12'],
            'Wrong prefix' => ['12345'],
            'Too long' => ['#1234567890'],
            'Invalid chars' => ['#xyz'],
            'Invalid length' => ['#12345'],
        ];
    }

    #[DataProvider('invalidHexColorsProvider')]
    public function testInvalidHexColors(string $hex): void
    {
        $this->expectException(\Resampler\Exceptions\Exception::class);
        $this->expectExceptionMessage('Color ' . $hex . ' is in bad format');

        new Color($hex);
    }

    public function testSetHexChaining(): void
    {
        $color = new Color();
        $result = $color->setHex('#fff');

        $this->assertSame($color, $result);
    }

    public function testSetHexTransparent(): void
    {
        $color = new Color('#000');
        $color->setHex('transparent');

        $this->assertSame(255, $color->getR());
        $this->assertSame(255, $color->getG());
        $this->assertSame(255, $color->getB());
        $this->assertSame(127, $color->getA());
    }

    #[DataProvider('validHexColorsProvider')]
    public function testSetHexColors(string $hex, int $r, int $g, int $b, int $a): void
    {
        $color = new Color($hex);

        $this->assertSame($r, $color->getR());
        $this->assertSame($g, $color->getG());
        $this->assertSame($b, $color->getB());
        $this->assertSame($a, $color->getA());
    }

    public function testConstructorNullValue(): void
    {
        $color = new Color(null);

        $this->assertSame(0xff, $color->getR());
        $this->assertSame(0xff, $color->getG());
        $this->assertSame(0xff, $color->getB());
        $this->assertSame(127, $color->getA());
    }
}
