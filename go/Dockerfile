FROM golang:1.26.0-alpine3.23 AS builder
WORKDIR /build
COPY . .
RUN go mod download && \
    CGO_ENABLED=0 go build -o /go-proxy ./cmd/proxy

FROM alpine:3.23
COPY --from=builder /go-proxy /usr/local/bin/
CMD ["/usr/local/bin/go-proxy"]