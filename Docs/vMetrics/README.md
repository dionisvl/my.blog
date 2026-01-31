# Victoria Logs + vmUI - Centralized Logging

Logging stack using Victoria Logs, Vector, and vmauth for JSON log collection and search.

## Quick Links

- **Dev UI**: http://logs.localhost/select/vmui/
- **Prod UI**: https://vmui.production-web.site/select/vmui/
- **Credentials**: Set in `.env` (VMAUTH_ADMIN_USER, VMAUTH_ADMIN_PASS)

## Architecture

```
Apps → Docker logs → Vector → Victoria Logs → vmauth → VMUI
       (JSON)       (parse)   (store/index)   (auth)  (search UI)
```

## Components

| Component | Image | Purpose |
|-----------|-------|---------|
| Victoria Logs | victoriametrics/victoria-logs:v1.44.0 | Log storage and indexing |
| Vector | timberio/vector:nightly-2026-01-30-alpine | Docker log collector & transformer |
| vmauth | victoriametrics/vmauth:v1.101.0 | Authentication layer |
| VMUI | `/select/vmui/` | Web search interface |

## Getting Started

1. Configure `.env`:
   ```bash
   VMAUTH_ADMIN_USER=admin
   VMAUTH_ADMIN_PASS=YourSecurePassword
   VMAUTH_READONLY_USER=readonly
   VMAUTH_READONLY_PASS=YourReadOnlyPassword
   ```

2. Start services:
   ```bash
   docker compose up -d
   ```

3. Open VMUI at http://logs.localhost/select/vmui/ (dev) or https://vmui.production-web.site/select/vmui/ (prod)

## Basic Queries

LogsQL examples (run in VMUI):

```logsql
_time:1h                              # Last 1 hour
error _time:1h                        # Errors only
{container_name="symfony"} _time:1h   # Specific container
_time:1h | stats count() by (container_name)  # Group by container
```

See [QUICKSTART.md](QUICKSTART.md) for more examples.

## Configuration

### Essential Files

- `.env` - Credentials and settings
- `compose.yml` - Victoria Logs, Vector, vmauth definitions
- `.devops/vmetrics/vector.toml` - Log collection pipeline
- `.devops/vmetrics/vmauth-entrypoint.sh` - Auth config generator
- `api-symfony/config/packages/monolog.yaml` - Application logging
- `api-symfony/Caddyfile` - Web server logging

### Key Settings

```bash
# .env
RETENTION_PERIOD=30d              # Log retention (7d, 30d, 90d, 365d)
VMAUTH_ADMIN_USER=admin
VMAUTH_ADMIN_PASS=SecurePassword
VMAUTH_READONLY_USER=readonly
VMAUTH_READONLY_PASS=ReadOnlyPassword
```

See [CONFIGURATION.md](CONFIGURATION.md) for detailed setup.

## Troubleshooting

**VMUI returns 404**: Check hostname is `logs.localhost`. Verify vmauth running: `docker compose logs vmauth | head -5`

**401 Unauthorized**: Password is wrong. Verify `VMAUTH_ADMIN_PASS` in `.env`

**No logs visible**: Check Victoria Logs and Vector are running: `docker compose ps`. Verify app logs to stdout in monolog.yaml

**Slow queries**: Use indexed fields: `{container_name="symfony"}` is fast, `{request_id="123"}` is slow (not indexed)

## Security

- Victoria Logs not exposed to host (`expose` only, no `ports`)
- vmauth restricts to read-only `/select/*` paths
- Credentials stored in `.env` (not in config files)
- HTTPS enabled in production (Traefik + Let's Encrypt)

## Official Documentation

- [Victoria Logs Docs](https://docs.victoriametrics.com/victorialogs/)
- [LogsQL Examples](https://docs.victoriametrics.com/victorialogs/logsql-examples/)
- [Vector Documentation](https://vector.dev/docs/)
- [vmauth Documentation](https://docs.victoriametrics.com/vmauth/)
