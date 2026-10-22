<#
    Atualiza o Cargo (title) e o Departamento (department) dos usuários no Active Directory
    a partir da exportação do RH (20/10/2026). Depois disso, o GLPI puxa os dois na sincronização.

    Como usar (num servidor com o módulo ActiveDirectory, por quem administra o AD):
      1) Simular (não altera nada):   .\Atualizar-CargosAD.ps1
      2) Aplicar de verdade:           .\Atualizar-CargosAD.ps1 -Aplicar

    Encontra o usuário pelo e-mail (mail); se não achar, pelo login (sAMAccountName = começo do e-mail).
    Não cria usuário. Gera um relatório CSV ao lado do script.
#>
param([switch]$Aplicar)

Import-Module ActiveDirectory -ErrorAction Stop

$pessoas = @(
    @{ Nome = 'Alanne Victória Santos de Oliveira Rodrigues'; Email = 'alanne.rodrigues@unitysaude.com.br'; Cargo = 'Supervisora de Conformidade'; Departamento = 'Conformidade Jurídica' }
    @{ Nome = 'Alessandra da Silva Matos'; Email = 'alessandra.matos@unitysaude.com.br'; Cargo = 'Enfermeiro Auditor'; Departamento = 'Regulação' }
    @{ Nome = 'Alex William da Silva Ribeiro'; Email = 'alex.ribeiro@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Amanda dos Santos Freire'; Email = 'amanda.freire@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Amanda Lima Fernandes'; Email = 'amanda.fernandes@unitysaude.com.br'; Cargo = 'Analista de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Amanda Souza Dias'; Email = 'amanda.souza@unitysaude.com.br'; Cargo = 'Recepcionista'; Departamento = 'Recepção' }
    @{ Nome = 'Ana Beatriz Fernandes Gomes'; Email = 'ana.beatriz@unitysaude.com.br'; Cargo = 'Concierge'; Departamento = 'Concierge' }
    @{ Nome = 'Ana Caroline Rodrigues de Matos'; Email = 'ana.matos@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Cadastro' }
    @{ Nome = 'Ana Clara Gonçalves Andrade Silva'; Email = 'ana.silva@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Concierge' }
    @{ Nome = 'Ana Júlia Magalhães dos Santos Neiva'; Email = 'ana.neiva@unitysaude.com.br'; Cargo = 'Auxiliar Administrativo'; Departamento = 'Vendas' }
    @{ Nome = 'Ana Karine Coelho Palmeira de Souza'; Email = 'ana.souza@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Ananda Santana Meireles'; Email = 'ananda.meireles@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'André William Leandro Ribeiro'; Email = 'andre.ribeiro@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'SAC' }
    @{ Nome = 'Anna Byancka Marques dos Santos'; Email = 'annabyancka.santos@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Aysllan Tenório de Oliveira'; Email = 'aysllan.oliveira@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Administrativo' }
    @{ Nome = 'Bárbara Kathleen Maranhão de Sousa'; Email = 'barbara.sousa@unitysaude.com.br'; Cargo = 'Enfermeiro Auditor'; Departamento = 'Regulação' }
    @{ Nome = 'Bianca Thayse Silva Lima'; Email = 'bianca.lima@unitysaude.com.br'; Cargo = 'Assistente de Conformidade'; Departamento = 'Cadastro' }
    @{ Nome = 'Breno Matheus Silva Ribeiro'; Email = 'breno.ribeiro@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Bruna Katarina Ribeiro da Silva'; Email = 'bruna.silva@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Camila de Souza Dornelas da Costa'; Email = 'camila.costa@unitysaude.com.br'; Cargo = 'Analista de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Camila Veiga de Souza'; Email = 'camila.souza@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Camilla Diniz Nery de Brito'; Email = 'camilla.brito@unitysaude.com.br'; Cargo = 'Supervisor(a) de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Camilly dos Santos Diniz Barbosa'; Email = 'camilly.barbosa@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Financeiro' }
    @{ Nome = 'Carlos Alexandre Santana Correa'; Email = 'carlos.correa@unitysaude.com.br'; Cargo = 'Jovem Aprendiz'; Departamento = 'Administrativo' }
    @{ Nome = 'Caroliny Araújo Alves'; Email = 'caroliny.alves@unitysaude.com.br'; Cargo = 'Analista de Credenciamento'; Departamento = 'Credenciamento' }
    @{ Nome = 'Cláudia Rodrigues dos Santos'; Email = 'claw7840@gmail.com'; Cargo = 'Copeira'; Departamento = 'Administrativo' }
    @{ Nome = 'Cristiane Da Silva Oliveira'; Email = 'cristiane.oliveira@unitysaude.com.br'; Cargo = 'Executiva Assistencial'; Departamento = 'Credenciamento' }
    @{ Nome = 'Daniela Queiroz'; Email = 'daniela.queiroz@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Débora Guimarães Galvão'; Email = 'debora.galvao@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Douglas de Sousa Claudino'; Email = 'douglas.claudino@unitysaude.com.br'; Cargo = 'Analista de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Dúlio César Lopes de Santana'; Email = 'dulio.santana@unitysaude.com.br'; Cargo = 'CEO'; Departamento = 'Diretoria' }
    @{ Nome = 'Ediny Priscila Ferreira'; Email = 'ediny.ferreira@unitysaude.com.br'; Cargo = 'Concierge'; Departamento = 'Concierge' }
    @{ Nome = 'Eduardo Endrill Bessa Teixeira'; Email = 'eduardo.teixeira@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Eduardo Petrus Da Silva Macedo'; Email = 'eduardo.macedo@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Eliane de Freire Assis'; Email = 'eliane.assis@unitysaude.com.br'; Cargo = 'Analista de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Eliane Gomes da Rosa'; Email = 'eliane.rosa@unitysaude.com.br'; Cargo = 'Gerente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Emileidy de Sousa Soares Viana'; Email = 'emileidy.viana@unitysaude.com.br'; Cargo = 'Diretoria'; Departamento = 'Diretoria' }
    @{ Nome = 'Erica Nunes'; Email = 'erica.nunes@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Erick Vinicius Dos Santos Gomes'; Email = 'erick.gomes@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Érika Marques Barreto dos Santos'; Email = 'barretoerika0209@gmail.com'; Cargo = 'Copeira'; Departamento = 'Administrativo' }
    @{ Nome = 'Ester Masai Pereira Alves'; Email = 'ester.pereira@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Evellyn Paula Pereira De Souza'; Email = 'evellyn.souza@unitysaude.com.br'; Cargo = 'Executiva Assistencial'; Departamento = 'Credenciamento' }
    @{ Nome = 'Fernanda Carina Silva Gomes'; Email = 'fernanda.gomes@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Gabriela de Souza Pereira'; Email = 'gabriela.pereira@unitysaude.com.br'; Cargo = 'Concierge'; Departamento = 'SAC' }
    @{ Nome = 'Gabrieli Alves Aguiar'; Email = 'gabrieli.aguiar@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Gente e Cultura'; Email = 'gente.cultura@unitysaude.com.br'; Cargo = 'Analista de Recursos Humanos'; Departamento = 'Comunicação e Cultura' }
    @{ Nome = 'Geovani Rodrigues de Souza'; Email = 'geovani.souza@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Vendas' }
    @{ Nome = 'Gersica Lourenço de Novais'; Email = 'gersica.novais@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Giovana Belmonte Dias Machado'; Email = 'giovana.machado@unitysaude.com.br'; Cargo = 'Jovem Aprendiz'; Departamento = 'Regulação' }
    @{ Nome = 'Giovanna de Moraes Zamprogno'; Email = 'giovanna.zamprogno@unitysaude.com.br'; Cargo = 'Analista de Recursos Humanos'; Departamento = 'Gente e Cultura' }
    @{ Nome = 'Hebert Mendes dos Santos'; Email = 'hebert.santos@unitysaude.com.br'; Cargo = 'Gerente de Vendas'; Departamento = 'Vendas' }
    @{ Nome = 'Hellen Cristina da Silva'; Email = 'hellen.silva@unitysaude.com.br'; Cargo = 'Assistente de Conformidade'; Departamento = 'Conformidade Jurídica' }
    @{ Nome = 'Hellen Reis'; Email = 'hellen.reis@unitysaude.com.br'; Cargo = 'Supervisor(a) de Crônicos'; Departamento = 'Regulação' }
    @{ Nome = 'Heloísa Cordeiro'; Email = 'heloisa.cordeiro@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Hingred Silva'; Email = 'hingred.silva@unitysaude.com.br'; Cargo = 'Processos'; Departamento = 'Conformidade Jurídica' }
    @{ Nome = 'Iasmim da Silva Ferreira'; Email = 'iasmim.ferreira@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Igor Gabriel Veloso Pereira'; Email = 'igor.pereira@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Izadora Mirian Ribeiro Targino'; Email = 'izadora.targino@unitysaude.com.br'; Cargo = 'Auxiliar Administrativo'; Departamento = 'SAC' }
    @{ Nome = 'Jean Fábio Lima'; Email = 'jean.lima@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Jenifer Mikaele Silva Alves'; Email = 'jenifer.alves@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'SAC' }
    @{ Nome = 'Jennyfer Carvalho de Araujo'; Email = 'jennyfer.araujo@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Jéssica de Fátima Rodrigues'; Email = 'jessica.rodrigues@unitysaude.com.br'; Cargo = 'Coordenador(a) de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'João Antônio Sousa Silva'; Email = 'joao.silva@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Cadastro' }
    @{ Nome = 'João Vítor Silva dos Santos'; Email = 'joao.santos@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Joelma Moura Silva Gomes'; Email = 'joelma.gomes@unitysaude.com.br'; Cargo = 'Coordenador de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Joelma Oliveira Marques'; Email = 'joelma.marques@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'José Augusto Nascimento Sousa'; Email = 'jose.sousa@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Diretoria' }
    @{ Nome = 'José Carlos dos Santos Sousa'; Email = 'jose.santos@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Júlia Lane de Sousa Lopes'; Email = 'julia.lopes@unitysaude.com.br'; Cargo = 'Executiva de Vendas'; Departamento = 'Vendas' }
    @{ Nome = 'Juliana Aparecida de Oliveira Peixoto Chianca'; Email = 'juliana.chianca@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Juliana Ayres de Souza'; Email = 'juliana.souza@unitysaude.com.br'; Cargo = 'Enfermeiro Auditor'; Departamento = 'Regulação' }
    @{ Nome = 'Juliana Ferreira Andrade'; Email = 'juliana.andrade@unitysaude.com.br'; Cargo = 'Coordenador de Credenciamento'; Departamento = 'Credenciamento' }
    @{ Nome = 'Juliana Queiroz da Silva'; Email = 'juliana.silva@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Kauany Gonçalves Alves da Silva'; Email = 'kauany.silva@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'Regulação' }
    @{ Nome = 'Laís de Almeida Muribeca'; Email = 'lais.muribeca@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Larissa Alves dos Santos'; Email = 'larissa.santos@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Larissa Regina da Silva Siqueira'; Email = 'larissa.siqueira@unitysaude.com.br'; Cargo = 'Diretoria'; Departamento = 'Operações' }
    @{ Nome = 'Larissa Santos do Nascimento'; Email = 'larissa.nascimento@unitysaude.com.br'; Cargo = 'Recepcionista'; Departamento = 'Recepção' }
    @{ Nome = 'Lavinia Cordeiro da Cruz'; Email = 'lavinia.cruz@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Lavinia Rodrigues Filgueiras'; Email = 'lavinia.filgueiras@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Layane Campos Pereira'; Email = 'layane.pereira@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Leticia Daniel Barcellos de Abreu'; Email = 'leticia.barcellos@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Lorena Oliveira Pereira'; Email = 'lorena.pereira@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Cadastro' }
    @{ Nome = 'Luana Alves de Sousa'; Email = 'luana.sousa@unitysaude.com.br'; Cargo = 'Analista de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Luanna Victória Santana de França'; Email = 'luanna.franca@unitysaude.com.br'; Cargo = 'Assistente de Recursos Humanos'; Departamento = 'Gente e Cultura' }
    @{ Nome = 'Lucas Henrique Thallys Sousa Silva'; Email = 'lucas.silva@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Ludmila Stefani Rocha Marques'; Email = 'ludmila.marques@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Luiz Fernando Gomes dos Santos'; Email = 'luiz.santos@unitysaude.com.br'; Cargo = 'Concierge'; Departamento = 'Concierge' }
    @{ Nome = 'Luiz Henrique Santos Sena'; Email = 'luiz.sena@unitysaude.com.br'; Cargo = 'Diretoria'; Departamento = 'Tecnologia e Inovação' }
    @{ Nome = 'Luiz Matos'; Email = 'luiz.matos@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Financeiro' }
    @{ Nome = 'Marcelo Henrique Oliveira Medeiros'; Email = 'marcelo.medeiros@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Márcia Rodrigues da Costa'; Email = 'marcia.rodrigues@unitysaude.com.br'; Cargo = 'Diretoria'; Departamento = 'Conformidade Jurídica' }
    @{ Nome = 'Maria Aparecida Costa Rufino'; Email = 'maria.rufino@unitysaude.com.br'; Cargo = 'Executiva de Vendas'; Departamento = 'Vendas' }
    @{ Nome = 'Maria Eduarda Costa Souza'; Email = 'maria.souza@unitysaude.com.br'; Cargo = 'Estagiário'; Departamento = 'Regulação' }
    @{ Nome = 'Maria Eduarda Sales Costa'; Email = 'maria.costa@unitysaude.com.br'; Cargo = 'Jovem Aprendiz'; Departamento = 'Credenciamento' }
    @{ Nome = 'Maria Elisângela da Silva'; Email = 'silvamaryelis@gmail.com'; Cargo = 'Copeira'; Departamento = 'Administrativo' }
    @{ Nome = 'Maria Júlia Pereira Pinheiro'; Email = 'maria.pinheiro@unitysaude.com.br'; Cargo = 'Jovem Aprendiz'; Departamento = 'Vendas' }
    @{ Nome = 'Mariana Gomes Lima'; Email = 'mariana.lima@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Mariana Ramos Fernandes Lins'; Email = 'mariana.lins@unitysaude.com.br'; Cargo = 'Analista de Marketing'; Departamento = 'Marketing' }
    @{ Nome = 'Mariany Aparecida da Silva Cardoso Diamantino'; Email = 'mariany.diamantino@unitysaude.com.br'; Cargo = 'Auxiliar Administrativo'; Departamento = 'Cadastro' }
    @{ Nome = 'Matheus Gomes de Santana'; Email = 'matheus.santana@unitysaude.com.br'; Cargo = 'Auxiliar Administrativo'; Departamento = 'Administrativo' }
    @{ Nome = 'Matheus Guilherme da Silva Sousa'; Email = 'matheus.sousa@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Matheus Mesquita'; Email = 'matheus.mesquita@unitysaude.com.br'; Cargo = 'Técnico de TI'; Departamento = 'Tecnologia e Inovação' }
    @{ Nome = 'Melina da Silva Meira'; Email = 'melina.meira@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Vendas' }
    @{ Nome = 'Mikaele Martins Sabóia'; Email = 'mikaele.saboia@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Milson Coelho Mendes'; Email = 'milson.mendes@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Monica Dias Almeida'; Email = 'monica.almeida@unitysaude.com.br'; Cargo = 'Enfermeiro Auditor'; Departamento = 'Regulação' }
    @{ Nome = 'Nara Tayane Marques da Costa'; Email = 'nara.costa@unitysaude.com.br'; Cargo = 'Concierge'; Departamento = 'Concierge' }
    @{ Nome = 'Nathalia Carvalho Quintino'; Email = 'nathalia.quintino@unitysaude.com.br'; Cargo = 'Enfermeiro Auditor'; Departamento = 'Regulação' }
    @{ Nome = 'Nathalia Pereira Dias'; Email = 'nathalia.dias@unitysaude.com.br'; Cargo = 'Analista de Recursos Humanos'; Departamento = 'Gente e Cultura' }
    @{ Nome = 'Nicoly Sofia Lopes'; Email = 'nicoly.lopes@unitysaude.com.br'; Cargo = 'Jovem Aprendiz'; Departamento = 'Vendas' }
    @{ Nome = 'Paola Moreira Coelho'; Email = 'paola.coelho@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Regulação' }
    @{ Nome = 'Patricia Fernandes Gomes'; Email = 'patricia.gomes@unitysaude.com.br'; Cargo = 'Coordenador de Cadastro'; Departamento = 'Cadastro' }
    @{ Nome = 'Patrícia Maria de Moura'; Email = 'patricia.moura@unitysaude.com.br'; Cargo = 'Analista de Credenciamento'; Departamento = 'Credenciamento' }
    @{ Nome = 'Patrícia Martins Papa'; Email = 'patricia.papa@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Patricia Oliveira da Silva'; Email = 'patricia.silva@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Financeiro' }
    @{ Nome = 'Petrus Sampaio'; Email = 'petrus.sampaio@unitysaude.com.br'; Cargo = 'Jovem Aprendiz'; Departamento = 'Conformidade Jurídica' }
    @{ Nome = 'Phelipe Augusto Fonseca Caetano'; Email = 'phelipe.caetano@unitysaude.com.br'; Cargo = 'Analista Financeiro'; Departamento = 'Financeiro' }
    @{ Nome = 'Priscielle Santos de Araujo'; Email = 'priscielle.araujo@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Priscila Mizuki Kuda'; Email = 'priscila.kuda@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Conformidade Jurídica' }
    @{ Nome = 'Priscilla Silva de Oliveira'; Email = 'priscilla.oliveira@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Rafaela Dias de Oliveira'; Email = 'rafaela.oliveira@unitysaude.com.br'; Cargo = 'Enfermeiro Auditor'; Departamento = 'Regulação' }
    @{ Nome = 'Rafaely Lorrane Amaral Rodrigues'; Email = 'rafaely.rodrigues@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Raquel Cardoso Dias'; Email = 'raquel.dias@unitysaude.com.br'; Cargo = 'DPO'; Departamento = 'Conformidade Jurídica' }
    @{ Nome = 'Raquel Oliveira Soares'; Email = 'raquel.soares@unitysaude.com.br'; Cargo = 'Enfermeiro Auditor'; Departamento = 'Regulação' }
    @{ Nome = 'Rebeca Felix da Silva'; Email = 'rebeca.silva@unitysaude.com.br'; Cargo = 'Assistente de Ouvidoria'; Departamento = 'Conformidade Jurídica' }
    @{ Nome = 'Reinaldo Alves Rocha'; Email = 'reinaldo.rocha@unitysaude.com.br'; Cargo = 'Coordenador Administrativo'; Departamento = 'Administrativo' }
    @{ Nome = 'Renata Duarte Caixeta'; Email = 'renata.caixeta@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Cadastro' }
    @{ Nome = 'Renata Rodrigues Leite'; Email = 'renata.leite@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Cadastro' }
    @{ Nome = 'Ronnierick Peterson Souza de Almeida'; Email = 'ronnierick.almeida@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Rubenia dos Santos Leite'; Email = 'rubenia.leite@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Sabrina Maria da Costa Sousa'; Email = 'sabrina.sousa@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Cadastro' }
    @{ Nome = 'Sâmalla Serracena Brito'; Email = 'samalla.brito@unitysaude.com.br'; Cargo = 'Assistente de Contas Médicas'; Departamento = 'Contas Médicas' }
    @{ Nome = 'Samuel Malveira dos Santos'; Email = 'samuel.santos@unitysaude.com.br'; Cargo = 'Auxiliar Administrativo'; Departamento = 'Vendas' }
    @{ Nome = 'Stephanie De Freitas Dantas'; Email = 'stephanie.dantas@unitysaude.com.br'; Cargo = 'Analista Financeiro'; Departamento = 'Financeiro' }
    @{ Nome = 'Stephanny Ágatha Brandão Borges'; Email = 'stephanny.borges@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Sthefanne Dos Santos Jansen'; Email = 'sthefanne.jansen@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Cadastro' }
    @{ Nome = 'Suellen Lima'; Email = 'suellen.lima@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Sumi Danitsa Champac Lozano'; Email = 'sumi.lozano@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Tatiana Rodrigues Bandeira'; Email = 'tatiana.bandeira@unitysaude.com.br'; Cargo = 'Analista Financeiro'; Departamento = 'Financeiro' }
    @{ Nome = 'Thainara Aragao Aguiar'; Email = 'thainara.aguiar@unitysaude.com.br'; Cargo = 'Assistente Financeiro'; Departamento = 'Financeiro' }
    @{ Nome = 'Thaissa Kaylanne dos Santos Oliveira'; Email = 'thaissa.oliveira@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'SAC' }
    @{ Nome = 'Thamara Rodrigues da Silva'; Email = 'thamara.silva@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Thaynara Araujo da Silva'; Email = 'thaynara.silva@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
    @{ Nome = 'Unity Saúde | Marketing'; Email = 'marketing@unitysaude.com.br'; Cargo = 'Analista de Marketing'; Departamento = 'Marketing' }
    @{ Nome = 'Victor Hugo Veiga dos Santos'; Email = 'victor.veiga@unitysaude.com.br'; Cargo = 'Supervisor de Credenciamento'; Departamento = 'Credenciamento' }
    @{ Nome = 'Vinicius Gomes Gonçalves'; Email = 'vinicius.gomes@unitysaude.com.br'; Cargo = 'Auxiliar Administrativo'; Departamento = 'Vendas' }
    @{ Nome = 'Vinicius Gonçalves'; Email = 'vinicius.goncalves@unitysaude.com.br'; Cargo = 'Estagiário'; Departamento = 'Regulação' }
    @{ Nome = 'vivaweb'; Email = 'vivaweb@suporte.com.br'; Cargo = 'Auxiliar Administrativo'; Departamento = 'Vendas' }
    @{ Nome = 'Vivian de Souza Paranhos Melo'; Email = 'vivian.melo@unitysaude.com.br'; Cargo = 'Assistente de Regulação'; Departamento = 'Regulação' }
    @{ Nome = 'Viviani Watzel Martins'; Email = 'viviani.martins@unitysaude.com.br'; Cargo = 'Analista de Relacionamento'; Departamento = 'Vendas' }
    @{ Nome = 'Weber Lima'; Email = 'weber.lima@unitysaude.com.br'; Cargo = 'Diretoria'; Departamento = 'Negócios Assistenciais' }
    @{ Nome = 'Wendell Victor de Brito dos Santos'; Email = 'wendell.santos@unitysaude.com.br'; Cargo = 'Assistente Administrativo'; Departamento = 'Concierge' }
    @{ Nome = 'Weudes Santos Martins'; Email = 'weudes.santos@unitysaude.com.br'; Cargo = 'Coordenador de TI'; Departamento = 'Tecnologia e Inovação' }
    @{ Nome = 'Weverton Silva'; Email = 'weverton.silva@unitysaude.com.br'; Cargo = 'Técnico de TI'; Departamento = 'Tecnologia e Inovação' }
    @{ Nome = 'Williams Cidrim do Nascimento'; Email = 'williams.nascimento@unitysaude.com.br'; Cargo = 'Analista Jurídico'; Departamento = 'Conformidade Jurídica' }
    @{ Nome = 'Yanna Carvalho'; Email = 'yanna.carvalho@unitysaude.com.br'; Cargo = 'Enfermeiro Auditor'; Departamento = 'Regulação' }
    @{ Nome = 'Yasmim Larissa Souza Aguiar'; Email = 'yasmim.aguiar@unitysaude.com.br'; Cargo = 'Auxiliar Administrativo'; Departamento = 'SAC' }
    @{ Nome = 'Yasmin Bezerra Souza'; Email = 'yasmin.bezerra@unitysaude.com.br'; Cargo = 'Operador de Atendimento'; Departamento = 'SAC' }
)

$relatorio = foreach ($p in $pessoas) {
    $u = Get-ADUser -Filter "mail -eq '$($p.Email)'" -Properties mail, title, department
    $como = 'e-mail'
    if (-not $u) {
        $login = $p.Email.Split('@')[0]
        $u = Get-ADUser -Filter "sAMAccountName -eq '$login'" -Properties mail, title, department
        $como = 'login'
    }
    if (-not $u) {
        [pscustomobject]@{ Nome = $p.Nome; Email = $p.Email; Resultado = 'NÃO ENCONTRADO NO AD'; Cargo = $p.Cargo; Departamento = $p.Departamento; Login = '' }
        continue
    }
    if (@($u).Count -gt 1) {
        [pscustomobject]@{ Nome = $p.Nome; Email = $p.Email; Resultado = 'MAIS DE UM USUÁRIO NO AD'; Cargo = $p.Cargo; Departamento = $p.Departamento; Login = ($u.SamAccountName -join ', ') }
        continue
    }
    if ($Aplicar) {
        Set-ADUser -Identity $u -Title $p.Cargo -Department $p.Departamento
        $res = "ATUALIZADO (achado por $como)"
    } else {
        $res = "SIMULAÇÃO: seria atualizado (achado por $como; hoje: '$($u.title)' / '$($u.department)')"
    }
    [pscustomobject]@{ Nome = $p.Nome; Email = $p.Email; Resultado = $res; Cargo = $p.Cargo; Departamento = $p.Departamento; Login = $u.SamAccountName }
}

$arquivo = Join-Path $PSScriptRoot ("relatorio_cargos_ad_{0:yyyyMMdd_HHmm}.csv" -f (Get-Date))
$relatorio | Export-Csv -Path $arquivo -Delimiter ';' -NoTypeInformation -Encoding UTF8
$relatorio | Group-Object { ($_.Resultado -split ' ')[0] } | ForEach-Object { "{0}: {1}" -f $_.Name, $_.Count }
"Relatório: $arquivo"
if (-not $Aplicar) { "Nada foi alterado. Para aplicar: .\Atualizar-CargosAD.ps1 -Aplicar" }