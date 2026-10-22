#!/bin/bash
# Recoloca a marca da empresa (logos e favicon) no contêiner do GLPI.
# Necessário depois de RECRIAR o contêiner (docker compose up com mudança de configuração);
# um simples restart não apaga nada. Os arquivos originais ficam no volume ./data.
set -e
cd "$(dirname "$0")"
ORIG=data/files/_plugins/brandkit/logos
for f in "$ORIG"/logo-*.png; do
  docker cp "$f" glpi:/var/www/glpi/public/pics/logos/$(basename "$f")
done
[ -f "$ORIG/favicon.ico" ] && docker cp "$ORIG/favicon.ico" glpi:/var/www/glpi/public/pics/favicon.ico
docker exec -u 0 glpi sh -c 'chown www-data:www-data /var/www/glpi/public/pics/favicon.ico /var/www/glpi/public/pics/logos/logo-*.png'
docker exec -u www-data glpi sh -c "mkdir -p /var/www/glpi/plugins/brandkit/public/js && cp /var/www/glpi/plugins/brandkit/js/*.js /var/www/glpi/plugins/brandkit/public/js/" || true
docker exec -u www-data glpi php bin/console cache:clear < /dev/null > /dev/null 2>&1 || true
echo "marca restaurada"
