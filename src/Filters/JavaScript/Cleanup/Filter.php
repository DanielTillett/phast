<?php


namespace Kibo\Phast\Filters\JavaScript\Cleanup;


use Kibo\Phast\Services\ServiceFilter;
use Kibo\Phast\ValueObjects\Resource;

class Filter implements ServiceFilter {

    private $cleanupFunction = <<<EOF
(function (param) {
    var script = document.querySelector('[data-phast-proxied-script="' + param.i + '"]');
    if (script) {
        script.removeAttribute('data-phast-proxied-script');
        script.setAttribute('src', param.s);
    }
})
EOF;

    public function apply(Resource $resource, array $request) {
        if (empty($request['id'])
            || !preg_match('/^(?:[a-z0-9]+-)*[a-z0-9]+$/', $request['id'])
        ) {
            return $resource;
        }

        $param = [
            'i' => $request['id'],
            's' => (string) $resource->getUrl()
        ];
        $script = $this->cleanupFunction . '(' . json_encode($param) . ');';

        return $resource->withContent($script . $resource->getContent());
    }


}
