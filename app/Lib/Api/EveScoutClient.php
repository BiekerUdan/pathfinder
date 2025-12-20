<?php


namespace Exodus4D\Pathfinder\Lib\Api;

use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\ESI\Client\ApiInterface;
use Exodus4D\Pathfinder\ESI\Client\EveScout\EveScout as Client;

/**
 * Class EveScoutClient
 * @package lib\api
 *
 * @method ApiInterface send(string $requestHandler, ...$handlerParams)
 * @method ApiInterface sendBatch(array $configs)
 */
class EveScoutClient extends AbstractClient {

    /**
     * @var string
     */
    const CLIENT_NAME = 'eveScoutClient';

    /**
     * @param \Base $f3
     * @return ApiInterface|null
     */
    protected function getClient(\Base $f3) : ?ApiInterface {
        $client = null;
        if(class_exists(Client::class)){
            $client = new Client(Config::getPathfinderData('api.eve_scout'));
            $client->setLogStats(true);                        // add cURL stats (e.g. transferTime) to loggable requests
            $client->setLogCache(true);                       // add cache info (e.g. from cached) to loggable requests
            $client->setLogAllStatus(true);                 // log all requests regardless of response HTTP status code
            $client->setLogRequestHeaders(true);       // add request HTTP headers to loggable requests
            $client->setLogResponseHeaders(true);      // add response HTTP headers to loggable requests
            $client->setLogFile('evescout_requests');

        }else{
            $this->getLogger()->write($this->getMissingClassError(Client::class));
        }

        return $client;
    }
}