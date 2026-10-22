#!/bin/bash
# Backup do banco do GLPI (somente homologação neste script) para ~/backups-glpi, fora do Git.
# Uso: ./scripts/backup-banco.sh homologacao
set -euo pipefail
AMB="${1:-homologacao}"
[ "$AMB" = "homologacao" ] || { echo "Este script só faz backup da homologação. Produção: siga docs/BACKUPS.md com o DBA."; exit 1; }

DESTINO="${HOME}/backups-glpi"
mkdir -p "$DESTINO"; chmod 700 "$DESTINO"
SENHA="$(docker exec glpi-db printenv MARIADB_ROOT_PASSWORD)"
ARQ="$DESTINO/glpidb-$(date +%Y%m%d-%H%M%S).sql.gz"
docker exec glpi-db mariadb-dump -uroot -p"$SENHA" --single-transaction --routines glpidb | gzip > "$ARQ"
chmod 600 "$ARQ"
# retenção: 14 dias
find "$DESTINO" -name 'glpidb-*.sql.gz' -mtime +14 -delete
echo "Backup criado: $ARQ"
