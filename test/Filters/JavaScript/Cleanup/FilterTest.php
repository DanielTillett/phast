<?php

namespace Kibo\Phast\Filters\JavaScript\Cleanup;


use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase {

    public function testFetchingInjectedScript() {
        $resource = Resource::makeWithContent(URL::fromString('http://allowed.com/the-script'), 'the-content');
        $request = ['id' => '1'];
        $result = (new Filter())->apply($resource, $request);
        $this->assertContains('data-phast-proxied-script', $result->getContent());
        $this->assertContains('the-content', $result->getContent());
    }


}
