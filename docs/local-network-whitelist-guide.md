# Local Network Whitelist Guide

This guide explains how to configure Baikal to allow local network connections without requiring authentication, while still requiring authentication for external connections.

## Overview

The local network whitelist feature allows you to:

- Access Baikal from local network devices without entering credentials
- Maintain security by requiring authentication for external connections
- Configure specific IP ranges that should be whitelisted
- Support both Apache and nginx variants

## How It Works

The whitelist system works at two levels:

1. **Web Server Level**: Apache/nginx checks the client IP and allows/bypasses authentication for whitelisted ranges
2. **Application Level**: A PHP plugin handles the authentication bypass logic within Baikal

## Configuration

### Environment Variables

- `BAIKAL_LOCAL_WHITELIST`: Space-separated list of IP ranges in CIDR notation that should be whitelisted

### Common Private Network Ranges

- `192.168.0.0/16` - Standard home/office networks (192.168.x.x)
- `10.0.0.0/8` - Large private networks (10.x.x.x)
- `172.16.0.0/12` - Medium private networks (172.16.x.x to 172.31.x.x)
- `127.0.0.1/32` - Localhost only

## Usage Examples

### Basic Home Network Setup

For a typical home network using 192.168.1.x:

```yaml
environment:
  BAIKAL_LOCAL_WHITELIST: "192.168.1.0/24"
```

### Multiple Network Ranges

For environments with multiple private networks:

```yaml
environment:
  BAIKAL_LOCAL_WHITELIST: "192.168.1.0/24 10.0.0.0/8 172.16.0.0/12"
```

### Localhost Only

For testing or single-machine setups:

```yaml
environment:
  BAIKAL_LOCAL_WHITELIST: "127.0.0.1/32"
```

## Docker Compose Examples

### nginx Variant

```yaml
version: "2"
services:
  baikal:
    image: ckulka/baikal:nginx
    restart: always
    ports:
      - "80:80"
    environment:
      BAIKAL_LOCAL_WHITELIST: "192.168.1.0/24 10.0.0.0/8"
    volumes:
      - config:/var/www/baikal/config
      - data:/var/www/baikal/Specific
      - ./files/nginx-whitelist.conf:/etc/nginx/conf.d/default.conf:ro
      - ./files/docker-entrypoint.d/nginx/45-inject-whitelist.sh:/docker-entrypoint.d/45-inject-whitelist.sh:ro
      - ./files/docker-entrypoint.d/LocalNetworkWhitelistPlugin.php:/docker-entrypoint.d/LocalNetworkWhitelistPlugin.php:ro

volumes:
  config:
  data:
```

### Apache Variant

```yaml
version: "2"
services:
  baikal:
    image: ckulka/baikal:apache
    restart: always
    ports:
      - "80:80"
      - "443:443"
    environment:
      BAIKAL_LOCAL_WHITELIST: "192.168.1.0/24 10.0.0.0/8"
    volumes:
      - config:/var/www/baikal/config
      - data:/var/www/baikal/Specific
      - ./files/apache-whitelist.conf:/etc/apache2/sites-enabled/000-default.conf:ro
      - ./files/docker-entrypoint.d/httpd/45-inject-whitelist.sh:/docker-entrypoint.d/45-inject-whitelist.sh:ro
      - ./files/docker-entrypoint.d/LocalNetworkWhitelistPlugin.php:/docker-entrypoint.d/LocalNetworkWhitelistPlugin.php:ro

volumes:
  config:
  data:
```

## Installation Steps

1. Copy the appropriate configuration files to your Docker host:
   - For nginx: `files/nginx-whitelist.conf`
   - For Apache: `files/apache-whitelist.conf`
   - Entrypoint scripts and PHP plugin

2. Update your docker-compose.yaml with the whitelist configuration

3. Set the `BAIKAL_LOCAL_WHITELIST` environment variable with your desired IP ranges

4. Start the container: `docker-compose up -d`

## Security Considerations

### What's Protected

- External connections still require full authentication
- Admin interface access is not affected by whitelist
- File system access remains secured

### What's Not Protected

- Local network users can access CalDAV/CardDAV without credentials
- Data is still transmitted in plain text over HTTP (consider using HTTPS)
- Physical network access bypasses authentication

### Best Practices

1. Use specific IP ranges rather than broad networks when possible
2. Consider using HTTPS even for local connections
3. Regularly review and update your whitelist
4. Monitor access logs for unexpected usage
5. Keep the admin interface secured with strong passwords

## Troubleshooting

### Whitelist Not Working

1. Check that the environment variable is set correctly
2. Verify the entrypoint scripts are executable
3. Check container logs for whitelist configuration messages
4. Ensure your client IP is within the specified ranges

### Still Prompted for Authentication

1. Verify your client's IP address is in the whitelist range
2. Check if you're behind a proxy that might change the source IP
3. Ensure the web server configuration files are properly mounted

### Logs and Debugging

- Container startup logs will show whitelist configuration
- Web server error logs will show any configuration issues
- Access logs will show which IPs are accessing the service

## Limitations

1. **Reverse Proxy Compatibility**: If using a reverse proxy, you may need to configure it to pass the real client IP
2. **Docker Network**: Make sure the Docker network configuration allows the real client IP to be visible
3. **IPv6**: Current implementation focuses on IPv4 addresses

## Migration from Standard Setup

1. Backup your existing Baikal configuration and data
2. Update your docker-compose.yaml with the new configuration files
3. Add the `BAIKAL_LOCAL_WHITELIST` environment variable
4. Restart the container
5. Test access from both local and external networks

The existing authentication will remain for non-whitelisted connections, so your external access security is maintained.
