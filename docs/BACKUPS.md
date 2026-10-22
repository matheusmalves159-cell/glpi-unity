# Backups do banco

**Nunca** no Git. O banco tem usuários, chamados e dados pessoais.

- Onde ficam: `~/backups-glpi/` (fora do repositório), permissão só do dono.
- Como fazer: `./scripts/backup-banco.sh homologacao`
- Quando fazer: **antes** de qualquer mudança em dados (grupos, perfis, regras, categorias, limpezas) e antes de publicar em produção.
- Retenção sugerida: 14 dias de backups diários e 6 mensais; o script apaga os mais antigos.
- Cópia fora da máquina: guarde também em armazenamento da empresa com acesso restrito (ex.: unidade protegida). Teste a restauração de vez em quando.
