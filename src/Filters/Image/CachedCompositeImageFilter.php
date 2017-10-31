<?php

namespace Kibo\Phast\Filters\Image;

use Kibo\Phast\Cache\Cache;
use Kibo\Phast\Retrievers\Retriever;
use Kibo\Phast\ValueObjects\URL;

class CachedCompositeImageFilter extends CompositeImageFilter {

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var Retriever
     */
    private $retriever;

    /**
     * @var array
     */
    private $request;

    /**
     * @var array
     */
    private $filtersNames = [];

    /**
     * CachedCompositeImageFilter constructor.
     *
     * @param Cache $cache
     * @param Retriever $retriever
     * @param array $request
     */
    public function __construct(Cache $cache, Retriever $retriever, array $request) {
        $this->cache = $cache;
        $this->retriever = $retriever;
        $this->request = $request;
    }

    /**
     * @param ImageFilter $filter
     */
    public function addImageFilter(ImageFilter $filter) {
        parent::addImageFilter($filter);
        $this->filtersNames[] = get_class($filter);
    }

    /**
     * @param Image $image
     * @return Image
     */
    public function apply(Image $image) {
        $url = URL::fromString($this->request['src']);
        $lastModTime = $this->retriever->getLastModificationTime($url);
        sort($this->filtersNames);
        $toHash = join('', $this->filtersNames) . $lastModTime . $this->request['src'];
        if (isset ($this->request['width'])) {
            $toHash .= $this->request['width'];
        }
        if (isset ($this->request['height'])) {
            $toHash .= $this->request['height'];
        }
        $hash = md5($toHash);
        $filtered = $this->cache->get($hash, function () use ($image) {
            return parent::apply($image);
        });
        return $filtered;
    }

}