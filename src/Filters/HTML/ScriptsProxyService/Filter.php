<?php

namespace Kibo\Phast\Filters\HTML\ScriptsProxyService;

use Kibo\Phast\Common\ObjectifiedFunctions;
use Kibo\Phast\Filters\HTML\BaseHTMLStreamFilter;
use Kibo\Phast\Filters\HTML\Helpers\JSDetectorTrait;
use Kibo\Phast\Logging\LoggingTrait;
use Kibo\Phast\Parsing\HTML\HTMLStreamElements\Tag;
use Kibo\Phast\PublicResourcesStorage\Storage;
use Kibo\Phast\Retrievers\Retriever;
use Kibo\Phast\Security\ServiceSignature;
use Kibo\Phast\Services\ServiceRequest;
use Kibo\Phast\Filters\JavaScript;
use Kibo\Phast\ValueObjects\PhastJavaScript;
use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;

class Filter extends BaseHTMLStreamFilter {
    use JSDetectorTrait, LoggingTrait;

    /**
     * @var array
     */
    private $config;

    /**
     * @var Retriever
     */
    private $retriever;

    /**
     * @var ServiceSignature
     */
    private $signature;

    /**
     * @var JavaScript\Composite\Filter
     */
    private $filter;

    /**
     * @var Storage
     */
    private $storage;

    /**
     * @var ObjectifiedFunctions
     */
    private $functions;

    private $ids = [];

    /**
     * @var bool
     */
    private $didInject = false;

    /**
     * Filter constructor.
     * @param array $config
     * @param Retriever $retriever
     * @param ServiceSignature $signature
     * @param JavaScript\Composite\Filter $filter
     * @param Storage $storage
     * @param ObjectifiedFunctions|null $functions
     */
    public function __construct(
        array $config,
        Retriever $retriever,
        ServiceSignature $signature,
        JavaScript\Composite\Filter $filter,
        Storage $storage,
        ObjectifiedFunctions $functions = null
    ) {
        $this->config = $config;
        $this->retriever = $retriever;
        $this->signature = $signature;
        $this->filter = $filter;
        $this->storage = $storage;
        $this->functions = is_null($functions) ? new ObjectifiedFunctions() : $functions;
    }

    protected function isTagOfInterest(Tag $tag) {
        return $tag->getTagName() == 'script' && $this->isJSElement($tag);
    }

    protected function handleTag(Tag $script) {
        $this->rewriteScriptSource($script);
        if (!$this->didInject) {
            $this->addScript();
            $this->didInject = true;
        }
        yield $script;
    }

    private function rewriteScriptSource(Tag $element) {
        if (!$element->hasAttribute('src')) {
            return;
        }
        $src = trim($element->getAttribute('src'));
        $id = $this->getRewriteId($src);
        $url = $this->rewriteURL($src, $id);
        $element->setAttribute('src', $url);
        // TODO: Use an attribute to restore the original src
        $element->setAttribute('data-phast-proxied-script', $id);
    }

    private function getRewriteId($src) {
        $hash = md5($src);
        if (!isset ($this->ids[$hash])) {
            $this->ids[$hash] = 0;
        }
        // TODO: Use the same hashing funcs in php and js
        $id = "s-" . $hash . "-" . ++$this->ids[$hash];
        return $id;
    }

    private function rewriteURL($src, $id) {
        $url = URL::fromString($src)->withBase($this->context->getBaseUrl());
        if (!$this->shouldRewriteURL($url)) {
            $this->logger()->info('Not proxying {src}', ['src' => $src]);
            return $src;
        }
        $this->logger()->info('Proxying {src}', ['src' => $src]);
        $lastModTime = $this->retriever->getLastModificationTime($url);
        $params = [
            'src' => (string) $url,
            'id' => $id,
            'cacheMarker' => $lastModTime
                             ? $lastModTime
                             : floor($this->functions->time() / $this->config['urlRefreshTime'])
        ];
        $storageKey = $this->filter->getStoreKey(
            Resource::makeWithRetriever($url, $this->retriever),
            $params
        );
        if ($this->storage->exists($storageKey)) {
            return $this->storage->getPublicURL($storageKey);
        }
        return (new ServiceRequest())
            ->withUrl(URL::fromString($this->config['serviceUrl']))
            ->withParams($params)
            ->sign($this->signature)
            ->serialize();
    }

    private function shouldRewriteURL(URL $url) {
        if ($url->isLocalTo($this->context->getBaseUrl())) {
            return true;
        }
        $str = (string) $url;
        foreach ($this->config['match'] as $pattern) {
            if (preg_match($pattern, $str)) {
                return true;
            }
        }
        return false;
    }

    private function addScript() {
        $config = [
            'serviceUrl' => $this->config['serviceUrl'],
            'urlRefreshTime' => $this->config['urlRefreshTime'],
            'whitelist' => $this->config['match']
        ];
        $script = new PhastJavaScript(__DIR__ . '/rewrite-function.js');
        $script->setConfig('script-proxy-service', $config);
        $this->context->addPhastJavaScript($script);
    }

}
