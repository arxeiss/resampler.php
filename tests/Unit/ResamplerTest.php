<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Resampler\Color;
use Resampler\Enums\Rotate;
use Resampler\Exceptions\FileException;
use Resampler\Resampler;
use Resampler\ResizeParams;
use Resampler\Utils;

#[CoversClass(Resampler::class)]
#[CoversClass(Utils::class)]
#[CoversClass(Color::class)]
#[CoversClass(FileException::class)]
#[CoversClass(ResizeParams::class)]
final class ResamplerTest extends TestCase
{
    public function testLoadWithNonExistingFile(): void
    {
        $path = __DIR__ . '/../data/non-existing.jpg';
        try {
            Resampler::load($path);

            $this->fail('Expected FileException was not thrown');
        } catch (\Resampler\Exceptions\FileException $e) {
            $this->assertSame($path, $e->getFilePath());
            $this->assertSame("Can't read file: " . $path, $e->getMessage());
        }
    }

    public function testLoadWithNonImageFile(): void
    {
        $path = __DIR__ . '/../data/non-image.txt';
        try {
            Resampler::load($path);

            $this->fail('Expected FileException was not thrown');
        } catch (\Resampler\Exceptions\FileException $e) {
            $this->assertSame($path, $e->getFilePath());
            $this->assertSame('Image can be only PNG, GIF or JPEG: ' . $path, $e->getMessage());
        }
    }

    public function testLoadWithNonImageFileWithPngSuffix(): void
    {
        $path = __DIR__ . '/../data/non-image.png';
        try {
            Resampler::load($path);

            $this->fail('Expected FileException was not thrown');
        } catch (\Resampler\Exceptions\FileException $e) {
            $this->assertSame($path, $e->getFilePath());
            $this->assertSame('Image can be only PNG, GIF or JPEG: ' . $path, $e->getMessage());
        }
    }

    public function testLoadAndMemoryChecks(): void
    {
        $path = __DIR__ . '/../data/ian-keefe-unsplash.jpg';
        $this->assertNotFalse(\ini_set('memory_limit', '15M'));

        // By default, memory checks are disabled unless GD is bundled. Which is not true for Github Actions
        $r = Resampler::load($path);
        $this->assertInstanceOf(Resampler::class, $r);
        $r->loadToMemory();
        $this->assertEquals($r->getWidth(), 1920);
        $this->assertEquals($r->getHeight(), 2880);
        $this->assertEquals($r->getMimeType(), 'image/jpeg');

        try {
            // Let's disable and try again
            $r->disableMemoryCheck(false);
            $r->loadToMemory(true);
            $this->fail('Expected OutOfMemoryException was not thrown');
        } catch (\Resampler\Exceptions\OutOfMemoryException $e) {
            $this->assertSame("Cannot load image to memory, there isn't enough space", $e->getMessage());
        }

        // Verify it is OK when again disabled
        $r->releaseMemory()->disableMemoryCheck(true)->loadToMemory()->releaseMemory();
    }

    public function testCheckMemoryAtThumbCreation(): void
    {
        $path = __DIR__ . '/../data/ian-keefe-unsplash.jpg';
        $this->assertNotFalse(\ini_set('memory_limit', '15M'));

        // By default, memory checks are disabled unless GD is bundled. Which is not true for Github Actions
        $r = Resampler::load($path)
            ->loadToMemory()
            ->disableMemoryCheck(false);

        try {
            $r->resize(10000, 10000, true);
            $this->fail('Expected OutOfMemoryException was not thrown');
        } catch (\Resampler\Exceptions\OutOfMemoryException $e) {
            $this->assertSame("Cannot create canvas for resampling, there isn't enough free memory", $e->getMessage());
        }

        try {
            $r->rotate(Rotate::DEG_90_CW);
            $this->fail('Expected OutOfMemoryException was not thrown');
        } catch (\Resampler\Exceptions\OutOfMemoryException $e) {
            $this->assertSame("Cannot create canvas for rotation, there isn't enough free memory", $e->getMessage());
        }
    }

    public function testNotPossibleToWriteToFile(): void
    {
        $path = __DIR__ . '/../data/ian-keefe-unsplash.jpg';
        try {
            Resampler::load($path)->save('./non/existing/folder/');

            $this->fail('Expected FileException was not thrown');
        } catch (\Resampler\Exceptions\FileException $e) {
            $this->assertSame('./non/existing/folder/ian-keefe-unsplash.jpg', $e->getFilePath());
            $this->assertSame(
                'Can\'t write into file: ./non/existing/folder/ian-keefe-unsplash.jpg',
                $e->getMessage(),
            );
        }
    }

    public function testInvalidImageExtensionOnOutput(): void
    {
        $path = __DIR__ . '/../data/ian-keefe-unsplash.jpg';
        try {
            Resampler::load($path)->output('bmp');

            $this->fail('Expected Exception was not thrown');
        } catch (\Resampler\Exceptions\Exception $e) {
            $this->assertSame('Unsupported image extension', $e->getMessage());
        }
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function pathProvider(): array
    {
        return [
            'empty' => ['ian-keefe-unsplash.jpg', null, __DIR__ . '/../data/ian-keefe-unsplash.jpg'],
            'empty with slash' => ['ian-keefe-unsplash.jpg', '/var/www/', '/var/www/ian-keefe-unsplash.jpg'],
            'empty with backslash' => ['ian-keefe-unsplash.jpg', 'C:\xyz\\', 'C:\xyz\ian-keefe-unsplash.jpg'],
            'without jpg suffix' => ['ian-keefe-unsplash.jpg', '/var/www/output', '/var/www/output.jpg'],
            'without png suffix' => ['transparent.png', '../../icon', '../../icon.png'],
            'without gif suffix' => ['gif.gif', 'test', 'test.gif'],
        ];
    }

    #[DataProvider('pathProvider')]
    public function testGetPathSuffix(string $path, ?string $newPath, string $expected): void
    {
        $path = __DIR__ . '/../data/' . $path;
        $this->assertSame($expected, Resampler::load($path)->getPathWithSuffix($newPath));
    }
}
