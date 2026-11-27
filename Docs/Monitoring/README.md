# Monitoring

Optional stack, separate from `make up`: Prometheus, Grafana and cAdvisor. Compose files live in
`.devops/monitoring/`; image versions are pinned in `.env`. Host metrics, logs and alerts are
covered outside this repository (Netdata and Dozzle on the server).

```
Traefik :8082/metrics ─┐
                       ├─▶ Prometheus ─▶ Grafana
cAdvisor ──────────────┘
```

Full stack (optional; node-exporter and Alertmanager are not in the Compose files anymore, the diagram is kept for reference):

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

| Service    | Dev                         | Prod                                  |
| ---------- | --------------------------- | ------------------------------------- |
| Grafana    | http://grafana.localhost    | HTTPS + BasicAuth (`GRAFANA_AUTH`)    |
| Prometheus | http://prometheus.localhost | HTTPS + BasicAuth (`PROMETHEUS_AUTH`) |

Prod hostnames are set in `docker-compose.monitoring.traefik-prod.yml`.

## Dashboards

Loaded automatically from `.devops/monitoring/grafana/dashboards/`:

- **Traefik** — requests per second, status codes, latency percentiles
- **Containers** — CPU, memory, network and disk I/O per container

To change one, export its JSON from Grafana into that directory and run `make monitoring-up` again.

## Notes

- Prometheus keeps 30 days of data. Data lives in `data/prometheus` and `data/grafana`.
- On macOS with Docker Desktop cAdvisor cannot see the containers, so the Containers dashboard
  stays empty. Check scrape targets at http://prometheus.localhost/targets.
