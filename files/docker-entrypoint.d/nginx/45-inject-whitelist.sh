#!/bin/sh

# Inject local network whitelist into nginx configuration

NGINX_CONFIG="/etc/nginx/conf.d/default.conf"

if [ ! -z "${BAIKAL_LOCAL_WHITELIST}" ]; then
    echo "Configuring nginx whitelist for local networks: ${BAIKAL_LOCAL_WHITELIST}"

    # Create whitelist map entries from environment variable
    WHITELIST_MAP_ENTRIES=""
    for IP_RANGE in $BAIKAL_LOCAL_WHITELIST; do
        # Convert CIDR notation to regex pattern for nginx map
        case "$IP_RANGE" in
            192.168.*)
                # Handle 192.168.x.x networks
                PREFIX=$(echo "$IP_RANGE" | sed 's|/.*||' | sed 's|\.[0-9]*$||')
                WHITELIST_MAP_ENTRIES="${WHITELIST_MAP_ENTRIES}    ~^${PREFIX//./\\.}\\. 1;\n"
                ;;
            10.*)
                # Handle 10.x.x.x networks
                WHITELIST_MAP_ENTRIES="${WHITELIST_MAP_ENTRIES}    ~^10\\. 1;\n"
                ;;
            172.*)
                # Handle 172.16-31.x.x networks (private range)
                WHITELIST_MAP_ENTRIES="${WHITELIST_MAP_ENTRIES}    ~^172\\.(1[6-9]|2[0-9]|3[01])\\. 1;\n"
                ;;
            127.*)
                # Handle localhost
                WHITELIST_MAP_ENTRIES="${WHITELIST_MAP_ENTRIES}    ~^127\\. 1;\n"
                ;;
            *)
                # Handle custom IP ranges - convert basic CIDR to regex
                IP_PREFIX=$(echo "$IP_RANGE" | sed 's|/.*||' | sed 's|\.[0-9]*$||')
                ESCAPED_PREFIX=$(echo "$IP_PREFIX" | sed 's|\.|\\\\.|g')
                WHITELIST_MAP_ENTRIES="${WHITELIST_MAP_ENTRIES}    ~^${ESCAPED_PREFIX}\\. 1;\n"
                ;;
        esac
    done

    # Replace the whitelist section in nginx config
    # First, remove any existing whitelist directives between markers
    sed -i '/# InjectedWhitelistStart/,/# InjectedWhitelistEnd/{
        /# InjectedWhitelistStart/!{
            /# InjectedWhitelistEnd/!d
        }
    }' "$NGINX_CONFIG"

    # Now inject the new whitelist directives
    if [ ! -z "$WHITELIST_MAP_ENTRIES" ]; then
        # Create temporary file with new whitelist section
        awk -v whitelist="$WHITELIST_MAP_ENTRIES" '
        /# InjectedWhitelistStart/ {
            print $0
            printf "    # Generated from BAIKAL_LOCAL_WHITELIST=%s\n", ENVIRON["BAIKAL_LOCAL_WHITELIST"]
            printf "%s", whitelist
            next
        }
        /# InjectedWhitelistEnd/ { print $0; next }
        { print $0 }
        ' "$NGINX_CONFIG" > "${NGINX_CONFIG}.tmp" && mv "${NGINX_CONFIG}.tmp" "$NGINX_CONFIG"
    fi
else
    echo "No BAIKAL_LOCAL_WHITELIST configured, using default authentication for all requests"
    # Remove whitelist section when no whitelist is configured
    sed -i '/# InjectedWhitelistStart/,/# InjectedWhitelistEnd/{
        /# InjectedWhitelistStart/!{
            /# InjectedWhitelistEnd/!d
        }
    }' "$NGINX_CONFIG"
fi
