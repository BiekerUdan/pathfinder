<?php


namespace Exodus4D\Pathfinder\ESI\Config\Ccp\Sso;

use Exodus4D\Pathfinder\ESI\Config\AbstractConfig;

class Config extends AbstractConfig {

    /**
     * @var array
     */
    protected static $spec = [
        'jwks' => [
            'GET' => '/oauth/jwks'
        ]
    ];
}