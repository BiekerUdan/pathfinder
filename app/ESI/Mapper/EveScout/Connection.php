<?php


namespace Exodus4D\Pathfinder\ESI\Mapper\EveScout;

use Exodus4D\Pathfinder\Data\Mapper\AbstractIterator;

class Connection extends AbstractIterator {

    /**
     * @var array
     */
    protected static $map = [
        'id'                                => 'id',
        'signature_type'                    => 'type',
        'completed'                         => ['state' => 'name'],

        //Thera || Turnur
        'out_system_id'                     => ['source' => 'id'],
        'out_system_name'                   => ['source' => 'name'],
        'out_signature'                     => ['sourceSignature' => 'name'],

        //Connection
        'in_system_id'                      => ['target' => 'id'],
        'in_system_name'                    => ['target' => 'name'],
        'in_signature'                      => ['targetSignature' => 'name'],

        // Region data is set manually in EveScout.php (lines 52-54)
        // Cannot use nested arrays in mapper - causes "Illegal offset type"

        // Wormhole data
        'wh_type'                           => 'wh_type',
        'remaining_hours'                   => 'remaining_hours',
        'expires_at'                        => 'expires_at',

        'created_at'                        => 'created',
        'updated_at'                        => 'updated',

        'created_by_id'                     => ['character' => 'id'],
        'created_by_name'                   => ['character' => 'name']
    ];
}
