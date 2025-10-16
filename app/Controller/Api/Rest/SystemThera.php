<?php


namespace Exodus4D\Pathfinder\Controller\Api\Rest;

use Exodus4D\Pathfinder\Lib\Config;

class SystemThera extends AbstractRestController {

    /**
     * cache key for HTTP response
     */
    const CACHE_KEY_THERA_CONNECTIONS               = 'CACHED_THERA_CONNECTIONS';

    /**
     * get Thera connections data from Eve-Scout
     * @param \Base $f3
     */
    public function get(\Base $f3){
        $ttl = 60 * 1;
        if(!$exists = $f3->exists(self::CACHE_KEY_THERA_CONNECTIONS, $connectionsData)){
            $connectionsData = $this->getEveScoutTheraConnections();
            $f3->set(self::CACHE_KEY_THERA_CONNECTIONS, $connectionsData, $ttl);
        }

        $f3->expire(Config::ttlLeft($exists, $ttl));

        $this->out($connectionsData);
    }

    private function getUniverseSystemInfo(string $systemId) {
        $systemInfo = []; // Initialize as an empty array in case the query fails or no data is found
        if ($universeDB = $this->getDB('UNIVERSE')) {
            $query = "SELECT * FROM system WHERE id = :systemId";
            $params = [':systemId' => $systemId];
            $systemInfo = $universeDB->exec($query, $params);
        }
        return $systemInfo;
    }

    /**
     * get Thera connections data from EveScout API
     * -> map response to Pathfinder format
     * @return array
     */
    protected function getEveScoutTheraConnections() : array {
        $connectionsData = [];

        /**
         * map system data from eveScout response to Pathfinder´s 'system' format
         * @param string $key
         * @param array  $eveScoutConnection
         * @param array  $connectionData
         */
        $enrichWithSystemData = function(string $key, array $eveScoutConnection, array &$connectionData) : void {
            // source or target
            $eveScoutSystem = (array)$eveScoutConnection[$key];
            $universeSystemData = self::getUniverseSystemInfo((int)$eveScoutSystem['id'])[0];
            $systemData = [
                'id' => (int)$eveScoutSystem['id'],
                'name' => (string)$eveScoutSystem['name'],
                'trueSec' => isset($universeSystemData['trueSec']) ? (float)$universeSystemData['trueSec'] : -1
            ];
        
            if(!empty($universeSystemData['constellationId'])){
                $systemData['constellation'] = ['id' => (int)$universeSystemData['constellationId']];
            }
            if(!empty($region = (array)$eveScoutSystem['region']) && !empty($region['id'])){
                $systemData['region'] = ['id' => (int)$region['id'], 'name' => (string)$region['name']];
            }
            $connectionData[$key] = $systemData;
        };

        /**
         * @param string $key
         * @param array  $eveScoutConnection
         * @param array  $connectionData
         */
        $enrichWithSignatureData = function(string $key, array $eveScoutConnection, array &$connectionData) : void {
            $eveScoutSignature = (array)$eveScoutConnection[$key];
            $signatureName = isset($eveScoutSignature['name']) ? $eveScoutSignature['name'] : null;
            $signatureType = isset($eveScoutSignature['type']) ? strtoupper($eveScoutSignature['type']) : null;
            $signatureData = [
                'name' => $signatureName,
                'type' => $signatureType,
            ];

            $connectionData[$key] = $signatureData;
        };

        /**
         * map wormhole data from eveScout to Pathfinder´s connection format
         * @param array $eveScoutConnection - full connection data from API
         * @param array $connectionsData
         */
        $enrichWithWormholeData = function(array $eveScoutConnection, array &$connectionsData) : void {
            $type = [];

            // Eve-Scout v2 API doesn't provide mass status, default to fresh
            $type[] = 'wh_fresh';

            // Check EOL based on remaining_hours (EOL if <= 4 hours)
            $remainingHours = isset($eveScoutConnection['remaining_hours']) ? (int)$eveScoutConnection['remaining_hours'] : 24;
            if($remainingHours <= 4){
                $type[] = 'wh_eol';
            }

            $connectionsData['type'] = $type;

            // Set estimated EOL from expires_at
            if(isset($eveScoutConnection['expires_at'])){
                $connectionsData['estimatedEol'] = $eveScoutConnection['expires_at'];
            }
        };

        $eveScoutResponse = $this->getF3()->eveScoutClient()->send('getTheraConnections');

        if(!empty($eveScoutResponse) && !isset($eveScoutResponse['error'])){
            foreach((array)$eveScoutResponse['connections'] as $eveScoutConnection){
                if(
                    $eveScoutConnection['type'] === 'wormhole' &&
                    isset($eveScoutConnection['source']) && isset($eveScoutConnection['target'])
                ){
                    try{
                        $data = [
                            'id' => (int)$eveScoutConnection['id'],
                            'scope' => 'wh',
                            'created' => [
                                'created' => (new \DateTime($eveScoutConnection['created']))->getTimestamp(),
                                'character' => (array)$eveScoutConnection['character']
                            ],
                            'updated' => (new \DateTime($eveScoutConnection['updated']))->getTimestamp()
                        ];
                        $enrichWithWormholeData($eveScoutConnection, $data);
                        $enrichWithSystemData('source', $eveScoutConnection, $data);
                        $enrichWithSystemData('target', $eveScoutConnection, $data);
                        $enrichWithSignatureData('sourceSignature', $eveScoutConnection, $data);
                        $enrichWithSignatureData('targetSignature', $eveScoutConnection, $data);
                        $connectionsData[] = $data;
                    }catch(\Exception $e){
                        // new \DateTime Exception -> skip this data
                    }
                }
            }
        }

        return $connectionsData;
    }
}