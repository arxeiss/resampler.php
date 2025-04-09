<?php

declare(strict_types=1);

namespace Resampler;

use finfo;
use GdImage;
use Resampler\Enums\ResampleMethod;
use Resampler\Enums\Rotate;

class Resampler
{
    protected int $width;
    protected int $height;
    protected string $mimeType;
    protected string $file;
    protected ?GdImage $imgResource = null;
    protected Color $bgColor;
    protected Rotate $exifRotation = Rotate::DEG_0;
    protected bool $disableMemoryCheck = false;

    /**
     * Mime type in constant IMAGETYPE_xxx.
     */
    protected int $mimeTypeConstant;

    final protected function __construct(string $file, ?GdImage $resource = null)
    {
        $this->file = $file;
        $this->bgColor = new Color();
        $this->imgResource = $resource;

        if ($resource !== null) {
            return;
        }
        if (!\is_readable($file)) {
            throw new \Resampler\Exceptions\FileException("Can't read file", $file);
        }
        $this->loadImageData();
    }

    public function __destruct()
    {
        $this->releaseMemory();
    }

    /**
     * Create new object and load information about it.
     *
     * @param bool $rotateByExif If true, image will be rotated directly after load by EXIF data.
     *    See also @see rotateByExif method to rotate it later, after downscale for example to save memory.
     */
    public static function load(string $file, bool $rotateByExif = false): static
    {
        if (!\extension_loaded('gd') || !\extension_loaded('fileinfo')) {
            throw new \Resampler\Exceptions\Exception('GD and Fileinfo extensions are required, but not available');
        }

        $r = new static($file);

        if ($rotateByExif) {
            $r->rotateByExif();
        }

        return $r;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function disableMemoryCheck(bool $disable = true): static
    {
        $this->disableMemoryCheck = $disable;

        return $this;
    }

    protected function loadImageData(): void
    {
        $f = new finfo();
        if (!\in_array($f->file($this->file, \FILEINFO_MIME_TYPE), ['image/jpeg', 'image/png', 'image/gif'], true)) {
            throw new \Resampler\Exceptions\FileException('Image can be only PNG, GIF or JPEG', $this->file);
        }
        //phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- hard to verify before calling this function
        $info = @\getimagesize($this->file);
        if ($info === false) {
            throw new \Resampler\Exceptions\FileException("Can't read file as image", $this->file);
        }
        [
            0 => $this->width,
            1 => $this->height,
            2 => $this->mimeTypeConstant,
            'mime' => $this->mimeType,
        ] = $info;

        // GD Version can look like 'bundled (2.1.0 compatible)' or '2.3.3'
        $gdInfo = \gd_info();
        $this->disableMemoryCheck(!\str_contains($gdInfo['GD Version'] ?? '', 'bundled'));

        // Check presence of functions to read EXIF data. When not present, the function itself shouldn't be available
        if (!\function_exists('exif_read_data')) {
            return;
        }
        //phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- hard to verify before calling this function
        $data = @\exif_read_data($this->file);
        if ($data === false) {
            return;
        }
        $this->exifRotation = match ($data['Orientation'] ?? 0) {
            3 => Rotate::DEG_180,
            6 => Rotate::DEG_90_CW, // It is rotated 90 CCW, so we need now 90 CW to put back to normal state
            8 => Rotate::DEG_90_CCW,
            default => Rotate::DEG_0,
        };
    }

    /**
     * Load image content to memory, if isn't.
     *
     * @param bool $force Load again from original file and discard previous.
     */
    public function loadToMemory(bool $force = false): static
    {
        if ($this->imgResource !== null && $force) {
            $this->releaseMemory();
        }

        if ($this->imgResource !== null) {
            return $this;
        }

        if (!$this->availableMemory()) {
            throw new \Resampler\Exceptions\OutOfMemoryException(
                "Cannot load image to memory, there isn't enough space",
            );
        }

        $this->loadImageData();

        $img = match ($this->mimeTypeConstant) {
            \IMAGETYPE_PNG => \imagecreatefrompng($this->file),
            \IMAGETYPE_GIF => \imagecreatefromgif($this->file),
            \IMAGETYPE_JPEG, \IMAGETYPE_JPEG2000 => \imagecreatefromjpeg($this->file),
            default => false,
        };
        if ($img === false) {
            throw new \Resampler\Exceptions\OutOfMemoryException('Cannot load image to memory');
        }

        $this->imgResource = $img;

        // Enable saving alpha channel, just to be sure
        \imagealphablending($this->imgResource, true);
        \imagesavealpha($this->imgResource, true);

        return $this;
    }

    protected function availableMemory(
        ?int $width = null,
        ?int $height = null,
        ?int $imgIntMimeType = null,
        bool $saving = false,
    ): bool {
        if ($this->disableMemoryCheck) {
            return true;
        }

        return Utils::hasEnoughMemory(
            $width ?? $this->width,
            $height ?? $this->height,
            $imgIntMimeType ?? $this->mimeTypeConstant,
            $saving,
        );
    }

    /**
     * Explicitly release image resource from memory. This is called also from destructor.
     */
    public function releaseMemory(): static
    {
        if ($this->imgResource !== null) {
            \imagedestroy($this->imgResource);
            $this->imgResource = null;
        }

        return $this;
    }

    protected function createTmbImg(int $width, int $height): GdImage
    {
        // ImageCreateTrueColor will use the same amount of memory as loading JPEG
        if (!$this->availableMemory($width, $height, \IMAGETYPE_JPEG)) {
            throw new \Resampler\Exceptions\OutOfMemoryException(
                "Cannot create canvas for resampling, there isn't enough free memory",
            );
        }
        $img = \imagecreatetruecolor($width, $height);

        if ($img === false) {
            throw new \Resampler\Exceptions\OutOfMemoryException('Unable to allocate place for thumb canvas');
        }
        \imagealphablending($img, false);
        \imagesavealpha($img, true);
        $bg  = \imagecolorallocatealpha(
            $img,
            $this->bgColor->getR(),
            $this->bgColor->getG(),
            $this->bgColor->getB(),
            $this->bgColor->getA(),
        );
        if ($bg === false) {
            throw new \Resampler\Exceptions\Exception('Unable to allocate color for background');
        }
        \imagefilledrectangle($img, 0, 0, $width, $height, $bg);
        \imagealphablending($img, true);

        return $img;
    }

    public function setBackgroundColor(Color $color): static
    {
        $this->bgColor = $color;

        return $this;
    }

    /**
     * Resize picture to maximum width and height.
     * Result image might be smaller in one dimension, when source image doesn't have same ratio as requested one.
     *
     * @param int  $maxWidth        Maximum width of final image.
     * @param int  $maxHeight       Maximum height of final image.
     * @param bool $scaleUp         Enable scaling up, if source image is smaller. Otherwise source image is returned.
     * @param bool $returnNewCanvas If true, new instance of Resampler is returned and original can be used again.
     */
    public function resize(int $maxWidth, int $maxHeight, bool $scaleUp = false, bool $returnNewCanvas = false): static
    {
        return $this->resample(ResampleMethod::Resize, $maxWidth, $maxHeight, $scaleUp, $returnNewCanvas);
    }

    /**
     * Crop picture to given width and height. Result image will be always of requested size,
     * and source image will try to cover whole area. Some parts might be cut, when ratio is different.
     *
     * When source image is smaller, it will be centered in final image. Or scaled up, based on $scaleUp param.
     * The empty space around source image in final image is filled by background color, @see setBackgroundColor().
     *
     * @param int  $width           Width of final image.
     * @param int  $height          Height of final image.
     * @param bool $scaleUp         Enable scaling up, if source image is smaller. Otherwise source image is centered.
     * @param bool $returnNewCanvas If true, new instance of Resampler is returned and original can be used again.
     */
    public function crop(int $width, int $height, bool $scaleUp = false, bool $returnNewCanvas = false,): static
    {
        return $this->resample(ResampleMethod::Crop, $width, $height, $scaleUp, $returnNewCanvas);
    }

    /**
     * Create rectangle and put source image to the center. Result image will be always of requested size,
     * and source image will try to fit inside. There might be empty spaces, when ratio is different.
     *
     * When source image is smaller or ratio doesn't match, it will be centered in final image.
     * The empty space around source image in final image is filled by background color, @see setBackgroundColor().
     *
     * @param int  $width           Width of final image.
     * @param int  $height          Height of final image.
     * @param bool $scaleUp         Enable scaling up, if source image is smaller. Otherwise source image is centered.
     * @param bool $returnNewCanvas If true, new instance of Resampler is returned and original can be used again.
     */
    public function rectangle(int $width, int $height, bool $scaleUp = false, bool $returnNewCanvas = false,): static
    {
        return $this->resample(ResampleMethod::Rectangle, $width, $height, $scaleUp, $returnNewCanvas);
    }

    protected function resample(
        ResampleMethod $type,
        int $width,
        int $height,
        bool $scaleUp = false,
        bool $returnNewCanvas = false,
    ): static {
        $newSize = Utils::getResizeParams($this->width, $this->height, $width, $height, $type, $scaleUp);
        if (!$newSize->isChanging) {
            return $this;
        }

        $this->loadToMemory();
        $tmb = $this->createTmbImg($newSize->canvasWidth, $newSize->canvasHeight);

        \imagecopyresampled(
            $tmb,
            $this->imgResource, //@phpstan-ignore-line argument.type (After loadToMemory() it will never be null)
            $newSize->dstX,
            $newSize->dstY,
            $newSize->srcX,
            $newSize->srcY,
            $newSize->width,
            $newSize->height,
            $this->width,
            $this->height,
        );

        return $this->handleTmb($tmb, $newSize->canvasWidth, $newSize->canvasHeight, $returnNewCanvas);
    }

    /**
     * Rotate canvas with given angle.
     *
     * @param bool $returnNewCanvas If true, new instance of Resampler is returned and original can be used again.
     */
    public function rotate(Rotate $angle, bool $returnNewCanvas = false): static
    {
        if ($angle === Rotate::DEG_0) {
            return $this;
        }

        $this->loadToMemory();
        if (!$this->availableMemory($this->width, $this->height, \IMAGETYPE_JPEG)) {
            throw new \Resampler\Exceptions\OutOfMemoryException(
                "Cannot create canvas for rotation, there isn't enough free memory",
            );
        }

        $tmb = \imagerotate(
            $this->imgResource, //@phpstan-ignore-line argument.type (After loadToMemory() it will never be null)
            $angle->value,
            \imagecolorallocatealpha(
                $this->imgResource, //@phpstan-ignore-line argument.type (After loadToMemory() it will never be null)
                $this->bgColor->getR(),
                $this->bgColor->getG(),
                $this->bgColor->getB(),
                $this->bgColor->getA(),
            ),
        );
        if ($tmb === false) {
            throw new \Resampler\Exceptions\Exception('Unable to rotate image');
        }
        \imagealphablending($tmb, true);
        \imagesavealpha($tmb, true);

        [$width, $height] = match ($angle) {
            Rotate::DEG_90_CW, Rotate::DEG_90_CCW => [$this->height, $this->width],
            default => [$this->width, $this->height],
        };

        return $this->handleTmb($tmb, $width, $height, $returnNewCanvas);
    }

    protected function handleTmb(GdImage $tmb, int $width, int $height, bool $returnNewCanvas): static
    {
        $returnObject = $this;
        if ($returnNewCanvas) {
            $returnObject = new static($this->file, $tmb);
            $returnObject->mimeType = $this->mimeType;
            $returnObject->mimeTypeConstant = $this->mimeTypeConstant;
            $returnObject->bgColor = clone $this->bgColor;
        }

        $returnObject->width = $width;
        $returnObject->height = $height;

        if (!$returnNewCanvas) {
            $returnObject->releaseMemory();
            $returnObject->imgResource = $tmb;
        }

        return $returnObject;
    }

    /**
     * Rotate canvas with angle from EXIF data.
     *
     * @param bool $returnNewCanvas If true, new instance of Resampler is returned and original can be used again.
     */
    public function rotateByExif(bool $returnNewCanvas = false): static
    {
        $r = $this->rotate($this->exifRotation, $returnNewCanvas);
        // Reset to zero, so in case of multiple calls, it is not rotated again.
        $this->exifRotation = Rotate::DEG_0;

        return $r;
    }

    /**
     * Return path to file, where image will be saved.
     * - If $file is empty, path to original file is returned.
     * - If $file ends with / or \, original filename is added to destination folder.
     * - If $file does not contain suffix (jpg, png or gif) this is added from original file.
     * - Otherwise, passed path is returned.
     *
     * @param  string $file Path or partial path ro file.
     * @return string       Return absolute path to file.
     */
    public function getPathWithSuffix(?string $file = null): string
    {
        if (empty($file)) {
            return $this->file;
        }
        if (\str_ends_with($file, '/') || \str_ends_with($file, '\\')) {
            return $file . \basename($this->file);
        }
        $mimeType = Utils::getTypeFromExtension($file);
        if ($mimeType) {
            return $file;
        }
        $file .= match ($this->mimeTypeConstant) {
            \IMAGETYPE_PNG => '.png',
            \IMAGETYPE_GIF => '.gif',
            default => '.jpg',
        };

        return $file;
    }

    protected function generateImage(?string $file, int $mimeType, ?int $quality): void
    {
        if (!$this->availableMemory(null, null, $mimeType, true)) {
            throw new \Resampler\Exceptions\OutOfMemoryException(
                "Cannot save image to file, there isn't enough needed memory",
            );
        }

        if ($this->imgResource === null) {
            throw new \Resampler\Exceptions\Exception('Image is not loaded to memory');
        }
        $success = match ($mimeType) {
            \IMAGETYPE_PNG => \imagepng($this->imgResource, $file, Utils::getQuality($quality, 0, 9, 9)),
            \IMAGETYPE_GIF => \imagegif($this->imgResource, $file),
            default => \imagejpeg($this->imgResource, $file, Utils::getQuality($quality, 0, 100, 85)),
        };

        if ($success === false) {
            if ($file === null) {
                throw new \Resampler\Exceptions\Exception('Cannot send image');
            }

            throw new \Resampler\Exceptions\FileException('Cannot save image to location', $file);
        }
    }

    /**
     * Save image to location. If $file is empty, original file is over written.
     * See getPathWithSuffix(), which formats $file argument accept.
     *
     * @param string $file    Path to new file, if empty original is over written.
     * @param int    $quality Picture quality. For PNG 0-9 (default 9), for JPG 0-100 (default 85).
     */
    public function save(?string $file = null, ?int $quality = null): static
    {
        $file = $this->getPathWithSuffix($file);
        $mimeType = Utils::getTypeFromExtension($file);
        if ($mimeType === false) {
            throw new \Resampler\Exceptions\Exception('Unsupported image extension');
        }
        if ((\file_exists($file) && !\is_writable($file)) || (!\file_exists($file) && !\is_writable(\dirname($file)))) {
            throw new \Resampler\Exceptions\FileException("Can't write into file", $file);
        }
        $this->loadToMemory();

        $this->generateImage($file, $mimeType, $quality);

        return $this;
    }

    /**
     * Send image to browser with headers if $sendHeader is true.
     *
     * @param string $ext        Type of image: jpg, png or gif.
     * @param int    $quality    Picture quality. For PNG 0-9 (default 9), for JPG 0-100 (default 85).
     * @param bool   $sendHeader If true, image header will be sent.
     */
    public function output(?string $ext = null, ?int $quality = null, bool $sendHeader = true): static
    {
        $this->loadToMemory();
        $mimeType = $this->mimeTypeConstant;
        if ($ext !== null) {
            $mimeType = Utils::getTypeFromExtension('.' . $ext);
            if ($mimeType === false) {
                throw new \Resampler\Exceptions\Exception('Unsupported image extension');
            }
        }

        if ($sendHeader) {
            \header('Content-type: ' . match ($mimeType) {
                \IMAGETYPE_PNG => 'image/png',
                \IMAGETYPE_GIF => 'image/gif',
                default => 'image/jpeg',
            });
        }
        $this->generateImage(null, $mimeType, $quality);

        return $this;
    }
}
