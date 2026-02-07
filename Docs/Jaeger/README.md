# Jaeger - Distributed Tracing

Distributed tracing for observing request flows across services.

## What is Jaeger?

Jaeger captures and visualizes traces as requests flow through your system:
- Request path and timeline
- Service dependencies
- Latency measurements
- Error tracking

## How It Works

```
HTTP Request → Traefik (generates trace) → Service
                    ↓
              Jaeger (receives trace)
                    ↓
              Jaeger UI (visualizes trace)
```

Traefik automatically generates traces via OpenTelemetry (OTLP) and sends them to Jaeger for storage and visualization.

## Accessing Jaeger UI

View traces in the Jaeger UI dashboard.

## Sampling

Tracing all requests (100%) is useful for development but expensive for production. Configure sampling rates to balance visibility and performance.

## Storage

Development uses in-memory storage (traces lost on restart). For production, use persistent backends (Elasticsearch, Cassandra, etc.) or cloud-hosted solutions.

## References

- [Jaeger Documentation](https://www.jaegertracing.io/docs/)
- [OpenTelemetry](https://opentelemetry.io/)