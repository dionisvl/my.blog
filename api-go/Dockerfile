ARG GO_VERSION=1.27.4
FROM golang:${GO_VERSION}-alpine AS builder

RUN apk add --no-cache git ca-certificates

WORKDIR /build

COPY go.mod go.sum ./
RUN --mount=type=cache,target=/go/pkg/mod \
    go mod download

COPY . .
RUN --mount=type=cache,target=/go/pkg/mod \
    --mount=type=cache,target=/root/.cache/go-build \
    CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o /build/api-go ./cmd/api

FROM alpine:3.24

RUN apk add --no-cache ca-certificates tzdata

COPY --from=builder /build/api-go /usr/local/bin/api-go
COPY --from=builder /build/config.yaml /etc/api-go/config.yaml

WORKDIR /etc/api-go

EXPOSE 8081

CMD ["api-go"]
