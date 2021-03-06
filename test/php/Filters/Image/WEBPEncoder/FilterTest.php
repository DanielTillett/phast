<?php

namespace Kibo\Phast\Filters\Image\WEBPEncoder;

use Kibo\Phast\Filters\Image\Image;
use Kibo\Phast\Filters\Image\ImageImplementations\DummyImage;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase {

    /**
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $request;

    /**
     * @var Filter
     */
    private $filter;

    public function setUp() {
        parent::setUp();
        $this->config = ['compression' => 80];
        $this->request = ['preferredType' => Image::TYPE_WEBP];
        $this->filter = $this->getFilter();
    }

    public function testEncoding() {
        $image = new DummyImage();
        $image->setType(Image::TYPE_JPEG);
        $image = $image->compress(10);

        $this->request['preferredType'] = Image::TYPE_PNG;
        $this->assertSame($image, $this->getFilter()->transformImage($image, $this->request));
        
        unset ($this->request['preferredType']);
        $this->assertSame($image, $this->getFilter()->transformImage($image, $this->request));

        $this->request['preferredType'] = Image::TYPE_WEBP;
        /** @var DummyImage $actual */
        $actual = $this->getFilter()->transformImage($image, $this->request);
        $this->assertNotSame($image, $actual);
        $this->assertEquals(Image::TYPE_WEBP, $actual->getType());
        $this->assertEquals(80, $actual->getCompression());
    }

    public function testChoosingImage() {
        $image = new DummyImage();

        $image->setImageString('super-super-long');
        $image->setTransformationString('short');
        $encoded = $this->filter->transformImage($image, $this->request);

        $image->setImageString('short');
        $image->setTransformationString('super-super-long');
        $nonEncoded = $this->filter->transformImage($image, $this->request);

        $this->assertNotSame($image, $encoded);
        $this->assertEquals('short', $encoded->getAsString());

        $this->assertSame($image, $nonEncoded);
    }

    public function testNotEncodingPNG() {
        $image = new DummyImage();
        $image->setType(Image::TYPE_PNG);
        $this->assertSame($image, $this->filter->transformImage($image, $this->request));
    }

    public function testGeneratingCacheSalt() {
        $filter1 = $this->getFilter();
        $this->config['compression'] = 85;
        $filter2 = $this->getFilter();
        $this->assertNotEquals($filter1->getCacheSalt([]), $filter2->getCacheSalt([]));
        $this->assertNotEquals($filter2->getCacheSalt([]), $filter2->getCacheSalt(['preferredType' => 'webp']));
    }

    private function getFilter() {
        return new Filter($this->config);
    }

}
