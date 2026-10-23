<?php

namespace CatLab\Assets\Image;

use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ModifierInterface;

/**
 * Class CircleMaskModifier
 *
 * Makes everything outside a centered circle transparent.
 * (Intervention Image v3+ no longer ships a mask modifier.)
 *
 * @package CatLab\Assets\Image
 */
class CircleMaskModifier implements ModifierInterface
{
    /**
     * @param int $diameter
     */
    public function __construct(
        protected int $diameter
    ) {
    }

    /**
     * @param ImageInterface $image
     * @return ImageInterface
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        $driver = $image->driver();

        foreach ($image as $frame) {
            if ($driver instanceof GdDriver) {
                $frame->setNative($this->applyGd($frame->native()));
            } elseif ($driver instanceof ImagickDriver) {
                $this->applyImagick($frame->native());
            } else {
                throw new \RuntimeException('Circle mask is not supported for driver ' . get_class($driver));
            }
        }

        return $image;
    }

    /**
     * @param \GdImage $source
     * @return \GdImage
     */
    protected function applyGd(\GdImage $source): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        $target = imagecreatetruecolor($width, $height);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefilledrectangle($target, 0, 0, $width - 1, $height - 1, imagecolorallocatealpha($target, 0, 0, 0, 127));

        $centerX = $width / 2;
        $centerY = $height / 2;
        $radiusSquared = ($this->diameter / 2) ** 2;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $dx = $x + 0.5 - $centerX;
                $dy = $y + 0.5 - $centerY;
                if ($dx * $dx + $dy * $dy <= $radiusSquared) {
                    $color = imagecolorsforindex($source, imagecolorat($source, $x, $y));
                    imagesetpixel(
                        $target,
                        $x,
                        $y,
                        imagecolorallocatealpha($target, $color['red'], $color['green'], $color['blue'], $color['alpha'])
                    );
                }
            }
        }

        return $target;
    }

    /**
     * @param \Imagick $native
     */
    protected function applyImagick(\Imagick $native): void
    {
        $width = $native->getImageWidth();
        $height = $native->getImageHeight();

        $mask = new \Imagick();
        $mask->newImage($width, $height, new \ImagickPixel('transparent'));
        $mask->setImageFormat('png');

        $draw = new \ImagickDraw();
        $draw->setFillColor(new \ImagickPixel('white'));
        $draw->circle($width / 2, $height / 2, $width / 2 + $this->diameter / 2, $height / 2);
        $mask->drawImage($draw);

        // SET (unlike ACTIVATE) initializes a missing alpha channel as opaque and keeps an existing one.
        $native->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);
        $native->compositeImage($mask, \Imagick::COMPOSITE_DSTIN, 0, 0);

        $mask->destroy();
    }
}
