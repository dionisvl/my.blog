# Quick Start - Jaeger Tracing

## 1. Start Services

Jaeger runs automatically with the rest of the stack.

## 2. Access Jaeger UI

Open the Jaeger dashboard to view traces.

## 3. Generate Traces

Make requests through your application to generate trace data.

## 4. View Traces in UI

In Jaeger UI:
1. Select a service from the dropdown
2. Click "Find Traces" to view recent traces
3. Click a trace to see details:
   - Spans and timeline
   - Latency and status codes
   - Service dependencies

## 5. Common Queries

| Goal | How |
|------|-----|
| Error traces | Filter by `error=true` |
| Slow requests | Set Min Duration filter |
| Specific paths | Search by request attributes |

## References

See README.md for detailed configuration.