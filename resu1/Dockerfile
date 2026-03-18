FROM php:8.2-cli

WORKDIR /app
COPY . /app

RUN apt-get update && apt-get install -y curl --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t ."]