<?php

/**
 * Baikal Local Network Whitelist Plugin
 *
 * This plugin allows bypassing authentication for requests coming from
 * whitelisted local network IP addresses. It works in conjunction with
 * the web server configuration (Apache/nginx) that sets special headers
 * for whitelisted requests.
 */

namespace Baikal\Core\Frameworks\Flake\Core;

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
        $server->on('beforeMethod:*', [$this, 'checkWhitelist'], 1); // High priority
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
            // We'll set a flag that can be used by other parts of Baikal
            // to skip authentication

            // Get the current user from HTTP Basic Auth if provided, or use a default
            $authHeader = $request->getHeader('Authorization');
            if ($authHeader && stripos($authHeader, 'basic ') === 0) {
                // Extract username from basic auth
                $credentials = base64_decode(substr($authHeader, 6));
                list($username) = explode(':', $credentials, 2);
            } else {
                // No auth provided, use a default local user
                $username = 'local-network-user';
            }

            // Set environment variable to indicate whitelist bypass
            $_SERVER['BAIKAL_WHITELIST_BYPASS'] = '1';
            $_SERVER['BAIKAL_WHITELIST_USER'] = $username;

            // Log the whitelisted access
            error_log("Baikal: Allowing whitelisted access from IP: " .
                ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'));
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

    /**
     * Get the whitelisted user for this request
     *
     * @return string|null
     */
    public static function getWhitelistUser()
    {
        return $_SERVER['BAIKAL_WHITELIST_USER'] ?? null;
    }
}
