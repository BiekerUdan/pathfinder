<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 23.02.2019
 * Time: 19:11
 */

namespace Exodus4D\Pathfinder\Lib\Logging\Handler;


use Monolog\Logger;
use Monolog\LogRecord;

class SocketHandler extends \Monolog\Handler\SocketHandler {

    /**
     * some meta data (additional processing information)
     * @var array|string
     */
    protected $metaData                 = [];

    /**
     * SocketHandler constructor.
     * @param $connectionString
     * @param int $level
     * @param bool $bubble
     * @param array $metaData
     */
    public function __construct($connectionString, $level = Logger::DEBUG, $bubble = true, $metaData = []){
        $this->metaData = $metaData;

        parent::__construct($connectionString, $level, $bubble);
    }

    /**
     * overwrite default handle()
     * -> change data structure after processor() calls and before formatter() calls
     * @param LogRecord $record
     * @return bool
     */
    public function handle(LogRecord $record) : bool {
        if (!$this->isHandling($record)) {
            return false;
        }

        $record = $this->processRecord($record);

        // Convert to array for socket transmission
        $recordArray = $record->toArray();

        $socketData = [
            'task' => 'logData',
            'load' => [
                'meta' => $this->metaData,
                'log' => $recordArray
            ]
        ];

        $socketData['formatted'] = $this->getFormatter()->format($record);

        $this->write($socketData);

        return false === $this->bubble;
    }
}