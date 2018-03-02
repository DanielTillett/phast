<?php


namespace Kibo\Phast\Filters\JavaScript\Composite;


use Kibo\Phast\Filters\Service\CachedResultServiceFilter;
use Kibo\Phast\Filters\Service\CompositeServiceFilter;
use Kibo\Phast\ValueObjects\Resource;

class Filter extends CompositeServiceFilter implements CachedResultServiceFilter {

    public function getCacheHash(Resource $resource, array $request) {
        return md5($resource->getContent()) . join('', array_map('get_class', $this->filters));
    }

}
