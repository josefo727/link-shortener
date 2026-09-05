FROM webdevops/php-nginx:8.4-alpine

ENV WEB_DOCUMENT_ROOT=/app/public

# Allow static index.html as a directory index (e.g. /sismos-vzla/).
# Without this, webdevops defaults to index.php only and bare-directory
# requests for static HTML return 403.
ENV WEB_DOCUMENT_INDEX="index.php index.html"

ENV PHP_DISMOD=bz2,calendar,exiif,ffi,gettext,ldap,mysqli,imap,soap,sockets,sysvmsg,sysvsm,sysvshm,shmop,xsl,apcu,vips,yaml,mongodb,amqp

WORKDIR /app

RUN sed -i 's/v3\.21/v3.20/g' /etc/apk/repositories && \
    apk update && \
    apk add --update nodejs npm

RUN apk add --no-cache supervisor \
    && apk add --no-cache php-xml php-mbstring php-intl php-soap php-bcmath php-gd php-xsl

# pcov: not bundled in this image and not available as an apk package for its actual PHP build
# (the apk `php84-pecl-pcov` package installs into an unrelated, unused Alpine system PHP tree).
# Build tools are installed as a virtual package and removed in the same layer so the final
# image doesn't retain a compiler toolchain. See .specs/001-postgresql-compatibility/research.md
# (T001 spike) for how this was confirmed.
RUN apk add --no-cache --virtual .build-deps autoconf gcc g++ make \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del .build-deps

RUN chown -R application:application .

EXPOSE 80
