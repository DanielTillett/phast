<?php

namespace Kibo\Phast\Filters\Service;


use Kibo\Phast\PublicResourcesStorage\Storage;
use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;
use PHPUnit\Framework\TestCase;

class PubliclyStoringResultServiceFilterTest extends TestCase {

    /**
     * @var Resource
     */
    private $request;

    /**
     * @var bool
     */
    private $shouldStore;

    public function testApply() {
        $this->request = ['param' => 'value', 'token' => 'the-token'];
        $this->shouldStore = true;
        $this->performTest();
    }

    public function testNotStoringNonLocal() {
        $this->request = ['param' => 'value'];
        $this->shouldStore = false;
        $this->performTest();
    }

    private function performTest() {
        $resource = Resource::makeWithContent(
            URL::fromString('http://phast.test/resource'),
            'the-content'
        );
        $result = $resource->withContent('filtered');

        $storedFilter = $this->createMock(PubliclyStoredResultServiceFilter::class);
        $storedFilter->method('getStoreKey')
            ->with($resource)
            ->willReturn('the-key');
        $storedFilter->expects($this->once())
            ->method('apply')
            ->with($resource, $this->request)
            ->willReturn($result);

        $storeExpectation = $this->shouldStore ? $this->once() : $this->never();
        $store = $this->createMock(Storage::class);
        $store->expects($storeExpectation)
            ->method('store')
            ->with('the-key', $result);

        $filter = new PubliclyStoringResultServiceFilter($store, $storedFilter);
        $actual = $filter->apply($resource, $this->request);
        $this->assertSame($result, $actual);
    }

}
