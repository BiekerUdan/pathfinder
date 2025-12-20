<?php


namespace Exodus4D\Pathfinder\ESI\Client\EveScout;

use Exodus4D\Pathfinder\ESI\Client;
use Exodus4D\Pathfinder\ESI\Config\ConfigInterface;
use Exodus4D\Pathfinder\ESI\Config\EveScout\Config;
use Exodus4D\Pathfinder\ESI\Lib\RequestConfig;
use Exodus4D\Pathfinder\ESI\Lib\WebClient;
use Exodus4D\Pathfinder\ESI\Mapper\EveScout as Mapper;

class EveScout extends Client\AbstractApi implements EveScoutInterface {

    /**
     * @return RequestConfig
     */
    protected function getTheraConnectionsRequest(): RequestConfig {
        return new RequestConfig(
            WebClient::newRequest('GET', $this->getConfig()->getEndpoint(['signatures', 'GET'])),
            [],
            function($body): array {
                $connectionsData = [];
                if(isset($body->error)){
                    $connectionsData['error'] = $body->error;
                    return $connectionsData;
                }
    
                foreach ($body as $data) {
                    $connectionId = (int)$data->id;
                    $wh_type = (string)$data->wh_type;
                    $target_region_id = (int)$data->in_region_id;
                    $target_region_name = (string)$data->in_region_name;
                    $outbound = (bool)$data->wh_exits_outward;
                    $remaining_hours = (int)$data->remaining_hours;

                  
                    $connectionData = (new Mapper\Connection($data))->getData();
                    // Add connection data
                    $connectionsData['connections'][$connectionId] = $connectionData;

                    // Set End of Life status
                    $connectionsData['connections'][$connectionId]['eol'] = $remaining_hours <= 4 ? "critical" : "fresh";
    
                    // Determine wormhole types
                    $outboundSig = $outbound ? 'sourceSignature' : 'targetSignature';
                    $connectionsData['connections'][$connectionId][$outboundSig]['type'] = $wh_type;


                    // Need to fix iterator to handle nesting so for now pulling out the region
                    //        'in_region_id'                      => ['target' => ['region' => 'id']],
                    $connectionsData['connections'][$connectionId]['target']['region']['id'] = $target_region_id;
                    //        'in_region_name'                    => ['target' => ['region' => 'name']],
                    $connectionsData['connections'][$connectionId]['target']['region']['name'] = $target_region_name;
                }
                return $connectionsData;
            }
        );
    }

    /**
     * @return ConfigInterface
     */
    protected function getConfig() : ConfigInterface {
        return ($this->config instanceof ConfigInterface) ? $this->config : $this->config = new Config();
    }
}