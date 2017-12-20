<?php

namespace Kibo\Phast;

use Kibo\Phast\Environment\Configuration;
use Kibo\Phast\Filters\HTML\Composite\Factory;
use Kibo\Phast\HTTP\Request;
use Kibo\Phast\Logging\Log;
use Kibo\Phast\Services\ServiceRequest;

class PhastDocumentFilters {

    public static function deploy(array $userConfig) {
        $request = ServiceRequest::fromHTTPRequest(Request::fromGlobals());
        $runtimeConfig = Configuration::fromDefaults()
            ->withUserConfiguration(new Configuration($userConfig))
            ->withServiceRequest($request)
            ->getRuntimeConfig()
            ->toArray();
        Log::init($runtimeConfig['logging'], $request, 'dom-filters');
        if (!$runtimeConfig['switches']['phast']) {
            Log::info('Phast is off. Skipping document filter deployment!');
            return;
        }
        $filter = (new Factory())->make($runtimeConfig);
        Log::info('Phast deployed!');
        ob_start([$filter, 'apply']);
    }

}
