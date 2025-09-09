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
    public function validateUserPass($username, $password)
    {
        // Check if this request should bypass authentication due to whitelist
        if (LocalNetworkWhitelistPlugin::shouldBypassAuth()) {
            // Log the whitelisted authentication with real credentials
            error_log("Baikal: Whitelisted auth for username: " . $username . " with password: " . substr($password, 0, 8) . "...");
            
            // For whitelisted requests, use the normal authentication to verify the real user exists
            return parent::validateUserPass($username, $password);
        }
        
        // For non-whitelisted requests, use normal authentication
        error_log("Baikal: Normal auth for username: " . $username);
        return parent::validateUserPass($username, $password);
    }
}
