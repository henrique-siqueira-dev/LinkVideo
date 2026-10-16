#!/usr/bin/env bash
# Depois de enviar a atualização (ou o pacote completo) para o servidor, guarda o
# retrato do que está publicado em deploy/servidor/, base da próxima pasta ATUALIZACAO.
set -euo pipefail
cd "$(dirname "$0")/.."
rsync -a --delete deploy/dist/ENVIAR-POR-FTP/linkvideo/ deploy/servidor/
echo "Retrato do servidor atualizado em deploy/servidor/."
