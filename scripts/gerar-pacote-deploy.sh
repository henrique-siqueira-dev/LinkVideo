#!/usr/bin/env bash
# Monta o pacote de publicação em deploy/dist/:
#   ENVIAR-POR-FTP/linkvideo/  -> arrastar para dentro de www/sistemas/ no servidor
#                                 (pasta pública + subpasta do sistema com nome aleatório e .env)
#   linkvideo-instalacao.sql   -> importar no banco vazio pelo phpMyAdmin (só na 1ª instalação)
#   ATUALIZACAO/linkvideo/     -> só os arquivos que mudaram desde a última publicação (arrastar por cima)
#   ATUALIZACAO/banco.sql      -> tabelas novas desde a última publicação (importar no phpMyAdmin)
#
# A comparação é feita com deploy/servidor/ (retrato do que está no servidor). Depois de
# enviar a atualização, rode: bash scripts/marcar-publicado.sh
#
# O .env de produção fica guardado em deploy/producao.env e é reaproveitado a cada pacote.
# Uso: bash scripts/gerar-pacote-deploy.sh   (MySQL do XAMPP ligado)
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP:-/Applications/XAMPP/xamppfiles/bin/php}"
COMPOSER="${COMPOSER_PHAR:-$HOME/bin/composer.phar}"
DIST="deploy/dist"
NAME_FILE="deploy/.pasta-sistema"
MASTER_ENV="deploy/producao.env"
BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

# nome aleatório e fixo da subpasta do sistema (mantido entre atualizações)
[[ -f "$NAME_FILE" ]] || echo "sistema-$(openssl rand -hex 12)" > "$NAME_FILE"
SYS="$(cat "$NAME_FILE")"
OUT="$DIST/ENVIAR-POR-FTP/linkvideo"

# se o .env do último pacote foi editado (dados do banco), guarda como o principal
if [[ -f "$OUT/$SYS/.env" && ( ! -f "$MASTER_ENV" || "$OUT/$SYS/.env" -nt "$MASTER_ENV" ) ]]; then
  cp "$OUT/$SYS/.env" "$MASTER_ENV"
fi
if [[ ! -f "$MASTER_ENV" ]]; then
  APP_KEY="$("$PHP" artisan key:generate --show --no-interaction)"
  sed "s#^APP_KEY=.*#APP_KEY=${APP_KEY}#" .env.production.example > "$MASTER_ENV"
fi

echo "1/4 Compilando CSS/JS..."
npm run build --silent >/dev/null

echo "2/4 Copiando o projeto..."
rsync -a ./ "$BUILD/sistema/" \
  --exclude='.git/' --exclude='.claude/' --exclude='node_modules/' --exclude='tests/' \
  --exclude='deploy/' --exclude='docs/' --exclude='scripts/' --exclude='/sistema-*/' --exclude='/build/' --exclude='/index.php' --exclude='/.htaccess' --exclude='/favicon.ico' --exclude='/robots.txt' --exclude='database/sql/' --exclude='database/database.sqlite' \
  --exclude='.env' --exclude='.env.*' --exclude='.phpunit*' --exclude='phpunit.xml' \
  --exclude='public/hot' --exclude='public/storage' \
  --exclude='storage/logs/*.log' --exclude='storage/framework/sessions/*' --exclude='storage/framework/views/*' \
  --exclude='storage/framework/cache/data/*' --exclude='storage/framework/testing/' --exclude='storage/app/private/*/' --exclude='storage/app/public/*/' \
  --exclude='bootstrap/cache/*.php' --exclude='.DS_Store'

echo "3/4 Removendo dependências de desenvolvimento..."
(cd "$BUILD/sistema" && "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --quiet)
printf 'Require all denied\n' > "$BUILD/sistema/.htaccess"

echo "4/4 Montando a pasta para o FTP e o SQL de instalação..."
# retrato do que já está no servidor: base para a pasta ATUALIZACAO
PREV=""
[[ -d "deploy/servidor/$SYS" ]] && PREV="deploy/servidor"
rm -rf "$DIST" && mkdir -p "$DIST/ENVIAR-POR-FTP"
mv "$BUILD/sistema/public" "$OUT"
sed "s#__PASTA_SISTEMA__#${SYS}#g" deploy/pasta-publica/index.php > "$OUT/index.php"
mv "$BUILD/sistema" "$OUT/$SYS"
cp "$MASTER_ENV" "$OUT/$SYS/.env"
touch -r "$MASTER_ENV" "$OUT/$SYS/.env"

bash scripts/gerar-sql-instalacao.sh >/dev/null
cp database/sql/linkvideo-instalacao.sql "$DIST/"

if [[ -n "$PREV" ]]; then
  UPD="$DIST/ATUALIZACAO"
  mkdir -p "$UPD/linkvideo"
  PREV_ABS="$(cd "$PREV" && pwd)"
  # só conteúdo (ignora permissões/datas); o .env do servidor nunca é sobrescrito por aqui
  rsync -r --links --checksum --exclude="/$SYS/.env" --exclude="/$SYS/package-lock.json" \
    --compare-dest="$PREV_ABS/" "$OUT/" "$UPD/linkvideo/"
  find "$UPD/linkvideo" -type d -empty -delete 2>/dev/null || true
  mkdir -p "$UPD/linkvideo"

  # migrations novas desde a última publicação: SQL gerado pelo próprio Laravel
  # (comando linkvideo:sql-atualizacao) contra um banco com as migrations do servidor
  NEW_MIGS=0
  for f in database/migrations/*.php; do [[ -f "$PREV/$SYS/$f" ]] || NEW_MIGS=1; done
  if (( NEW_MIGS )); then
    MY=("/Applications/XAMPP/xamppfiles/bin/mysql" -h 127.0.0.1 -u root)
    DBENV=(env APP_ENV=local DB_HOST=127.0.0.1 DB_USERNAME=root DB_PASSWORD= DB_DATABASE=lv_upd_old)
    "${MY[@]}" -e "DROP DATABASE IF EXISTS lv_upd_old; CREATE DATABASE lv_upd_old CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    for f in "$PREV/$SYS"/database/migrations/*.php; do
      "${DBENV[@]}" "$PHP" artisan migrate --force -q --path="database/migrations/$(basename "$f")"
    done
    {
      echo "-- linkVideo: atualização do banco (gerado em $(date '+%d/%m/%Y %H:%M'))"
      echo "-- Como usar: phpMyAdmin > selecione o banco do sistema > Importar."
      echo "-- Importe UMA vez. Se aparecer 'Duplicate column' ou 'already exists', ela já tinha sido aplicada."
      echo
      echo "SET NAMES utf8mb4;"
      echo
      "${DBENV[@]}" "$PHP" artisan linkvideo:sql-atualizacao
    } > "$UPD/banco.sql"
    "${MY[@]}" -e "DROP DATABASE lv_upd_old;"
  fi
  echo "Atualização: $UPD ($(find "$UPD/linkvideo" -type f | wc -l | tr -d ' ') arquivos alterados)"
fi

echo
echo "Pronto: $OUT ($(find "$OUT" -type f | wc -l | tr -d ' ') arquivos)"
if grep -qE '^DB_(DATABASE|USERNAME|PASSWORD)=<' "$OUT/$SYS/.env"; then
  echo "ATENÇÃO: preencha DB_DATABASE, DB_USERNAME e DB_PASSWORD em $OUT/$SYS/.env"
fi
