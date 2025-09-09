#!/bin/sh

# Inject local network whitelist into Apache configuration

APACHE_CONFIG="/etc/apache2/sites-available/000-default.conf"

if [ ! -z "${BAIKAL_LOCAL_WHITELIST}" ]; then
    echo "Configuring Apache whitelist for local networks: ${BAIKAL_LOCAL_WHITELIST}"

    # Create whitelist directives from environment variable
    WHITELIST_DIRECTIVES=""
    for IP_RANGE in $BAIKAL_LOCAL_WHITELIST; do
        WHITELIST_DIRECTIVES="${WHITELIST_DIRECTIVES}        Require ip ${IP_RANGE}\n"
    done

    # Replace the whitelist section in Apache config
    # First, remove any existing whitelist directives between markers
    sed -i '/# InjectedWhitelistStart/,/# InjectedWhitelistEnd/{
        /# InjectedWhitelistStart/!{
            /# InjectedWhitelistEnd/!d
        }
    }' "$APACHE_CONFIG"

    # Now inject the new whitelist directives
    if [ ! -z "$WHITELIST_DIRECTIVES" ]; then
        # Create temporary file with new whitelist section
        awk -v whitelist="$WHITELIST_DIRECTIVES" '
        /# InjectedWhitelistStart/ {
            print $0
            print "\t<LocationMatch \"^/(dav\\.php|cal\\.php|card\\.php)\">"
            print "\t\t# Default: Require authentication for all requests"
            print "\t\tSatisfy any"
            print "\t\t"
            print "\t\t# Allow access without authentication from whitelisted IPs"
            printf "\t\t# Generated from BAIKAL_LOCAL_WHITELIST=%s\n", ENVIRON["BAIKAL_LOCAL_WHITELIST"]
            printf "%s", whitelist
            print "\t\t"
            print "\t\t# For non-whitelisted IPs, authentication will be handled by Baikal application"
            print "\t\tRequire all granted"
            print "\t</LocationMatch>"
            next
        }
        /# InjectedWhitelistEnd/ { print $0; next }
        { print $0 }
        ' "$APACHE_CONFIG" > "${APACHE_CONFIG}.tmp" && mv "${APACHE_CONFIG}.tmp" "$APACHE_CONFIG"
    fi
else
    echo "No BAIKAL_LOCAL_WHITELIST configured, using default authentication for all requests"
    # Remove whitelist section when no whitelist is configured
    sed -i '/# InjectedWhitelistStart/,/# InjectedWhitelistEnd/{
        /# InjectedWhitelistStart/!{
            /# InjectedWhitelistEnd/!d
        }
    }' "$APACHE_CONFIG"
fi
