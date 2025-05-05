FROM ubuntu:latest

#########################################################################################################
#########################################################################################################
##
## ZABBIX-MAILGRAPH (Dockerfile)
## =============================
## Dockerfile to builld and configure the zabbix-mailgraph image.
##
## -----------------------------------------------------------------------------------------------------
## v0.9.1 2025/05/05 - Mark Oudsen - First public beta - Pending documentation
## v0.9.0 2025/05/05 - Mark Oudsen - Internal test release
## v0.1.0 2025/05/05 - Mark Oudsen - Initial build, based on idea from "dima-online
##                                   "https://github.com/moudsen/mailGraph/issues/50
##
## -----------------------------------------------------------------------------------------------------
##
## (C) M.J.Oudsen, mark.oudsen@puzzl.nl
## MIT License
## Credits: "demi-online" (https://github.com/dima-online)
##
#########################################################################################################
#########################################################################################################

# Disable interactive functions 
ENV DEBIAN_FRONTEND=noninteractive

# Install Apache, PHP and supplimentary programs
RUN apt-get update && \
    apt-get install -y apache2 \
    libapache2-mod-php \
    php-curl \
    php-zip \
    curl \
    git

# Clean out APT files
RUN rm -rf /var/lib/apt/lists/*
RUN apt-get clean -y

# Install Composer for PHP dependencies
RUN cd /tmp && curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer

# Enable Apache modules
RUN a2enmod php8.3
RUN a2enmod rewrite

# Update the PHP.ini file, enable <? ?> tags and quieten logging.
RUN sed -i "s/short_open_tag = Off/short_open_tag = On/" /etc/php/8.3/apache2/php.ini
RUN sed -i "s/error_reporting = .*$/error_reporting = E_ERROR | E_WARNING | E_PARSE/" /etc/php/8.3/apache2/php.ini

# Manually set up the apache environment variables
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_LOG_DIR="/var/log/apache2"
ENV APACHE_LOCK_DIR="/var/lock/apache2"
ENV APACHE_PID_FILE="/var/run/apache2.pid"

# Copy the Github code into Apache site directory (run fetch_mailgraph.sh before building!)
ADD www /var/www/site/public

# Copy the cleanup script into local bin
ADD cleanup.sh /usr/local/bin/cleanup
RUN chmod +x /usr/local/bin/cleanup

# Fetch dependencies via Composer
RUN composer require phpmailer/phpmailer
RUN composer require twig/twig

# Move into the site directory
RUN mv /composer* /var/www/site/public/.
RUN mv /vendor /var/www/site/public/.

# Create some directories not existing yet
RUN mkdir /var/www/site/public/log
RUN mkdir /var/www/site/public/tmp

# Fix ownership
RUN chown www-data.www-data -R /var/www/site/public

# Update(/overwrite) the default apache site with the config we created
ADD apache-config.conf /etc/apache2/sites-enabled/000-default.conf

# Expose the HTTP port to external
EXPOSE 80

# By default, simply start apache
ENTRYPOINT ["/usr/sbin/apache2ctl", "-D", "FOREGROUND"]
