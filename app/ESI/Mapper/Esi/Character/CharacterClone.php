<?php


namespace Exodus4D\Pathfinder\ESI\Mapper\Esi\Character;

use Exodus4D\Pathfinder\Data\Mapper\AbstractIterator;

class CharacterClone extends AbstractIterator {

    /**
     * @var array
     */
    protected static $map = [
        'location_id'   => ['location' => 'id'],
        'location_type' => ['location' => 'type']
    ];
}