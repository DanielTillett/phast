<?php

namespace Kibo\Phast\Services\Bundler;

use Kibo\Phast\Exceptions\ItemNotFoundException;
use Kibo\Phast\HTTP\Response;
use Kibo\Phast\Logging\LoggingTrait;
use Kibo\Phast\Retrievers\Retriever;
use Kibo\Phast\Security\ServiceSignature;
use Kibo\Phast\Services\ServiceFilter;
use Kibo\Phast\Services\ServiceRequest;
use Kibo\Phast\ValueObjects\Resource;
use Kibo\Phast\ValueObjects\URL;

class Service {
    use LoggingTrait;

    /**
     * @var ServiceSignature
     */
    private $signature;

    /**
     * @var Retriever
     */
    private $retriever;

    /**
     * @var ServiceFilter
     */
    private $filter;

    /**
     * Bundler constructor.
     * @param ServiceSignature $signature
     * @param Retriever $retriever
     * @param ServiceFilter $filter
     */
    public function __construct(ServiceSignature $signature, Retriever $retriever, ServiceFilter $filter) {
        $this->signature = $signature;
        $this->retriever = $retriever;
        $this->filter = $filter;
    }

    /**
     * @param ServiceRequest $request
     * @return Response
     */
    public function serve(ServiceRequest $request) {
        $results = [];
        foreach ($this->getParams($request) as $key => $params) {
            if (!isset ($params['src'])) {
                $this->logger()->error('No src found for set {key}', ['key' => $key]);
                $results[$key] = ['status' => 404];
                continue;
            }
            if (!$this->verifyParams($params)) {
                $this->logger()->error('Params verification failed for set {key}', ['key' => $key]);
                $results[$key] = ['status' => 401];
                continue;
            }
            $resource = Resource::makeWithRetriever(
                URL::fromString($params['src']),
                $this->retriever
            );
            try {
                $this->logger()->info('Applying for set {key}', ['key' => $key]);
                $filtered = $this->filter->apply($resource, $params);
                $results[$key] = ['status' => 200, 'content' => $filtered->getContent()];
            } catch (ItemNotFoundException $e) {
                $this->logger()->error(
                    'Could not find {url} for set {key}',
                    ['url' => $params['src'], 'key' => $key]
                );
                $results[$key] = ['status' => 404];
            } catch (\Exception $e) {
                $this->logger()->critical(
                    'Unhandled exception for set {key}: {type} Message: {message} File: {file} Line: {line}',
                    [
                        'key' => $key,
                        'type' => get_class($e),
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                );
                $results[$key] = ['status' => 500];
            }
        }
        $response = new Response();
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent(json_encode($results));
        return $response;
    }

    private function getParams(ServiceRequest $request) {
        $result = [];
        foreach ($request->getParams() as $name => $value) {
            if (strpos($name, '_') !== false) {
                list ($name, $key) = explode('_', $name, 2);
                $result[$key][$name] = $value;
            }
        }
        return $result;
    }

    private function verifyParams(array $params) {
        return ServiceParams::fromArray($params)->verify($this->signature);
    }

}