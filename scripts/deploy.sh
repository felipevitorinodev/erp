#!/bin/sh
set -eu

ROOT="/home/felipe/servidor"
COMPOSE="$ROOT/docker-compose.yml"
TEMPLATE="$ROOT/erp/docker/docker-compose.production.yml"

if [ "$(id -u)" -eq 0 ]; then
    echo "Execute este script como o usuário felipe, não como root."
    exit 1
fi

for arg in "$@"; do
    case "$arg" in
        *seed*|*fresh*|*refresh*|*down*|*volume*|-v|--volumes)
            echo "Recusado: o deploy não executa seeders, não recria o banco e não apaga volumes."
            exit 1
            ;;
    esac
done

if [ ! -f "$TEMPLATE" ]; then
    echo "Arquivo de compose não encontrado em $TEMPLATE"
    exit 1
fi

cp "$TEMPLATE" "$COMPOSE"

cd "$ROOT"

cfg="$(mktemp)"
chmod 600 "$cfg"
backup=""
limpar() {
    if [ -n "${backup:-}" ]; then
        rm -rf "$backup"
    fi
    if [ -n "${cfg:-}" ]; then
        rm -f "$cfg"
    fi
}
trap limpar EXIT

docker compose config > "$cfg"

valor() {
    sed -n "s/^[[:space:]]*$1: //p" "$cfg" | head -n 1 | tr -d '"' | tr -d "'"
}

db="$(valor DB_DATABASE)"
url="$(valor APP_URL)"
user="$(valor DB_USERNAME)"
debug="$(valor APP_DEBUG)"

if [ "$db" != "erp_magnolias" ] || [ "$user" != "magnolias_user" ]; then
    echo "Compose inválido: o serviço deve usar erp_magnolias com magnolias_user."
    exit 1
fi

if [ "$url" != "https://magnolias.taild6fc8e.ts.net" ]; then
    echo "Compose inválido: APP_URL divergente."
    exit 1
fi

if [ "$debug" != "false" ]; then
    echo "Compose inválido: APP_DEBUG deve ser false."
    exit 1
fi

backup="$(mktemp -d /tmp/erp-cert-XXXXXX)"
chmod 700 "$backup"
restore_certs=0

if docker container inspect erp_app >/dev/null 2>&1; then
    if docker exec erp_app sh -c 'find /app/storage/app/certificados -type f | grep -q .'; then
        docker cp erp_app:/app/storage/app/certificados "$backup/certificados"
        restore_certs=1
    fi
fi

docker compose build erp
docker compose up -d --no-deps --force-recreate erp

if [ "$restore_certs" -eq 1 ]; then
    i=0
    until docker exec erp_app true >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "Container erp_app não aceitou a restauração dos certificados."
            exit 1
        fi
        sleep 2
    done
    docker cp "$backup/certificados/." erp_app:/app/storage/app/certificados/
    docker exec erp_app chown -R www-data:www-data /app/storage/app/certificados
    docker exec erp_app sh -c 'find /app/storage/app/certificados -type d -exec chmod 750 {} \; && find /app/storage/app/certificados -type f -exec chmod 640 {} \;'
fi

rm -rf "$backup"
backup=""

i=0
status="starting"
while [ "$i" -lt 40 ]; do
    status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' erp_app 2>/dev/null || echo missing)"
    if [ "$status" = "healthy" ]; then
        break
    fi
    i=$((i + 1))
    sleep 3
done

echo "container=$status"
docker compose ps erp erp_mysql

if [ "$status" != "healthy" ]; then
    echo "A aplicação não ficou saudável. Últimas linhas do log:"
    docker logs erp_app --tail 40
    exit 1
fi

docker exec erp_app curl -fsS http://127.0.0.1:8080/up >/dev/null
login="$(docker exec erp_app curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/login)"
css="$(docker exec erp_app curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/css/app.css)"
js="$(docker exec erp_app curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/js/app.js)"

echo "login=$login css=$css js=$js"

if [ "$login" != "200" ] || [ "$css" != "200" ] || [ "$js" != "200" ]; then
    exit 1
fi
