<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 16.02.2019
 * Time: 11:26
 */

namespace Exodus4D\Pathfinder\Lib\Socket;


use React\Promise;

interface SocketInterface {

    /**
     * @param string $task
     * @param null $load
     * @return Promise\PromiseInterface
     */
    public function write(string $task, $load = null) : Promise\PromiseInterface;

    /**
     * @param string $class
     * @param string $uri
     * @param array $options
     * @return SocketInterface
     */
    public static function factory(string $class, string $uri, array $options = []) : SocketInterface;
}