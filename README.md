# GLPI Unity Saúde

Personalizações do GLPI 11 da Unity Saúde: plugins próprios, configuração do ambiente de homologação e documentação.

> **Regra de ouro:** tudo é feito e testado em **homologação**. Nada vai para produção sem decisão explícita. Dados reais (usuários, chamados, planilhas do RH, backups) **nunca** entram neste repositório.

## O que tem aqui

| Pasta | Conteúdo |
|---|---|
| `plugins/abrirchamado` | Menu, perfis (aba "Módulos da Empresa"), avisos na tela, sincronização diária com o AD, categoria de usuário por setor, situação dos computadores, ajustes visuais e de português |
| `plugins/chamadoti` | "Chamado por setor": Central de telas (formulários por setor, campos próprios, cores) e abertura de chamados |
| `plugins/relatorios` | Painel de relatórios de chamados |
| `plugins/salasreuniao` | Reserva de salas de reunião com chamado e agenda |
| `plugins/pagamentos` | Solicitações de pagamento e planilha |
| `infra/` | `docker-compose.yml`, Apache, PHP e `.env.example` do ambiente de homologação |
| `scripts/` | Publicação de plugin no contêiner e restauração da marca |
| `tools/` | Scripts de apoio (tradução, notificações, imagens de e-mail, cargos do AD) |
| `docs/` | Estrutura de grupos e perfis, rotina de publicação e decisões |

O plugin **Brandkit** (logos e tela de login) é de terceiros e não está no repositório.

## Ambiente de homologação

- GLPI 11.0.9 em Docker (WSL2), banco MariaDB, Mailpit para capturar e-mails de teste.
- Pasta do ambiente: `~/glpi-homolog` (não versionada). O `infra/docker-compose.yml` é a referência.
- Endereço local: `http://localhost:8080`. E-mails de teste: `http://127.0.0.1:8025`.

## Como publicar um plugin na homologação

```bash
./scripts/deploy-plugin.sh abrirchamado
```

O script copia o plugin para o contêiner, limpa fim de linha, ajusta permissões, valida a sintaxe, reinstala, ativa e limpa o cache.
**Sempre aumente a versão** em `setup.php` do plugin antes de publicar (isso renova o cache do navegador).

## Fluxo sugerido de trabalho

1. Crie uma branch por assunto: `feature/nome`, `fix/nome`.
2. Altere o plugin, aumente a versão e publique na homologação.
3. Teste com contas de teste (nunca com a senha de alguém).
4. Abra um Pull Request, descreva o que mudou e o que foi testado.
5. Faça o merge em `main`. Marque versões com tags (`v2026.10.22`).

## O que nunca versionar

Senhas e `.env`, dumps de banco, planilhas do RH, exportações de chamados, arquivos com CPF, endereço ou dados de saúde. O `.gitignore` já bloqueia os formatos mais comuns, mas confira antes de cada commit.

## Mais informações

- `docs/GRUPOS-E-PERFIS.md`: como os grupos e perfis estão organizados.
- `docs/BACKUPS.md`: como guardar backups do banco (fora do Git).
- `docs/CHECKLIST-PRODUCAO.md`: o que conferir antes e depois de ir para produção.
- `CHANGELOG.md`: histórico das mudanças.
