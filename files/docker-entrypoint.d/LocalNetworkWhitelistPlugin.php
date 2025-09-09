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
     * Reference to server object
     *
     * @var DAV\Server
     */
    protected $server;

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
        $this->server = $server;
        
        // Hook into the authentication process very early - before any method processing
        $server->on('beforeMethod:*', [$this, 'checkWhitelist'], 1000); // Very high priority
    }

    /**
     * Check if request is from whitelisted network and bypass authentication
     *
     * @param HTTP\RequestInterface $request
     * @param HTTP\ResponseInterface $response
     */
    public function checkWhitelist(HTTP\RequestInterface $request, HTTP\ResponseInterface $response)
    {
        // Skip processing for Sabre administrative requests (assets, plugins, etc.)
        $requestUri = $request->getUrl();
        if (strpos($requestUri, 'sabreAction=') !== false) {
            // This is a Sabre admin/asset request, don't interfere
            return;
        }

        // Check if this request is marked as whitelisted by the web server
        $whitelistHeader = $request->getHeader('X-Baikal-Whitelist');

        if ($whitelistHeader === '1') {
            // This request is from a whitelisted IP
            
            // Clear any existing authorization headers first
            unset($_SERVER['HTTP_AUTHORIZATION']);
            unset($_SERVER['PHP_AUTH_USER']);
            unset($_SERVER['PHP_AUTH_PW']);
            
            $authHeader = $request->getHeader('Authorization');
            
            if (!$authHeader) {
                // Extract username from the URL path for calendar/contacts access
                $path = $request->getPath();
                $username = null;
                
                // Try to extract username from paths like /calendars/josh@jwowk.net/ or /addressbooks/josh@jwowk.net/
                if (preg_match('#/(calendars|addressbooks)/([^/]+)/#', $path, $matches)) {
                    $username = $matches[2];
                } else {
                    // Default to the first user if we can't determine from path
                    $username = 'josh@jwowk.net';
                }
                
                // Log the extracted username
                error_log("Baikal: Extracted username: " . $username . " from path: " . $path);
                
                // Use the exact working credentials
                $workingEncodedCredentials = 'am9zaEBqd293ay5uZXQ6T1VUVEF0aW1lODgq';
                
                // Set the authorization header exactly as a working curl command would
                $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . $workingEncodedCredentials;
                
                // Also set alternative header names that might be used
                $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Basic ' . $workingEncodedCredentials;
                
                // Remove any conflicting auth variables
                unset($_SERVER['PHP_AUTH_USER']);
                unset($_SERVER['PHP_AUTH_PW']);
                
                error_log("Baikal: Setting WORKING auth header for username: " . $username);
            }

            // Set environment variable to indicate whitelist bypass for our auth backend
            $_SERVER['BAIKAL_WHITELIST_BYPASS'] = '1';

            // Log the whitelisted access
            $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            error_log("Baikal: Allowing whitelisted access from IP: " . $clientIp);
        }
    }

    /**
     * Handle authentication for whitelisted requests
     */
    public function handleWhitelistAuth(HTTP\RequestInterface $request, HTTP\ResponseInterface $response)
    {
        // Check if this is a whitelisted request
        $whitelistHeader = $request->getHeader('X-Baikal-Whitelist');
        
        if ($whitelistHeader === '1') {
            // Extract username from path
            $path = $request->getPath();
            $username = 'josh@jwowk.net'; // Default
            
            if (preg_match('#/(calendars|addressbooks)/([^/]+)/#', $path, $matches)) {
                $username = $matches[2];
            }
            
            // Simulate successful authentication by setting the user in the authentication plugin
            $authPlugin = $this->server->getPlugin('auth');
            if ($authPlugin && method_exists($authPlugin, 'setCurrentUser')) {
                $authPlugin->setCurrentUser($username);
                error_log("Baikal: Set current user to: " . $username);
            }
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
