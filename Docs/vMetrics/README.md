# Logs (VictoriaLogs)

```
containers ─▶ Docker logs ─▶ Vector ─▶ VictoriaLogs ─▶ vmauth ─▶ VMUI
                             (parse JSON)  (store)      (BasicAuth)
```

All three services start with the rest of the stack (`make up`); image versions are pinned in `.env`.

| Component    | Role                                                    | Config                                  |
| ------------ | ------------------------------------------------------- | --------------------------------------- |
| VictoriaLogs | Stores logs for 30 days                                 | `compose.yml` (`-retentionPeriod`)      |
| Vector       | Reads container logs, merges JSON fields into the entry | `.devops/vmetrics/vector.toml`          |
| vmauth       | BasicAuth in front of VictoriaLogs, `/select/*` only    | `.devops/vmetrics/vmauth-entrypoint.sh` |

## Access

| Environment | URL                        |
| ----------- | -------------------------- |
| Dev         | http://logs.localhost/select/vmui/ |
| Prod        | `https://vmui.<MAIN_HOST>` |

Log in with `VMAUTH_ADMIN_USER` / `VMAUTH_ADMIN_PASS` or `VMAUTH_READONLY_USER` /
`VMAUTH_READONLY_PASS` from `.env`. Both accounts can only query; nothing can be written through
vmauth. After changing them: `docker compose restart vmauth`.

## Queries

```logsql
_time:1h                                       # last hour
error _time:1h                                 # entries containing "error"
{container_name="symfony"} _time:1h            # one container
_time:1h | stats count() by (container_name)   # volume per container
```

Stream fields (`container_name`, `service`) are indexed and fast to filter on. To index another
field, add it to `_stream_fields` in `vector.toml` and restart Vector.

## Troubleshooting

| Symptom          | Check                                                                        |
| ---------------- | ---------------------------------------------------------------------------- |
| Request rejected | vmauth routes only `/select/*` paths                                         |
| 401              | Credentials in `.env`, then restart vmauth                                   |
| No logs          | `docker compose ps vector victoria-logs`; the app must log to stdout/stderr  |

## References

- [VictoriaLogs](https://docs.victoriametrics.com/victorialogs/) · [LogsQL](https://docs.victoriametrics.com/victorialogs/logsql/)
- [Vector](https://vector.dev/docs/) · [vmauth](https://docs.victoriametrics.com/vmauth/)
