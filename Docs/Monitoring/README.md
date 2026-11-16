# Monitoring

Optional stack, separate from `make up`: Prometheus, Grafana, Alertmanager, node-exporter and
cAdvisor. Compose files live in `.devops/monitoring/`; image versions are pinned in `.env`.

```
Traefik :8082/metrics ─┐
node-exporter ─────────┼─▶ Prometheus ─▶ Alertmanager
cAdvisor ──────────────┘        │
                                ▼
                             Grafana
```

## Run

```bash
make monitoring-up        # dev
make monitoring-up-prod   # prod
make monitoring-down
```

Set `GRAFANA_ADMIN_USER` and `GRAFANA_ADMIN_PASSWORD` in `.env` first.

| Service      | Dev                           | Prod                                               |
| ------------ | ----------------------------- | -------------------------------------------------- |
| Grafana      | http://grafana.localhost      | HTTPS + BasicAuth (`GRAFANA_AUTH`)                 |
| Prometheus   | http://prometheus.localhost   | HTTPS + BasicAuth (`PROMETHEUS_AUTH`)              |
| Alertmanager | http://alertmanager.localhost | HTTPS + BasicAuth (`ALERTMANAGER_AUTH`)            |

Prod hostnames are set in `docker-compose.monitoring.traefik-prod.yml`.

## Dashboards

Loaded automatically from `.devops/monitoring/grafana/dashboards/`:

- **Traefik** — requests per second, status codes, latency percentiles
- **Host** — CPU, memory, disk, load, network
- **Containers** — CPU, memory, network and disk I/O per container

To change one, export its JSON from Grafana into that directory and run `make monitoring-up` again.

## Alerts

One rule, `HostHighCpu`: CPU above 80% for 5 minutes (`prometheus/alerts/rules.yml`).
Alertmanager has no receiver configured yet, so alerts are visible in its UI but are not sent
anywhere.

## Notes

- Prometheus keeps 30 days of data. Data lives in `data/prometheus`, `data/grafana` and
  `data/alertmanager`.
- On macOS with Docker Desktop cAdvisor cannot see the containers, so the Containers dashboard
  stays empty. Check scrape targets at http://prometheus.localhost/targets.
