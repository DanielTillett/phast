<?php

namespace Kibo\Phast\Filters\CSS\Composite;


use Kibo\Phast\Retrievers\Retriever;
use Kibo\Phast\Services\ServiceFilter;
use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase {

    /**
     * @var Filter
     */
    private $filter;

    public function setUp() {
        parent::setUp();
        $this->filter = new Filter();
    }

    public function testReturnSameResourceWhenEmpty() {
        $resource = $this->makeResource();
        $returned = $this->filter->apply($resource, []);
        $this->assertEquals($resource->getContent(), $returned->getContent());
    }

    public function testApplyingFilters() {
        $resource0 = $this->makeResource();
        $resource1 = $this->makeResource();
        $resource2 = $this->makeResource();
        $filter1 = $this->createMock(ServiceFilter::class);
        $filter1->expects($this->once())
            ->method('apply')
            ->with($resource0, [])
            ->willReturn($resource1, []);
        $filter2 = $this->createMock(ServiceFilter::class);
        $filter2->expects($this->once())
            ->method('apply')
            ->with($resource1, [])
            ->willReturn($resource2);

        $this->filter->addFilter($filter1);
        $this->filter->addFilter($filter2);
        $returned = $this->filter->apply($resource0, []);

        $this->assertEquals($resource2->getContent(), $returned->getContent());
    }

    public function testGetCacheHash() {
        $hashes = [];
        $resource = Resource::makeWithContent(URL::fromString('http://phast.test'), 'the-content');
        $hashes[] = $this->filter->getCacheHash($resource, []);
        $this->filter->addFilter($this->createMock(ServiceFilter::class));
        $hashes[] = $this->filter->getCacheHash($resource, []);
        $this->filter->addFilter($this->createMock(ServiceFilter::class));
        $hashes[] = $this->filter->getCacheHash($resource, []);
        $resource2 = Resource::makeWithContent(URL::fromString('http://phast.test'), 'other-content');
        $hashes[] = $this->filter->getCacheHash($resource2, []);
        $resource3 = Resource::makeWithContent(URL::fromString('http://phast.test/other-url.css'), 'the-content');
        $hashes[] = $this->filter->getCacheHash($resource3, []);
        $hashes[] = $this->filter->getCacheHash($resource3, ['strip-imports' => '1']);

        foreach ($hashes as $idx => $hash) {
            $this->assertTrue(is_string($hash), "Hash $idx is not string");
            $this->assertNotEmpty($hash, "Hash $idx is an empty string");
        }
        $this->assertEquals($hashes, array_unique($hashes), "There are duplicate hashes");
    }

    public function testGetStoreKey() {
        $keys = [];
        $params1 = ['cacheMarker' => 123];
        $resource = Resource::makeWithContent(URL::fromString('http://phast.test'), '');
        $keys[] = $this->filter->getStoreKey($resource, $params1);
        $this->filter->addFilter($this->createMock(ServiceFilter::class));
        $keys[] = $this->filter->getStoreKey($resource, $params1);
        $this->filter->addFilter($this->createMock(ServiceFilter::class));
        $keys[] = $this->filter->getStoreKey($resource, $params1);

        $params2 = ['cacheMarker' => 234];
        $resource2 = Resource::makeWithContent(URL::fromString('http://phast.test'), '');
        $keys[] = $this->filter->getStoreKey($resource2, $params2);
        $resource3 = Resource::makeWithContent(URL::fromString('http://phast.test/other-url.css'), '');
        $keys[] = $this->filter->getStoreKey($resource3, $params2);
        $keys[] = $this->filter->getStoreKey($resource3, array_merge(['strip-imports' => '1'], $params2));

        foreach ($keys as $idx => $key) {
            $this->assertTrue(is_string($key), "Key $idx is not string");
            $this->assertNotEmpty($key, "Key $idx is an empty string");
        }
        $this->assertEquals($keys, array_unique($keys), "There are duplicate keys");
    }

    public function testReadableStoreKey() {
        $retriever = $this->createMock(Retriever::class);
        $retriever->method('getLastModificationTime')
            ->willReturn(123);
        $resource = Resource::makeWithRetriever(URL::fromString('http://phast.test/css/the-file.css'), $retriever);
        $key = $this->filter->getStoreKey($resource, []);
        $this->assertRegExp('/^the-file_[a-f0-9]{32}\.css/', $key);
    }

    public function testRemoveComments() {
        $css = '/* a comment here */ selector {rule: /* comment in a weird place*/ value}';
        $resource = Resource::makeWithContent(URL::fromString('http://phast.test'), $css);
        $filtered = $this->filter->apply($resource, []);
        $this->assertEquals(' selector {rule:  value}', $filtered->getContent());
    }

    private function makeResource() {
        return Resource::makeWithContent(URL::fromString('http://phast.test'), 'content');
    }

}
