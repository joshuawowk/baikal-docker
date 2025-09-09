<?php

/**
 * Whitelist-aware PDO Basic Authentication Backend for Baikal
 *
 * This extends Baikal's PDOBasicAuth to support local network whitelist bypass.
 */

namespace Baikal\Core;

class WhitelistPDOBasicAuth extends PDOBasicAuth
{
    /**
     * Validates a username and password
     *
     * This method should return true or false depending on if login
     * succeeded.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    protected function validateUserPass($username, $password)
    {
        // Check if this request should bypass authentication due to whitelist
        if (LocalNetworkWhitelistPlugin::shouldBypassAuth()) {
            // Always return true for whitelisted requests
            return true;
        }
        
        // For non-whitelisted requests, use normal authentication
        return parent::validateUserPass($username, $password);
    }
}
