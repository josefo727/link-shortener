#!/bin/bash

set -e

if [ -f "vendor/autoload.php" ]; then
    php artisan down
fi

rm -rf vendor node_modules

composer install --no-interaction --optimize-autoloader --no-dev

if grep -q "APP_KEY=base64:" .env; then
    echo "APP_KEY ya está configurada, no se generará una nueva"
else
    echo "Generando nueva APP_KEY..."
    php artisan key:generate
fi

# php artisan migrate --force
php artisan icon:cache
php artisan optimize
if [ ! -L public/storage ]; then
    php artisan storage:link
else
    echo "El enlace simbólico ya existe. No se ejecutó el comando."
fi

npm install
npm run build

chown -R application:application .

php artisan up
