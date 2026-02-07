# Quick Start

## Setup

Ensure `.env` contains vmauth credentials:
```bash
VMAUTH_ADMIN_USER=admin
VMAUTH_ADMIN_PASS=YourSecurePassword
```

## Start Services

```bash
docker compose up -d
```

## Access VMUI

**Development**: http://logs.localhost/select/vmui/
**Production**: https://vmui.production-web.site/select/vmui/

Login with `VMAUTH_ADMIN_USER` and `VMAUTH_ADMIN_PASS`.

## Basic Queries

```logsql
_time:1h                              # Last hour
error _time:1h                        # Errors only
{container_name="symfony"} _time:1h   # Specific container
"404" _time:1h                        # Search for "404"
_time:1h | stats count() by (container_name)  # Count by container
```

## Common Issues

| Issue | Solution |
|-------|----------|
| 404 Not Found | Check hostname: use `logs.localhost`. See vmauth logs: `docker compose logs vmauth \| head -5` |
| 401 Unauthorized | Verify `VMAUTH_ADMIN_PASS` in `.env` |
| No logs appear | Check Victoria Logs and Vector: `docker compose ps`. Check app logs to stdout in `api-symfony/config/packages/monolog.yaml` |
| Slow queries | Use indexed fields like `{container_name="symfony"}`. Non-indexed fields like `{request_id="123"}` are slow |

See [README.md](README.md) for full documentation.
