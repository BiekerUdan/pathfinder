<?php


namespace Exodus4D\Pathfinder\ESI\Config\GitHub;

use Exodus4D\Pathfinder\ESI\Config\AbstractConfig;

class Config extends AbstractConfig {

    /**
     * @var array
     */
    protected static $spec = [
        'releases' => [
            'GET' =>  '/repos/{x}/releases'
        ],
        'markdown' => [
            'POST' =>  '/markdown'
        ]
    ];
}