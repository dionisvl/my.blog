# Dashboards

## Overview

Three pre-built dashboards auto-load on startup via JSON provisioning.

## 1. Traefik Dashboard

Monitors reverse proxy performance and request flow.

**Panels**:
- **Requests Per Second** - RPS by service
- **HTTP Status Codes** - 2xx/3xx/4xx/5xx breakdown
- **Response Latency** - p50/p95/p99 percentiles

**Key Metrics**:
```
sum(rate(traefik_service_requests_total[5m])) by (service)
histogram_quantile(0.95, ...)  # p95 latency
```

## 2. Host Metrics Dashboard

System resource utilization and performance.

**Panels**:
- **CPU Usage %** - Gauge (threshold colors)
- **Memory Usage %** - Gauge (threshold colors)
- **Disk Usage %** - Gauge (threshold colors)
- **Load Average** - 1m/5m/15m trends
- **Network Throughput** - RX/TX bytes/sec

**Key Metrics**:
```
rate(node_cpu_seconds_total{mode="idle"}[5m])
node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes
node_load1, node_load5, node_load15
```

## 3. Containers Dashboard

Per-container resource tracking.

**Panels**:
- **Container CPU %** - CPU by container
- **Container Memory MB** - RAM by container
- **Container Network I/O** - RX/TX throughput
- **Container Disk I/O** - Read/write operations

**Note**: Linux only. Requires real Docker daemon access (unavailable on macOS Docker Desktop).

## Editing Dashboards

### Via UI
1. Open dashboard
2. Click **Edit** (pencil icon)
3. Add/modify panels
4. Save

### Via JSON
1. Export from UI: Dashboard → **Share** → **Export** → **Download JSON**
2. Update `.devops/monitoring/grafana/dashboards/dashboard-name.json`
3. Restart Grafana: `docker compose restart grafana`

## Queries

Common PromQL patterns:

```promql
# Requests by service
sum(rate(traefik_service_requests_total[5m])) by (service)

# Error rate percentage
sum(rate(traefik_service_requests_total{code=~"5.."}[5m])) /
sum(rate(traefik_service_requests_total[5m])) * 100

# CPU percentage
(100 - avg(rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)

# Available memory percentage
node_memory_MemAvailable_bytes / node_memory_MemTotal_bytes

# Top containers by CPU
topk(5, sum(rate(container_cpu_usage_seconds_total[5m])) by (container_name))
```
