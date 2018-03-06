<?php


namespace Kibo\Phast\Filters\Service;

use Kibo\Phast\Logging\LoggingTrait;
use Kibo\Phast\PublicResourcesStorage\Storage;
use Kibo\Phast\Services\ServiceFilter;
use Kibo\Phast\ValueObjects\Resource;

class PubliclyStoringResultServiceFilter implements ServiceFilter {
    use LoggingTrait;

    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var PubliclyStoredResultServiceFilter
     */
    private $filter;

    /**
     * PubliclyStoringResultServiceFilter constructor.
     * @param Storage $storage
     * @param PubliclyStoredResultServiceFilter $filter
     */
    public function __construct(Storage $storage, PubliclyStoredResultServiceFilter $filter) {
        $this->storage = $storage;
        $this->filter = $filter;
    }


    public function apply(Resource $resource, array $request) {
        $result = $this->filter->apply($resource, $request);
        if (isset ($request['token'])) {
            $key = $this->filter->getStoreKey($resource, $request);
            $this->logger()->info('Storing to {key}', ['key' => $key]);
            $this->storage->store($key, $result);
        }
        return $result;
    }


}
