<?php

declare(strict_types=1);

namespace Resampler;

class Color
{
    /** @var array{int, int, int, int} */
    protected array $color = [0xff, 0xff, 0xff, 127];

    /**
     * By default value will be set to transparent, if nothing is specified
     *
     * @param string|null $hex Color in format #rgb, #rrggbb, #rrggbbaa or 'transparent'
     */
    public function __construct(?string $hex = null)
    {
        if ($hex !== null) {
            $this->setHex($hex);
        }
    }

    public function getR(): int
    {
        return $this->color[0];
    }

    public function getG(): int
    {
        return $this->color[1];
    }

    public function getB(): int
    {
        return $this->color[2];
    }

    public function getA(): int
    {
        return $this->color[3];
    }

    /**
     * @param string $hex Color in format #rgb, #rrggbb, #rrggbbaa or 'transparent'
     */
    public function setHex(string $hex): self
    {
        if ($hex === 'transparent') {
            $this->color = [255, 255, 255, 127];

            return $this;
        }
        if (\preg_match('/^#([0-9a-f]{3})(?:([0-9a-f]{3})([0-9a-f]{2})?)?$/i', $hex, $matches)) {
            if (empty($matches[2])) {
                $this->color[0] = (int)\hexdec($hex[1] . $hex[1]);
                $this->color[1] = (int)\hexdec($hex[2] . $hex[2]);
                $this->color[2] = (int)\hexdec($hex[3] . $hex[3]);
            } else {
                $this->color[0] = (int)\hexdec($hex[1] . $hex[2]);
                $this->color[1] = (int)\hexdec($hex[3] . $hex[4]);
                $this->color[2] = (int)\hexdec($hex[5] . $hex[6]);
            }

            $this->color[3] = empty($matches[3]) ? 0 : (int)(\hexdec($hex[7] . $hex[8]) / 2);
        } else {
            throw new \Resampler\Exceptions\Exception('Color ' . $hex . ' is in bad format');
        }

        return $this;
    }
}
