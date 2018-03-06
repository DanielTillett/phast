<?php


namespace Kibo\Phast\Filters\CSS\Composite;

use Kibo\Phast\Filters\Service\CachedResultServiceFilter;
use Kibo\Phast\Filters\Service\CompositeServiceFilter;
use Kibo\Phast\Filters\Service\PubliclyStoredResultServiceFilter;
use Kibo\Phast\Logging\LoggingTrait;
use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\Filters\CSS\CommentsRemoval;

class Filter extends CompositeServiceFilter implements CachedResultServiceFilter, PubliclyStoredResultServiceFilter {
    use LoggingTrait;

    public function __construct() {
        $this->filters[] = new CommentsRemoval\Filter();
    }

    public function getStoreKey(Resource $resource, array $request) {
        $hashParts = join('', array_map('get_class', $this->filters));
        $hashParts .= $resource->getUrl();
        $hashParts .= (string) @$request['cacheMarker'];
        if (isset ($request['strip-imports'])) {
            $hashParts .= 'strip-imports';
        }

        $matches = [];
        preg_match('/(.*?)(\..*)?$/', basename($resource->getUrl()), $matches);
        return @$matches[1] . '_' . md5($hashParts) . @$matches[2];
    }


    public function getCacheHash(Resource $resource, array $request) {
        $parts = array_map('get_class', $this->filters);
        $parts[] = $resource->getUrl();
        $parts[] = md5($resource->getContent());
        if (isset ($request['strip-imports'])) {
            $parts[] = 'strip-imports';
        }
        return join("\n", $parts);
    }

}
