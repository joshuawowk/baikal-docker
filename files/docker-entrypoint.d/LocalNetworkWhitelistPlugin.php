<?php

/**
 * Baikal Local Network Whitelist Plugin
 *
 * This plugin allows bypassing authentication for requests coming from
 * whitelisted local network IP addresses. It works in conjunction with
 * the web server configuration (Apache/nginx) that sets special headers
 * for whitelisted requests.
 */

namespace Baikal\Core;

use Sabre\DAV;
use Sabre\HTTP;

class LocalNetworkWhitelistPlugin extends DAV\ServerPlugin
{

    /**
     * Returns a plugin name.
     *
     * @return string
     */
    public function getPluginName()
    {
        return 'local-network-whitelist';
    }

    /**
     * Initialize the plugin
     *
     * @param DAV\Server $server
     * @return void
     */
    public function initialize(DAV\Server $server)
    {
        // Hook into the authentication process very early
        $server->on('beforeMethod:*', [$this, 'checkWhitelist'], 10); // High priority
    }

    /**
     * Check if request is from whitelisted network and bypass authentication
     *
     * @param HTTP\RequestInterface $request
     * @param HTTP\ResponseInterface $response
     */
    public function checkWhitelist(HTTP\RequestInterface $request, HTTP\ResponseInterface $response)
    {
        // Check if this request is marked as whitelisted by the web server
        $whitelistHeader = $request->getHeader('X-Baikal-Whitelist');

        if ($whitelistHeader === '1') {
            // This request is from a whitelisted IP
            // Set the authorization header to a default user if none provided
            $authHeader = $request->getHeader('Authorization');
            
            if (!$authHeader) {
                // No auth provided, inject a basic auth header for a default user
                // This allows the rest of Baikal to work normally
                $defaultUser = 'local-network-user';
                $defaultPass = 'bypass'; // This won't be validated due to our backend override
                $encodedCredentials = base64_encode($defaultUser . ':' . $defaultPass);
                
                // Add the authorization header to the request
                $request = $request->withHeader('Authorization', 'Basic ' . $encodedCredentials);
                
                // Store the modified request back in the server
                // Note: We need to modify the global $_SERVER to affect PHP's input
                $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . $encodedCredentials;
            }

            // Set environment variable to indicate whitelist bypass for our auth backend
            $_SERVER['BAIKAL_WHITELIST_BYPASS'] = '1';

            // Log the whitelisted access
            $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            error_log("Baikal: Allowing whitelisted access from IP: " . $clientIp);
        }
    }

    /**
     * Check if the current request should bypass authentication
     *
     * @return bool
     */
    public static function shouldBypassAuth()
    {
        return !empty($_SERVER['BAIKAL_WHITELIST_BYPASS']);
    }
}
