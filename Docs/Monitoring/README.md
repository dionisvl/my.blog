# Monitoring Stack

Complete monitoring solution with **Prometheus**, **Grafana**, and **Alertmanager**.

## Architecture

```
┌─────────────┐
│   Traefik   │ ──► :8082/metrics
└─────────────┘            │
                           │
┌─────────────┐            ▼
│node_exporter│ ──► ┌───────────────┐      ┌──────────────┐
└─────────────┘     │  Prometheus   │ ───► │ Alertmanager │
                    │ (time series) │      │ (alerts)     │
┌─────────────┐     └───────────────┘      └──────────────┘
│  cAdvisor   │ ──►         │
└─────────────┘             │
                            ▼
                    ┌──────────────┐
                    │   Grafana    │
                    │(dashboards)  │
                    └──────────────┘
```

## Components

| Service | Image | Port | Purpose |
|---------|-------|------|---------|
| Prometheus | prom/prometheus:v3.9.1 | 9090 | Time series database, alerting |
| Grafana | grafana/grafana:12.3.2 | 3000 | Visualization, dashboards |
| Alertmanager | prom/alertmanager:v0.30.1 | 9093 | Alert routing |
| node-exporter | prom/node-exporter:v1.10.2 | 9100 | Host metrics |
| cAdvisor | gcr.io/cadvisor/cadvisor:latest | 8080 | Container metrics |

## Metrics

- **Traefik**: RPS, HTTP status codes, latency (p50/p95/p99)
- **Host**: CPU, RAM, disk, load, network throughput
- **Containers**: CPU, RAM, network I/O, disk I/O

## Dashboards

Automatically provisioned on startup:

1. **Traefik Dashboard** - Reverse proxy metrics
2. **Host Metrics Dashboard** - Server performance
3. **Containers Dashboard** - Per-container stats (Linux only)

## Alerts

Single baseline alert:
- **HostHighCpu**: CPU > 80% for 5 minutes

Alertmanager configured for extensibility (Slack, Email, Telegram, PagerDuty).

## Configuration

### Development

```bash
make monitoring-up
```

Access at:
- Grafana: http://grafana.localhost (admin / SecureGrafanaPass123!)
- Prometheus: http://prometheus.localhost
- Alertmanager: http://alertmanager.localhost

### Production

```bash
make monitoring-up-prod
```

HTTPS + BasicAuth via Traefik. Credentials in `.env`.

## Data Retention

- Prometheus: 30 days (configurable)
- Grafana: persistent volume
- Alertmanager: persistent volume

## Notes

- **macOS**: cAdvisor cannot access Docker daemon; Traefik and Host dashboards work
- **Linux**: All dashboards fully functional
- Dashboards automatically loaded via provisioning script on startup
