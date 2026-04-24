FROM golang:1.26.0-alpine3.23 AS builder

ARG APP_VERSION=dev
ARG BUILD_DATE
RUN echo "$APP_VERSION (built at $BUILD_DATE)" > /version.txt

WORKDIR /build
COPY . .
RUN go mod download && \
    CGO_ENABLED=0 go build -o /go-proxy ./cmd/proxy

FROM alpine:3.23
COPY --from=builder /go-proxy /usr/local/bin/
COPY --from=builder /version.txt /version.txt
CMD ["/usr/local/bin/go-proxy"]