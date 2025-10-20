FROM php:8.4-fpm

RUN apt update && apt upgrade -y && \
    pecl install redis && \
    apt install -y git curl unzip libzip-dev libicu-dev && \
	docker-php-ext-install -j$(nproc) zip pdo_mysql mysqli intl && \
	docker-php-ext-enable zip pdo_mysql mysqli intl redis && \
	git config --global user.email "clement.trumpff.dev@gmail.com" && \
  	git config --global user.name "Clement Trumpff" && \
	curl -sS https://get.symfony.com/cli/installer | bash && \
	mv ~/.symfony*/bin/symfony /usr/local/bin/symfony && \
	curl -sS https://getcomposer.org/installer | php && \
	mv composer.phar /usr/local/bin/composer && \
	mkdir -p /usr/share/nginx/html

WORKDIR /usr/share/nginx/html
