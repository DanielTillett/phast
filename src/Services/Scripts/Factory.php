<?php

namespace Kibo\Phast\Services\Scripts;

use Kibo\Phast\Filters\HTML\ScriptsProxyService\Filter;
use Kibo\Phast\Filters\JavaScript\Minification\JSMinifierFilter;
use Kibo\Phast\Filters\Service\CompositeFilter;
use Kibo\Phast\Security\ServiceSignatureFactory;
use Kibo\Phast\Services\ServiceFactoryTrait;

class Factory {
    use ServiceFactoryTrait;

    public function make(array $config) {
        $cachedComposite = new CompositeFilter();
        $cachedComposite->addFilter(new JSMinifierFilter(@$config['scripts']['removeLicenseHeaders']));

        return new Service(
            (new ServiceSignatureFactory())->make($config),
            $config['documents']['filters'][Filter::class]['match'],
            $this->makeUniversalCachingRetriever($config, 'scripts'),
            $this->makeCachingServiceFilterWithCompression($config, $cachedComposite, 'scripts-minified'),
            $config
        );
    }

}
