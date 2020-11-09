FROM php:8.1-cli

COPY dist/cli.phar /app/cli.phar

ENTRYPOINT ["php", "/app/cli.phar"]
