<?php


namespace Exodus4D\Pathfinder\ESI\Config\EveScout;

use Exodus4D\Pathfinder\ESI\Config\AbstractConfig;

class Config extends AbstractConfig {

    /**
     * @var array
     */
    protected static $spec = [
        'signatures' => [
            'GET' => 'v2/public/signatures'
        ]
    ];
}