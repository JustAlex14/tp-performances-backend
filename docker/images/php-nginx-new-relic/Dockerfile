FROM arthureudeline/php-nginx

# Install NewRelic
ARG NEW_RELIC_API_KEY
ENV NEW_RELIC_API_KEY=$NEW_RELIC_API_KEY

ARG NEW_RELIC_ACCOUNT_ID
ENV NEW_RELIC_ACCOUNT_ID=$NEW_RELIC_ACCOUNT_ID

ARG NEW_RELIC_REGION
ENV NEW_RELIC_REGION=$NEW_RELIC_REGION

ARG NEW_RELIC_APP_NAME
ENV NEW_RELIC_APP_NAME=$NEW_RELIC_APP_NAME

ARG NEW_RELIC_LICENSE_KEY
ENV NEW_RELIC_LICENSE_KEY=$NEW_RELIC_LICENSE_KEY
ENV NR_INSTALL_KEY=$NEW_RELIC_LICENSE_KEY

ENV NR_INSTALL_USE_CP_NOT_LN 1
ENV NR_INSTALL_SILENT 1
ENV PHP_VERSION 8.1

RUN echo newrelic-php5 newrelic-php5/application-name string $NEW_RELIC_APP_NAME | debconf-set-selections \
    && echo newrelic-php5 newrelic-php5/license-key string $NEW_RELIC_LICENSE_KEY | debconf-set-selections

RUN echo 'deb http://apt.newrelic.com/debian/ newrelic non-free' | sudo tee /etc/apt/sources.list.d/newrelic.list \
    && wget -O- https://download.newrelic.com/548C16BF.gpg | sudo apt-key add - \
    && sudo apt update && yes | sudo apt -y install newrelic-php5 \
    && sudo NR_INSTALL_SILENT=1 newrelic-install install \
    && sed -i -e "s/REPLACE_WITH_REAL_KEY/$NEW_RELIC_LICENSE_KEY/" \
         -e "s/newrelic.appname[[:space:]]=[[:space:]].*/newrelic.appname=\"$NEW_RELIC_APP_NAME\"/" \
         /etc/php/$PHP_VERSION/fpm/conf.d/newrelic.ini