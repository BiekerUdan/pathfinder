<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 14.03.15
 * Time: 21:04
 */

namespace Model;

use DB\SQL\Schema;

class SystemJumpModel extends SystemApiBasicModel {

    protected $table = 'system_jumps';

    protected $fieldConf = [
        'active' => [
            'type' => Schema::DT_BOOL,
            'nullable' => false,
            'default' => true,
            'index' => true
        ],
        'systemId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'unique' => true
        ]
    ];
} 