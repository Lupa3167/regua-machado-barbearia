# Régua & Machado — Barbearia

> **PIC I | Engenharia de Computação | EEP/FUMEP**  
> Protótipo acadêmico para gestão de atendimentos em barbearia.

## Objetivo

Apoiar a rotina de uma barbearia por meio de cadastro de clientes, agendamento de serviços, acompanhamento da agenda dos profissionais e registro de movimentações financeiras. O sistema é um **protótipo acadêmico executado localmente**, não uma aplicação pronta para produção.

## Integrantes do grupo

- Murillo Gonçalves Oliveira de Souza
- Luca Coppi Barros
- Lucas Araújo Alves
- Felipe Adriano Zen
- Mariana de Moraes Ramos
- Marcos Paulo

## Tecnologias

- PHP 8.2 e PDO (acesso ao banco)
- MariaDB / MySQL compatível
- Apache via XAMPP
- HTML, CSS e JavaScript
- Bootstrap 5.3.3 via CDN (internet necessária para carregar o Bootstrap)

## Requisitos

- Windows com XAMPP (ou ambiente equivalente com Apache, PHP e MariaDB/MySQL)
- Extensão PHP PDO MySQL habilitada
- Navegador atualizado
- Porta e serviço de banco disponíveis (evite conflito entre instalações de MySQL e MariaDB)

## Instalação e execução

1. Instale o XAMPP e inicie **Apache** e **MySQL** no painel.
2. Copie os arquivos deste repositório para `C:\xampp\htdocs\regua-machado-dev\`. Mantenha os PHP/HTML na raiz dessa pasta.
3. Abra `http://localhost/phpmyadmin/`.
4. Para uma **instalação limpa**, use a aba **SQL** do phpMyAdmin para executar `Database.sql` em um ambiente sem tabelas anteriores do projeto. O script cria o banco `forja_corte_dev`, as seis tabelas e os cadastros iniciais de seis profissionais e seis serviços.
5. Confira a conexão em `Config.php`: host `localhost`, banco `forja_corte_dev`, usuário e senha de seu ambiente local. A configuração incluída é apenas para o XAMPP local de desenvolvimento.
6. Abra `http://localhost/regua-machado-dev/`.

**Atenção:** não execute o script de instalação limpa sobre uma base já preenchida. Faça backup antes de qualquer restauração. O banco de testes completo não foi publicado neste repositório para evitar exposição de cadastros e movimentações. Ele deve ser compartilhado separadamente apenas se necessário para a avaliação e com dados fictícios.

## Funcionalidades

- Cadastro e seleção de clientes
- Seleção de serviços e profissionais a partir do banco
- Agendamentos com múltiplos serviços e profissionais
- Cálculo de duração, horário final e valor total
- Validação do expediente adotado (08:00–19:00) e verificação de conflitos de horários
- Visualização da agenda por data e profissional; alteração de status
- Caixa com lançamentos de entrada/saída e recebimento de agendamentos
- Registro de pagamento de agendamento, evitando duplicação pelo fluxo normal da interface

## Estrutura do projeto

| Arquivo | Responsabilidade |
|---|---|
| `index.php` / `Pagina_inicial.html` | Entrada e navegação |
| `Agendamento.php` | Formulário de agendamento |
| `Cad_Cli.php` | Cadastro de clientes |
| `Processar_agendamento.php` | Validações e persistência de agendamentos |
| `Coresatt.php` | Agenda e status |
| `Caixa.php` | Movimentações financeiras |
| `Config.php` | Conexão e consultas auxiliares |
| `Database.sql` | Criação do banco para instalação limpa |

## Exemplos de funcionamento

O funcionamento pode ser demonstrado no vídeo entregue pelo Teams ou por link externo. Capturas de tela podem ser adicionadas futuramente para apresentação no portfólio.

## Testes e limitações

Foram realizados testes manuais no XAMPP de cadastro, múltiplos serviços, conflitos de horário, limites do expediente, agenda e caixa. Não há suíte automatizada nem testes completos de segurança e concorrência. Não há autenticação de usuários, pagamento parcial ou controle independente de status por serviço.

## Vídeo de apresentação

A gravação de apresentação será entregue separadamente pelo Teams ou por link, conforme orientações do professor. Todos os integrantes devem participar com câmera aberta durante suas respectivas falas.

## Contexto acadêmico

Projeto desenvolvido para a disciplina de Projeto Interdisciplinar de Curso I (PIC I). O repositório documenta a versão acadêmica de entrega.
