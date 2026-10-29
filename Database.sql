-- REGUA & MACHADO - INSTALACAO LIMPA DO BANCO DE DADOS
-- Cria as seis tabelas e cadastra seis profissionais e seis servicos iniciais.
-- Clientes, agendamentos e movimentos de caixa comecam vazios.
-- ATENCAO: execute SOMENTE em uma instalacao nova, com banco vazio.
-- NAO execute no forja_corte_dev atual nem depois de importar forja_corte_dev.sql.
-- Este script nao apaga tabelas existentes nem e adequado para atualizar bancos.
-- Para restaurar registros de teste, use a exportacao forja_corte_dev.sql.
-- A conexao em Config.php deve apontar para o banco forja_corte_dev.
CREATE DATABASE IF NOT EXISTS `forja_corte_dev` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `forja_corte_dev`;

CREATE TABLE `clientes` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `telefone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `data_cadastro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `profissionais` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `especialidade` VARCHAR(100) DEFAULT NULL,
  `ativo` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `servicos` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(100) NOT NULL,
  `duracao` INT NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  `ativo` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `agendamentos` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` INT DEFAULT NULL,
  `profissional_id` INT DEFAULT NULL,
  `data_agendamento` DATE NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fim` TIME NOT NULL,
  `status` ENUM('agendado','confirmado','aguardando','em_atendimento','finalizado','pago','cancelado','faltou') DEFAULT 'agendado',
  `observacao` TEXT DEFAULT NULL,
  `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `agendamentos_ibfk_2` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `agendamento_servicos` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `agendamento_id` INT DEFAULT NULL,
  `servico_id` INT DEFAULT NULL,
  `profissional_id` INT DEFAULT NULL,
  `hora_inicio` TIME DEFAULT NULL,
  `hora_fim` TIME DEFAULT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  INDEX `idx_servico_profissional` (`profissional_id`),
  CONSTRAINT `agendamento_servicos_ibfk_1` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agendamento_servicos_ibfk_2` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`),
  CONSTRAINT `agendamento_servicos_profissional_fk` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `caixa` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `data_movimento` DATE NOT NULL,
  `titulo` VARCHAR(100) NOT NULL,
  `forma_pagamento` ENUM('dinheiro','debito','credito','pix') NOT NULL,
  `valor` DECIMAL(10,2) NOT NULL,
  `tipo` ENUM('entrada','saida') DEFAULT 'entrada',
  `observacao` TEXT DEFAULT NULL,
  `agendamento_id` INT DEFAULT NULL,
  `cliente_id` INT DEFAULT NULL,
  `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `caixa_ibfk_1` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `caixa_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------
-- DADOS INICIAIS PARA USAR O PROTOTIPO EM UMA INSTALACAO LIMPA
-- IDs estaveis de referencia: 1 a 6 para profissionais e servicos.
-- Os valores sao ficticios e podem ser modificados em uma futura versao.
-- ----------------------------------------------------------------------

INSERT INTO `profissionais` (`id`, `nome`, `especialidade`, `ativo`) VALUES
(1, 'João', 'Cortes Masculinos', 1),
(2, 'Maria', 'Colorização', 1),
(3, 'Carlos', 'Cortes Femininos', 1),
(4, 'Tiago Melo', 'Barboterapia', 1),
(5, 'Estevan Araujo', 'Cortes', 1),
(6, 'Alice Azevedo', 'Hidratação', 1);

INSERT INTO `servicos` (`id`, `nome`, `duracao`, `valor`, `ativo`) VALUES
(1, 'Corte Masculino', 30, 35.00, 1),
(2, 'Corte Feminino', 60, 80.00, 1),
(3, 'Barba', 30, 25.00, 1),
(4, 'Barboterapia', 40, 60.00, 1),
(5, 'Hidratação', 45, 70.00, 1),
(6, 'Coloração', 120, 120.00, 1);
