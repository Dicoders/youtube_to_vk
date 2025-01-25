FROM php:8.4.3-zts-alpine3.20

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
    ffmpeg \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    freetype-dev \
    linux-headers

RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype
RUN docker-php-ext-install gd
RUN docker-php-ext-install sockets

RUN rm -rf /var/cache/apk/*

WORKDIR /app

COPY . .

RUN python3 -m venv /env
ENV PATH="/env/bin:$PATH"
RUN pip install --upgrade pip && pip install yt-dlp


RUN wget https://getcomposer.org/installer -O composer-setup.php
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN composer update

COPY ./docker/crontab /etc/crontabs/root
COPY ./docker/supervisord.conf /etc/supervisord.conf


CMD ["/usr/bin/supervisord"]
