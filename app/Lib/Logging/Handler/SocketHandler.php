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
     * @param array|LogRecord $record
     * @return bool
     */
    public function handle(array|LogRecord $record) : bool {
        // Convert LogRecord to array for compatibility with both Monolog 2.x and 3.x
        $recordArray = $record instanceof LogRecord ? $record->toArray() : $record;

        if (!$this->isHandling($recordArray)) {
            return false;
        }

        $recordArray = $this->processRecord($recordArray);

        $recordArray = [
            'task' => 'logData',
            'load' => [
                'meta' => $this->metaData,
                'log' => $recordArray
            ]
        ];

        $recordArray['formatted'] = $this->getFormatter()->format($recordArray);

        $this->write($recordArray);

        return false === $this->bubble;
    }
}