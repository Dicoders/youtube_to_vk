FROM golang:1.20-alpine AS builder

WORKDIR /app

COPY . .

ENV CGO_ENABLED=1
RUN go build -o feed_parser feed_parser.go
RUN go build -o downloader downloader.go
RUN go build -o image_downloader image_downloader.go
RUN go build -o uploader uploader.go
RUN go build -o clearer clearer.go

# Используем образ для запуска
FROM alpine:3.18

RUN apk update && apk add --no-cache \
    sqlite \
    sqlite-dev \
    bash \
    curl \
    git \
    openrc \
    supervisor \
    gcc \
    musl-dev \
    python3 \
    py3-pip \
    py3-virtualenv \
    ffmpeg


COPY --from=builder /app /app
COPY --from=builder /app/crontab /etc/crontabs/root
COPY --from=builder /app/supervisord.conf /etc/supervisord.conf

WORKDIR /app

CMD ["/usr/bin/supervisord"]