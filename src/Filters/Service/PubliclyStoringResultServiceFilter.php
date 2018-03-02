<?php


namespace Kibo\Phast\Filters\Service;

use Kibo\Phast\Logging\LoggingTrait;
use Kibo\Phast\PublicResourcesStorage\Storage;
use Kibo\Phast\Services\ServiceFilter;
use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;

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
     * @var URL
     */
    private $localUrl;

    /**
     * PubliclyStoringResultServiceFilter constructor.
     * @param Storage $storage
     * @param PubliclyStoredResultServiceFilter $filter
     * @param string $localUrl
     */
    public function __construct(Storage $storage, PubliclyStoredResultServiceFilter $filter, $localUrl) {
        $this->storage = $storage;
        $this->filter = $filter;
        $this->localUrl = URL::fromString((string) $localUrl);
    }


    public function apply(Resource $resource, array $request) {
        $result = $this->filter->apply($resource, $request);
        if ($resource->getUrl()->isLocalTo($this->localUrl)) {
            $key = $this->filter->getStoreKey($resource, $request);
            $this->logger()->info('Storing to {key}', ['key' => $key]);
            $this->storage->store($key, $result);
        }
        return $result;
    }


}
