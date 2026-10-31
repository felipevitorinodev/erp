#!/bin/sh
set -eu

cd /app

if [ "${DB_DATABASE:-}" != "erp_magnolias" ]; then
    echo "Inicialização recusada: a aplicação só pode usar o banco erp_magnolias."
    exit 1
fi

if [ "${DB_USERNAME:-}" != "magnolias_user" ]; then
    echo "Inicialização recusada: o usuário do banco deve ser magnolias_user."
    exit 1
fi

if [ "${DB_HOST:-}" != "erp_mysql" ]; then
    echo "Inicialização recusada: o host do banco deve ser erp_mysql."
    exit 1
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "Inicialização recusada: APP_KEY não definida."
    exit 1
fi

if [ "${APP_ENV:-}" != "production" ]; then
    echo "Inicialização recusada: APP_ENV deve ser production."
    exit 1
fi

case "${APP_DEBUG:-}" in
    true|TRUE|1|yes|on)
        echo "Inicialização recusada: APP_DEBUG não pode ficar ligado."
        exit 1
        ;;
esac

mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/app/certificados \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

i=0
until php -r '
    $pdo = new PDO(
        sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            getenv("DB_HOST"),
            getenv("DB_PORT") ?: "3306",
            getenv("DB_DATABASE")
        ),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD"),
        [PDO::ATTR_TIMEOUT => 3]
    );
    $nome = $pdo->query("SELECT DATABASE()")->fetchColumn();
    if ($nome !== "erp_magnolias") {
        exit(1);
    }
' >/dev/null 2>&1; do
    i=$((i + 1))
    if [ "$i" -ge 30 ]; then
        echo "MySQL indisponível ou o banco erp_magnolias não pôde ser acessado."
        exit 1
    fi
    sleep 2
done

php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction
php artisan storage:link --force --no-interaction
php artisan package:discover --ansi --no-interaction
php artisan config:cache --no-interaction
php artisan view:clear --no-interaction
php artisan view:cache --no-interaction
php artisan route:clear --no-interaction
if ! php artisan route:cache --no-interaction; then
    echo "Cache de rotas não aplicado. A aplicação segue sem ele."
    php artisan route:clear --no-interaction
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
find storage/app/certificados -type d -exec chmod 750 {} \;
find storage/app/certificados -type f -exec chmod 640 {} \;

php-fpm --nodaemonize &
php_pid=$!
nginx -g "daemon off;" -c /etc/nginx/nginx.conf &
nginx_pid=$!

shutdown() {
    kill -TERM "$php_pid" "$nginx_pid" 2>/dev/null || true
    wait "$php_pid" 2>/dev/null || true
    wait "$nginx_pid" 2>/dev/null || true
    exit 0
}

trap shutdown TERM INT

while kill -0 "$php_pid" 2>/dev/null && kill -0 "$nginx_pid" 2>/dev/null; do
    sleep 1
done

kill -TERM "$php_pid" "$nginx_pid" 2>/dev/null || true
wait "$php_pid" 2>/dev/null || true
wait "$nginx_pid" 2>/dev/null || true
exit 1
