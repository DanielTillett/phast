<?php


namespace Kibo\Phast\Filters\JavaScript\Composite;


use Kibo\Phast\Filters\Service\CompositeServiceFilter;
use Kibo\Phast\Filters\Service\PubliclyStoredResultServiceFilter;
use Kibo\Phast\ValueObjects\Resource;

class Filter extends CompositeServiceFilter implements PubliclyStoredResultServiceFilter {

    public function getStoreKey(Resource $resource, array $request) {
        $hashParts = $resource->getUrl()
            . (string) @$request['id']
            . (string) @$request['cacheMarker'];
        $matches = [];
        preg_match('/(.*?)(\..*)?$/', basename($resource->getUrl()->getPath()), $matches);
        return @$matches[1] . '_' . md5($hashParts) . @$matches[2];
    }
}
