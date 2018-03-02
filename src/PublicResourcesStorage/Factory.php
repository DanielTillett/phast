<?php


namespace Kibo\Phast\PublicResourcesStorage;


class Factory {

    public function make(array $config) {
        return new Storage($config['publicStorage']['storeDir'], $config['publicStorage']['publicUrl']);
    }

}
