#!/usr/bin/env bash
# Gera database/sql/linkvideo-instalacao.sql para importar no phpMyAdmin do servidor:
# estrutura completa + histórico das migrations + os usuários master
# (senha do MASTER_PASSWORD do .env local, gravada em bcrypt, nunca em texto puro).
# Requer o MySQL do XAMPP ligado. Uso: bash scripts/gerar-sql-instalacao.sh
set -euo pipefail
cd "$(dirname "$0")/.."

PHP="${PHP:-/Applications/XAMPP/xamppfiles/bin/php}"
BIN="${MYSQL_BIN:-/Applications/XAMPP/xamppfiles/bin}"
TMP_DB="linkvideo_instalacao_tmp"
OUT="database/sql/linkvideo-instalacao.sql"
MYSQL=("$BIN/mysql" -h 127.0.0.1 -u root)

MASTER_PASSWORD="${MASTER_PASSWORD:-$(grep -E '^MASTER_PASSWORD=' .env | head -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/')}"
if [[ -z "$MASTER_PASSWORD" ]]; then
  echo "Defina MASTER_PASSWORD no .env local antes de gerar o SQL." >&2
  exit 1
fi

"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$TMP_DB\`; CREATE DATABASE \`$TMP_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
trap '"${MYSQL[@]}" -e "DROP DATABASE IF EXISTS \`$TMP_DB\`;"' EXIT

# usa sempre o MySQL local do XAMPP, mesmo que o .env aponte para outro banco
export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD= DB_DATABASE="$TMP_DB" APP_ENV=local BCRYPT_ROUNDS=12 MASTER_PASSWORD
"$PHP" artisan migrate --force --no-interaction -q
"$PHP" artisan db:seed --force --no-interaction -q

mkdir -p "$(dirname "$OUT")"
{
  echo "-- ============================================================"
  echo "-- linkVideo: banco para instalação (gerado em $(date '+%d/%m/%Y %H:%M'))"
  echo "-- Contém: estrutura, histórico das migrations e os usuários master"
  echo "--          (senhas criptografadas em bcrypt)."
  echo "-- Não contém vídeos."
  echo "-- Como usar: phpMyAdmin > selecione o banco (vazio) > Importar."
  echo "-- Depois de importar, APAGUE este arquivo."
  echo "-- ============================================================"
  echo
  "$BIN/mysqldump" -h 127.0.0.1 -u root \
    --default-character-set=utf8mb4 --single-transaction --skip-dump-date \
    --no-tablespaces --skip-triggers --add-drop-table \
    --ignore-table="$TMP_DB.sessions" --ignore-table="$TMP_DB.cache" --ignore-table="$TMP_DB.cache_locks" \
    "$TMP_DB"
  # tabelas de sessão/cache: só a estrutura
  "$BIN/mysqldump" -h 127.0.0.1 -u root \
    --default-character-set=utf8mb4 --skip-dump-date --no-tablespaces --add-drop-table --no-data \
    "$TMP_DB" sessions cache cache_locks
} > "$OUT"

echo "Gerado: $OUT ($(wc -l < "$OUT" | tr -d ' ') linhas)"
