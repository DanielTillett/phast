<?php

namespace Kibo\Phast\PublicResourcesStorage;


use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;

class StorageTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var string
     */
    private $dir;

    /**
     * @var URL
     */
    private $publicUrl = 'http://phast.test/public/';

    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var URL
     */
    private $url;

    public function setUp() {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/' . uniqid('phast-storage-test-');
        $this->storage = new Storage($this->dir, $this->publicUrl);
        $this->url = URL::fromString('http://phast.test');
    }

    public function tearDown() {
        parent::tearDown();
        foreach (glob($this->dir . '/*') as $file) {
            @unlink($this->dir . '/' . $file);
        }
        @rmdir($this->dir);
    }

    public function testStoring() {
        $key = 'the-file';
        $content = 'some-content';

        $this->assertFalse($this->storage->exists($key));
        $this->storage->store($key, Resource::makeWithContent($this->url, $content));
        $this->assertTrue($this->storage->exists($key));

        $expectedUrl = 'http://phast.test/public/the--file';
        $url = $this->storage->getPublicURL($key);
        $this->assertEquals($expectedUrl, $url);

        $newStorage = new Storage($this->dir, $this->publicUrl);
        $this->assertTrue($newStorage->exists($key));
        $this->assertEquals($expectedUrl, $newStorage->getPublicURL($key));
    }

    public function testRetrieving() {
        $key = "../some/\0bad-url";
        $content = 'some-content';
        $this->storage->store($key, Resource::makeWithContent($this->url, $content));
        $url = $this->storage->getPublicURL($key);
        $file = str_replace($this->publicUrl, $this->dir . '/', $url);
        $this->assertEquals($content, file_get_contents($file));
    }

}
