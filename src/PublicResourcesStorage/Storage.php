<?php


namespace Kibo\Phast\PublicResourcesStorage;


use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;

class Storage {

    /**
     * @var string
     */
    private $storageDir;

    /**
     * @var URL
     */
    private $publicURL;

    /**
     * Storage constructor.
     * @param string $storageDir
     * @param string $publicURL
     */
    public function __construct($storageDir, $publicURL) {
        $this->storageDir = $storageDir;
        $this->publicURL = URL::fromString($publicURL);
    }


    public function store($key, Resource $resource) {
        $file = $this->getFullPath($key);
        @mkdir($this->storageDir, 0755, true);
        $content = $resource->getContent();
        $size = strlen($content);
        $tmp = $file . uniqid('-tmp-', true);
        if ($size === @file_put_contents($tmp, $content)) {
            @rename($tmp, $file);
            @chmod($file, 0755);
        }
    }

    public function exists($key) {
        return file_exists($this->getFullPath($key));
    }

    public function getPublicURL($key) {
        return URL::fromString($this->getFilename($key))->withBase($this->publicURL);
    }

    private function getFilename($key) {
        return str_replace(['-', '/', "\0"], ['--', '-', '-0-'], $key);
    }

    private function getFullPath($key) {
        return $this->storageDir . '/' . $this->getFilename($key);
    }

}
