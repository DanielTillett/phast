<?php

namespace Kibo\Phast\Services\Bundler;

use Kibo\Phast\Security\ServiceSignature;

class ServiceParams {

    /**
     * @var string
     */
    private $token;

    /**
     * @var array
     */
    private $params;

    private function __construct() {}

    /**
     * @param array $params
     * @return ServiceParams
     */
    public static function fromArray(array $params) {
        $instance = new self();
        if (isset ($params['token'])) {
            $instance->token = $params['token'];
            unset ($params['token']);
        }
        $instance->params = $params;
        return $instance;
    }

    /**
     * @param ServiceSignature $signature
     * @return ServiceParams
     */
    public function sign(ServiceSignature $signature) {
        $params = $this->params;
        ksort($params);
        $new = new self();
        $new->token = $signature->sign(json_encode($params));
        $new->params = $params;
        return $new;
    }

    /**
     * @param ServiceSignature $signature
     * @return bool
     */
    public function verify(ServiceSignature $signature) {
        if (!isset ($this->token)) {
            return false;
        }
        return $this->token == $this->makeToken($signature);
    }

    /**
     * @return mixed
     */
    public function toArray() {
        $params = $this->params;
        if ($this->token) {
            $params['token'] = $this->token;
        }
        return $params;
    }

    private function makeToken(ServiceSignature $signature) {
        $params = $this->params;
        ksort($params);
        return $signature->sign(json_encode($params));
    }
}
