<?php


namespace Kibo\Phast\Filters\Service;


use Kibo\Phast\Services\ServiceFilter;
use Kibo\Phast\ValueObjects\Resource;

interface PubliclyStoredResultServiceFilter extends ServiceFilter {

    /**
     * @param Resource $resource
     * @param array $request
     * @return string
     */
    public function getStoreKey(Resource $resource, array $request);

}
