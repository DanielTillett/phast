<?php

namespace Kibo\Phast\Filters\Service;


use Kibo\Phast\Exceptions\RuntimeException;
use Kibo\Phast\PhastTestCase;
use Kibo\Phast\Services\ServiceFilter;
use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;

class CompositeFilterTest extends PhastTestCase {

    /**
     * @var CompositeFilter
     */
    protected $filter;

    public function setUp() {
        parent::setUp();
        $this->filter = new CompositeFilter();
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

    public function testNotBreakingOnExceptionInFilter() {
        $filter = $this->createMock(ServiceFilter::class);
        $filter->method('apply')
            ->willReturnCallback(function (Resource $resource) {
                static $calls = 0;
                return $resource->withContent($resource->getContent() . ($calls++));
            });

        $throwingFilter = $this->createMock(ServiceFilter::class);
        $throwingFilter->method('apply')
            ->willThrowException(new RuntimeException());
        $this->filter->addFilter($filter);
        $this->filter->addFilter($throwingFilter);
        $this->filter->addFilter($filter);
        $resource = $this->makeResource();

        $result = $this->filter->apply($resource, []);
        $this->assertEquals('content01', $result->getContent());
    }

    protected function makeResource() {
        return Resource::makeWithContent(URL::fromString('http://phast.test'), 'content');
    }

}
