FROM php:8.2-cli

COPY . /app
WORKDIR /app

RUN apt-get update && apt-get install -y curl

CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t ."]