# Configuration Guide

## Environment Variables (.env)

Required:
```bash
VMAUTH_ADMIN_USER=admin***
VMAUTH_ADMIN_PASS=***
VMAUTH_READONLY_USER=readonly***
VMAUTH_READONLY_PASS=***
RETENTION_PERIOD=30d
```

## Core Services

### Victoria Logs

Stores and indexes logs with 30-day retention.

**Key file**: `compose.yml`
```yaml
victoria-logs:
  image: victoriametrics/victoria-logs:v1.44.0
  command:
    - -storageDataPath=/victoria-logs-data
    - -retentionPeriod=30d
```

Change retention: modify `-retentionPeriod=7d|30d|90d|365d` in `compose.yml`

### Vector

Collects Docker logs, parses JSON, sends to Victoria Logs.

**Key file**: `.devops/vmetrics/vector.toml`

Pipeline:
1. **Source**: Reads from Docker socket (`docker_logs`)
2. **Transform**: Parses JSON from `.message` field, merges fields
3. **Sink**: HTTP POST to Victoria Logs

To adjust indexed fields, modify this in `.devops/vmetrics/vector.toml`:
```toml
[sinks.victoria_logs]
uri = "http://victoria-logs:9428/insert/jsonline?_stream_fields=container_name,service"
```

For higher log volume, increase buffer:
```toml
[sinks.victoria_logs.buffer]
max_events = 50000
```

### vmauth

Provides Basic Auth to Victoria Logs.

**Key file**: `.devops/vmetrics/vmauth-entrypoint.sh`

Generates config from `.env` variables at startup:
- Creates 2 users: admin (full) and readonly (read-only)
- Restricts access to `/select/*` paths (VMUI + queries)
- Credentials from `.env` (VMAUTH_*_USER, VMAUTH_*_PASS)

To change passwords: update `.env` and restart `docker compose restart vmauth`

## Application Logging

### Monolog (Symfony)

**File**: `api-symfony/config/packages/monolog.yaml`

Settings:
```yaml
handlers:
  main:
    type: stream
    path: "php://stdout"
    formatter: monolog.formatter.json
```

Both dev and prod should log to stdout as JSON.

### Caddy Web Server

**File**: `api-symfony/Caddyfile` or `api-symfony/Caddyfile.dev`

```
log {
    format json { time_format iso8601 }
    output stdout
}
```

## Routing (Traefik)

### Development

**File**: `compose.override.dev.yaml`

```yaml
vmauth:
  labels:
    - "traefik.http.routers.vmui.rule=Host(`logs.localhost`)"
    - "traefik.http.services.vmui.loadbalancer.server.port=8427"
```

### Production

**File**: `compose.override.prod.yaml`

```yaml
vmauth:
  labels:
    - "traefik.http.routers.vmui.rule=Host(`vmui.production-web.site`)"
    - "traefik.http.routers.vmui.tls.certresolver=myresolver"
```

## Common Configuration Changes

### Change Log Retention

In `compose.yml`:
```yaml
victoria-logs:
  command:
    - -retentionPeriod=90d
```

### Add Indexed Field

In `.devops/vmetrics/vector.toml`:
```toml
uri = "...?_stream_fields=container_name,service,env"
```

Then restart Vector: `docker compose restart vector`

### Change vmauth Password

1. Update `.env`:
   ```bash
   VMAUTH_ADMIN_PASS=NewPassword
   ```
2. Restart vmauth:
   ```bash
   docker compose restart vmauth
   ```

## Validation

Validate Docker Compose config:
```bash
docker compose config --quiet
```

Test Victoria Logs connectivity:
```bash
docker compose exec vector curl http://victoria-logs:9428/api/v1/ping
```

## Performance Tuning

### Slow Queries

Queries using indexed fields:
- `{container_name="symfony"}` (indexed)
- `{request_id="123"}` (not indexed)

Add frequently-queried fields to `_stream_fields` in Vector config.

### High Log Volume

Increase Vector buffer in `.devops/vmetrics/vector.toml`:
```toml
[sinks.victoria_logs.buffer]
max_events = 50000
```

## Security Notes

- Victoria Logs only exposed internally (`expose` only, no `ports`)
- vmauth restricts to read-only `/select/*` paths
- Credentials stored in `.env` (not in config files, not in git)
- Use HTTPS in production (Traefik + Let's Encrypt handles this)

## See Also

- [README.md](README.md) - Overview and troubleshooting
- [QUICKSTART.md](QUICKSTART.md) - 5-minute setup
- [Victoria Logs Docs](https://docs.victoriametrics.com/victorialogs/)
- [Vector Docs](https://vector.dev/docs/)
