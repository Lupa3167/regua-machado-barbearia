# Régua & Machado Barbearia

Projeto Interdisciplinar (PIC I) — sistema web acadêmico para gerenciamento de barbearia.

## Objetivo
Organizar o cadastro de clientes, o agendamento de serviços e profissionais, a visualização da agenda e o controle financeiro do caixa.

## Integrantes
- Murillo Gonçalves Oliveira de Souza
- Luca Coppi Barros
- Lucas Araújo Alves
- Felipe Adriano Zen
- Mariana de Moraes Ramos
- Marcos Paulo

*Relação extraída do Termo de Realização de Projeto de Extensão 1; confirmar a composição final do grupo.*

## Tecnologias
PHP, HTML, CSS, JavaScript, MySQL/MariaDB e Apache via XAMPP.

## Requisitos
- XAMPP com Apache, PHP e MariaDB/MySQL
- PHP com extensão PDO MySQL
- Navegador moderno
- Conexão à internet se bibliotecas de interface forem carregadas por CDN

## Instalação e execução
1. Instale o XAMPP e inicie Apache e MySQL.
2. Copie os arquivos do projeto para `C:\xampp\htdocs\regua-machado-dev\`.
3. Pelo phpMyAdmin, importe `sql/Database.sql` em ambiente limpo (não execute sobre um banco existente).
4. Confira as configurações de conexão em `Config.php` e adapte ao seu ambiente.
5. Acesse `http://localhost/regua-machado-dev/`.

## Funcionalidades
- Cadastro e seleção de clientes
- Agendamento de múltiplos serviços e profissionais
- Cálculo de horários, durações e valores
- Verificação de conflitos e validação de expediente (08h–19h)
- Agenda de atendimentos e atualização de status
- Controle de caixa e pagamentos

## Arquivos principais
- `index.php` e `Pagina_inicial.html`: entrada
- `Agendamento.php`, `Cad_Cli.php`, `Processar_agendamento.php`: clientes e agendamentos
- `Coresatt.php`: agenda
- `Caixa.php`: caixa
- `Config.php`: banco de dados
- `sql/Database.sql`: esquema e dados iniciais para instalação limpa

## Demonstração
As capturas de tela e o vídeo de apresentação final serão adicionados antes da entrega.

## Limitações
Protótipo acadêmico testado manualmente em XAMPP. Não é uma aplicação pronta para produção. O gerenciamento de comandas, citado no planejamento original, não integra esta versão. O backup com dados de clientes e pagamentos não deve ser publicado no GitHub.

**Atenção:** este repositório está em preparação. O código-fonte ainda precisa ser publicado antes de seu uso para avaliação.
