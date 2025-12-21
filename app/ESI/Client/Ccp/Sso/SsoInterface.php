<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 26.12.2018
 * Time: 16:23
 */

namespace Exodus4D\Pathfinder\ESI\Client\Ccp\Sso;

interface SsoInterface {

    /**
     * @return string
     */
    public function getAuthorizationEndpointURI() : string;

    /**
     * @return string
     */
    public function getVerifyUserEndpointURI() : string;

    /**
     * @return string
     */
    public function getVerifyAuthorizationCodeEndpointURI() : string;

    /**
     * @return string
     */
    public function getJWKSEndpointURI() : string;

}