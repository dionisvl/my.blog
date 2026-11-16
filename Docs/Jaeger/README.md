# Tracing (Jaeger)

Traefik creates a trace for every request and sends it to Jaeger over OTLP/HTTP
(`jaeger:4318`). The applications themselves are not instrumented, so a trace shows Traefik's
routers, middlewares and the upstream call.

Jaeger starts with the rest of the stack (`make up`).

| Environment | UI                           | Auth                              |
| ----------- | ---------------------------- | --------------------------------- |
| Dev         | http://jaeger.localhost      | none                              |
| Prod        | `https://jaeger.<MAIN_HOST>` | BasicAuth, `JAEGER_AUTH` in `.env` |

## Using the UI

1. Pick the `traefik` service and click **Find Traces**.
2. Narrow down with **Min Duration** (slow requests) or the tag `error=true`.
3. Open a trace to see the span timeline and status codes.

## Limits

- Storage is in memory: traces are lost when the container restarts.
- Every request is traced; lower Traefik's `--tracing.sampleRate` if that becomes too much.
