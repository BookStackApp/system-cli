FROM ubuntu:22.04

# Install system depedancies
ARG DEBIAN_FRONTEND=noninteractive
RUN set -xe && \
    apt-get update -yqq && \
    apt-get install -yqq curl git mysql-client php8.1-cli php8.1-common php8.1-curl php8.1-mbstring \
                         php8.1-xml php8.1-zip php8.1-gd php8.1-bcmath php8.1-mysql php8.1-xdebug

# Install composer to a custom location
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Clone down a BookStack instance for us to play with
RUN mkdir -p /var/www/bookstack && \
    cd /var/www/bookstack && \
    git clone https://github.com/BookStackApp/BookStack.git --branch release --single-branch ./ && \
    composer install --no-dev && \
    cp .env.example .env && \
    php artisan key:generate

# Update env options
RUN sed -i 's/^DB_HOST=.*/DB_HOST=db/' /var/www/bookstack/.env && \
    sed -i 's/^DB_DATABASE=.*/DB_DATABASE=bookstack/' /var/www/bookstack/.env && \
    sed -i 's/^DB_USERNAME=.*/DB_USERNAME=bookstack/' /var/www/bookstack/.env && \
    sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=bookstack/' /var/www/bookstack/.env

CMD ["/bin/bash"]