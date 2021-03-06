<?php

namespace Kibo\Phast\Filters\Image;

use Kibo\Phast\Filters\Image\ImageImplementations\GDImage;
use Kibo\Phast\Retrievers\LocalRetriever;
use Kibo\Phast\Retrievers\PostDataRetriever;
use Kibo\Phast\Retrievers\RemoteRetrieverFactory;
use Kibo\Phast\Retrievers\UniversalRetriever;
use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;

class ImageFactory {

    private $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * @param URL $url
     * @return GDImage
     */
    public function getForURL(URL $url) {
        if ($this->config['images']['api-mode']) {
            $retriever = new PostDataRetriever();
        } else {
            $retriever = new UniversalRetriever();
            $retriever->addRetriever(new LocalRetriever($this->config['retrieverMap']));
            $retriever->addRetriever((new RemoteRetrieverFactory())->make($this->config));
        }
        return new GDImage($url, $retriever);
    }

    /**
     * @param Resource $resource
     * @return GDImage
     */
    public function getForResource(Resource $resource) {
        return $this->getForURL($resource->getUrl());
    }

}
