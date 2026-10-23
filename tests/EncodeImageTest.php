<?php

use CatLab\Assets\Laravel\Models\Asset;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;

/**
 * intervention/image 2 encoded at quality 90 by default, 4 at 75: without
 * an explicit quality every resized JPEG and WebP came out visibly worse
 * (and 35-45% smaller) after the upgrade.
 */
class EncodeImageTest extends TestCase
{
    private function photo()
    {
        $manager = new ImageManager(GdDriver::class);
        $source = $manager->createImage(64, 48)->fill('6a9fd4');
        return $manager->decodeBinary($source->encodeUsingFormat(Format::JPEG, quality: 100)->toString());
    }

    public function testKeepsTheSourceFormatAtQuality90()
    {
        $image = $this->photo();

        $this->assertSame(90, Asset::ENCODE_QUALITY);
        $this->assertSame(
            $image->encodeUsingFormat(Format::JPEG, quality: 90)->toString(),
            Asset::encodeImage($image)->toString()
        );
        $this->assertNotSame(
            $image->encodeUsingFormat(Format::JPEG)->toString(),
            Asset::encodeImage($image)->toString()
        );
    }

    public function testForcedFormatIgnoresTheQualityItDoesNotTake()
    {
        $encoded = Asset::encodeImage($this->photo(), Format::PNG);

        $this->assertSame('image/png', $encoded->mediaType());
    }
}
