FROM ubuntu:22.04

# Install system depedancies
ARG DEBIAN_FRONTEND=noninteractive
RUN set -xe && \
    apt-get update -yqq && \
    apt-get install -yqq curl git mysql-client php8.1-cli php8.1-common php8.1-curl php8.1-mbstring \
                         php8.1-xml php8.1-zip php8.1-gd php8.1-bcmath php8.1-mysql php8.1-xdebug

# Install composer to a custom location
RUN mkdir /scripts && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

CMD ["/bin/bash"]