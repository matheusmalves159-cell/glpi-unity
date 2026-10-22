# Checklist antes de ir para produção

Nada vai para produção sem este checklist completo e sem decisão explícita.

## Antes
- [ ] Pull Request aprovado e `main` atualizada; tag criada (`vAAAA.MM.DD`)
- [ ] Backup do banco de produção feito e guardado (`docs/BACKUPS.md`)
- [ ] Combinado o horário com quem usa (fora do horário de pico)
- [ ] Plano de volta: tag anterior e backup à mão

## Publicação
- [ ] `./scripts/deploy-plugin.sh <plugin>` para cada plugin alterado (versão aumentada)
- [ ] Limpar o cache do GLPI

## Depois (conferir com conta de teste)
- [ ] Login de usuário comum, de supervisor e de diretor
- [ ] Abrir chamado por uma tela de setor e acompanhar até a solução
- [ ] Notificações: e-mail ao atribuir e aviso de SLA na tela
- [ ] Reserva de sala e relatórios abrem sem erro
- [ ] Logs sem erro novo (`php-errors.log`)
- [ ] Sincronização com o AD rodou (ação automática)

## Se algo der errado
Voltar a tag anterior (`git checkout vAAAA.MM.DD`), publicar de novo e restaurar o backup se houve mudança de dados.
