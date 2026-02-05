# Quick Start

## Setup

1. **Verify environment variables** in `.env`:
   ```bash
   GRAFANA_ADMIN_USER=admin
   GRAFANA_ADMIN_PASSWORD=SecureGrafanaPass123!
   ```

2. **Start monitoring stack**:
   ```bash
   # Development
   make monitoring-up

   # Production
   make monitoring-up-prod
   ```

3. **Verify deployment**:
   ```bash
   docker compose ps | grep -E "(prometheus|grafana|alertmanager)"
   ```

## Access

### Development
- **Grafana**: http://grafana.localhost
- **Prometheus**: http://prometheus.localhost
- **Alertmanager**: http://alertmanager.localhost

### Production
- Same hosts but with HTTPS and BasicAuth

## Dashboards

Three dashboards are automatically loaded:

1. **Traefik Dashboard** - HTTP metrics, latency
2. **Host Metrics Dashboard** - CPU, RAM, disk, network
3. **Containers Dashboard** - Per-container stats

Navigate in Grafana left sidebar → Dashboards.

## Verify Data

Check Prometheus targets:
```bash
curl -s http://prometheus.localhost/api/v1/targets | jq '.data.activeTargets[] | {job: .labels.job, health: .health}'
```

All should show `"health":"up"`.

## Troubleshooting

**No data in dashboards**

1. Check Prometheus targets are UP
2. Verify metrics are being scraped:
   ```bash
   curl -s http://prometheus.localhost/api/v1/query?query=up | jq
   ```
3. Wait 2-3 minutes for metrics to accumulate

**Containers dashboard empty on macOS**

This is expected. cAdvisor cannot access Docker daemon on macOS. Works on Linux only.

**cAdvisor errors**

Ignore. On macOS it runs in compatibility mode without container metrics.

## Stop

```bash
make monitoring-down
```
