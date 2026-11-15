ARG GO_VERSION=1.27.4
FROM golang:${GO_VERSION}-alpine AS builder

RUN apk add --no-cache git ca-certificates

WORKDIR /build

COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -ldflags="-s -w" -o /build/api-go ./cmd/api

FROM alpine:3.23

RUN apk add --no-cache ca-certificates tzdata

COPY --from=builder /build/api-go /usr/local/bin/api-go
COPY --from=builder /build/config.yaml /etc/api-go/config.yaml

WORKDIR /etc/api-go

EXPOSE 8081

CMD ["api-go"]
