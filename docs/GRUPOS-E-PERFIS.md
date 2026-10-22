# Grupos e perfis

## Grupos: um por setor, direto sob o CEO

```
CEO
├─ Administrativo
├─ Atendimento
├─ Auditoria
│   ├─ Crônicos
│   └─ Internação
├─ Cadastro
├─ Comercial
├─ Concierge
├─ Conformidade
├─ Contas Médicas
├─ Credenciamento
├─ Financeiro
├─ Gente e Cultura
├─ Jurídico
├─ Marketing
├─ Privacidade
├─ Regulação
├─ SAC
└─ TI
    ├─ Inovação
    └─ Sustentação
```

Regras:

- **Um grupo por setor.** Não existem grupos que juntem setores ("Diretoria X e Y"): se o diretor mudar, só aquele setor é alterado.
- **Diretor** é chefe e membro de cada setor que dirige. **Coordenador e supervisor** são chefes do grupo deles.
- **Pai enxerga filhos:** os grupos com subgrupos (CEO, TI, Auditoria) têm "associação recursiva" ligada. Sem isso o GLPI não mostra os chamados dos filhos.
- **Quem recebe chamado:** só TI (com Inovação e Sustentação), Administrativo, Atendimento, Financeiro, Comercial, Conformidade, Credenciamento e Regulação. Os demais são apenas solicitantes.
- **Roteamento da TI por categoria:** Desenvolvimento, Relatórios, Contratação de Sistema, FacPlan/Portal > Relatórios e Instalação/Configuração > Site, Intranet e Aplicativo vão para Inovação; o restante vai para Sustentação.
- **Subsetores não aparecem** na lista "Setor (para onde vai)" da Central de telas; as pessoas deles contam no setor pai.

## Perfis

| Perfil | Para quem | Resumo |
|---|---|---|
| Usuários | Todos | Abrir e acompanhar os próprios chamados, FAQ |
| Atendente | Recepção | Abrir chamados e atender as reservas de sala |
| Técnico | Equipes que atendem (ex.: Administrativo) | Atender chamados, enxuto |
| Técnico de TI | TI | Atender e configurar o necessário |
| Supervisor | Coordenadores e supervisores | Ver os chamados do grupo, atribuir e aprovar |
| Diretor | Diretores | Ver todos os chamados, relatórios e estatísticas; sem configuração |
| Super-Admin | Somente a TI e o CEO | Administração completa |

O perfil padrão de cada líder é mantido por duas regras de autorização no fim da lista ("Perfil padrão - Diretores" e "Perfil padrão - Supervisores e coordenadores"). O AD reaplica o perfil padrão a cada login, por isso a regra é necessária.

## Usuários vindos do AD

- A sincronização roda todo dia entre 1h e 5h (ação automática "Sincronizar usuários com o AD", do plugin `abrirchamado`).
- Cria quem entrou, atualiza grupos e perfis pelas regras, desativa quem saiu e reativa quem voltou.
- A **categoria do usuário** segue a pasta (OU) do AD, não o "departamento" do RH. Diretores ficam em "Diretoria".
- Contas em `OU=DESATIVADOS` são bloqueadas na importação.
