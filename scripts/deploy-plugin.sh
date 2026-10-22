#!/bin/bash
# Publica um plugin deste repositório no GLPI de HOMOLOGAÇÃO (contêiner "glpi").
# Uso: ./scripts/deploy-plugin.sh abrirchamado
set -euo pipefail

PLUGIN="${1:?Informe o plugin, ex.: abrirchamado}"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
ORIGEM="$RAIZ/plugins/$PLUGIN"
CONTEINER="${GLPI_CONTAINER:-glpi}"
DESTINO="/var/www/glpi/plugins/$PLUGIN"

[ -d "$ORIGEM" ] || { echo "Plugin não encontrado: $ORIGEM"; exit 1; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
cp -r "$ORIGEM/." "$TMP/"
# fim de linha Unix e sem BOM
find "$TMP" -type f \( -name '*.php' -o -name '*.js' -o -name '*.css' -o -name '*.twig' \) \
  -exec sed -i 's/\r$//; 1s/^\xEF\xBB\xBF//' {} +

docker cp "$TMP/." "$CONTEINER:$DESTINO/"
docker exec -u 0 "$CONTEINER" chown -R 33:33 "$DESTINO"
for f in $(cd "$TMP" && find . -name '*.php' | head -200); do
  docker exec "$CONTEINER" php -l "$DESTINO/$f" > /dev/null
done
docker exec -u 33 "$CONTEINER" php bin/console plugin:install --force -u Admin "$PLUGIN" < /dev/null | tail -1
docker exec -u 33 "$CONTEINER" php bin/console plugin:activate "$PLUGIN" < /dev/null | tail -1
docker exec -u 33 "$CONTEINER" php bin/console cache:clear -q < /dev/null
echo "Plugin $PLUGIN publicado na homologação."
