#!/bin/sh

# Install Local Network Whitelist Plugin into Baikal

if [ ! -z "${BAIKAL_LOCAL_WHITELIST}" ]; then
    echo "Installing Local Network Whitelist Plugin"
    
    # Copy plugin files to Baikal's Core directory
    if [ -f "/docker-entrypoint.d/LocalNetworkWhitelistPlugin.php" ]; then
        cp "/docker-entrypoint.d/LocalNetworkWhitelistPlugin.php" "/var/www/baikal/Core/Frameworks/Baikal/Core/"
        echo "Plugin file copied to Baikal Core directory"
    else
        echo "Warning: Plugin file not found at /docker-entrypoint.d/LocalNetworkWhitelistPlugin.php"
        exit 0
    fi
    
    if [ -f "/docker-entrypoint.d/WhitelistPDOBasicAuth.php" ]; then
        cp "/docker-entrypoint.d/WhitelistPDOBasicAuth.php" "/var/www/baikal/Core/Frameworks/Baikal/Core/"
        echo "Whitelist auth backend copied to Baikal Core directory"
    else
        echo "Warning: Whitelist auth backend not found at /docker-entrypoint.d/WhitelistPDOBasicAuth.php"
        exit 0
    fi
    
    # Check if plugin is already integrated
    if grep -q "LocalNetworkWhitelistPlugin" /var/www/baikal/Core/Frameworks/Baikal/Core/Server.php; then
        echo "Plugin already integrated into Server.php"
    else
        echo "Integrating plugin into Baikal Server.php"
        
        # Create backup
        cp /var/www/baikal/Core/Frameworks/Baikal/Core/Server.php /var/www/baikal/Core/Frameworks/Baikal/Core/Server.php.backup
        
        # Replace PDOBasicAuth with our WhitelistPDOBasicAuth
        sed -i 's/new \\Baikal\\Core\\PDOBasicAuth(/new \\Baikal\\Core\\WhitelistPDOBasicAuth(/g' /var/www/baikal/Core/Frameworks/Baikal/Core/Server.php
        
        # Add the plugin loading right after the auth plugin
        # This ensures our plugin can intercept authentication early
        sed -i '/\$this->server->addPlugin(new \\Sabre\\DAV\\Auth\\Plugin(\$authBackend, \$this->authRealm));/a\
        $this->server->addPlugin(new \\Baikal\\Core\\LocalNetworkWhitelistPlugin());' /var/www/baikal/Core/Frameworks/Baikal/Core/Server.php
        
        echo "Plugin integrated successfully"
    fi
else
    echo "BAIKAL_LOCAL_WHITELIST not set, skipping whitelist plugin installation"
fi
