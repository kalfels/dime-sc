-- ===================================================================
-- SISTEMA DIME-SC - INSTALAÇÃO COMPLETA DO BANCO DE DADOS
-- ===================================================================
-- Arquivo consolidado para instalação completa do banco de dados
-- Execute este arquivo em um servidor MySQL/MariaDB vazio
-- Versão: 1.0.0
-- Data: Dezembro 2024
-- ===================================================================

-- Configurações iniciais
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ===================================================================
-- 1. ESTRUTURA PRINCIPAL DO BANCO (database_schema.sql)
-- ===================================================================
-- Criação do banco e todas as tabelas principais

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS `dime` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dime`;

-- =====================================================
-- CORE SYSTEM TABLES
-- =====================================================

-- Table: usuarios (Users)
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `senha_hash` varchar(255) NOT NULL,
  `role` enum('admin','operador') NOT NULL DEFAULT 'operador',
  `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `contabilista_id` int(11) DEFAULT NULL,
  `ultimo_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_contabilista` (`contabilista_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: contabilistas (Accountants)
CREATE TABLE `contabilistas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `cpf` varchar(14) NOT NULL UNIQUE,
  `crc` varchar(20) NOT NULL,
  `endereco` varchar(255) NOT NULL,
  `numero` varchar(10) NOT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `estado` char(2) NOT NULL DEFAULT 'SC',
  `cep` varchar(9) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cpf` (`cpf`),
  KEY `idx_crc` (`crc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: empresas (Companies)
CREATE TABLE `empresas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `razao_social` varchar(255) NOT NULL,
  `nome_fantasia` varchar(255) DEFAULT NULL,
  `cnpj` varchar(18) NOT NULL UNIQUE,
  `inscricao_estadual` varchar(20) NOT NULL,
  `endereco` varchar(255) NOT NULL,
  `numero` varchar(10) NOT NULL,
  `complemento` varchar(100) DEFAULT NULL,
  `bairro` varchar(100) NOT NULL,
  `cidade` varchar(100) NOT NULL,
  `estado` char(2) NOT NULL DEFAULT 'SC',
  `cep` varchar(9) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contabilista_id` int(11) NOT NULL,
  `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cnpj` (`cnpj`),
  KEY `idx_inscricao_estadual` (`inscricao_estadual`),
  KEY `idx_contabilista` (`contabilista_id`),
  FOREIGN KEY (`contabilista_id`) REFERENCES `contabilistas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- REFERENCE TABLES (CFOP, Municipalities, etc.)
-- =====================================================

-- Table: t_cfops (CFOP Codes)
CREATE TABLE `t_cfops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(4) NOT NULL UNIQUE,
  `descricao` text NOT NULL,
  `tipo` enum('entrada','saida','ambos') NOT NULL DEFAULT 'ambos',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: t_municipios (Municipalities)
CREATE TABLE `t_municipios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_ibge` varchar(7) NOT NULL UNIQUE,
  `nome` varchar(255) NOT NULL,
  `estado` char(2) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_codigo_ibge` (`codigo_ibge`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: t_codigos_receita (Revenue Codes)
CREATE TABLE `t_codigos_receita` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(10) NOT NULL UNIQUE,
  `descricao` text NOT NULL,
  `origem_dime_valida` tinyint(1) NOT NULL DEFAULT 1,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: t_classes_vencimento (Due Date Classes)
CREATE TABLE `t_classes_vencimento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(2) NOT NULL UNIQUE,
  `descricao` varchar(255) NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DIME DECLARATION TABLES
-- =====================================================

-- Recriar tabela dime_manual_versoes com estrutura correta
DROP TABLE IF EXISTS `dime_manual_versoes`;
CREATE TABLE `dime_manual_versoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `versao_completa` varchar(50) NOT NULL,
  `data_publicacao` date NOT NULL,
  `portarias_incorporadas` text DEFAULT NULL,
  `resumo_alteracoes` text DEFAULT NULL,
  `caminho_arquivo_pdf` varchar(500) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_versao` (`versao_completa`),
  KEY `idx_data_publicacao` (`data_publicacao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dime_declaracoes (DIME Declarations - Main table)
CREATE TABLE `dime_declaracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `contabilista_id` int(11) NOT NULL,
  `periodo_referencia` varchar(7) NOT NULL, -- Format: YYYY-MM
  `tipo_declaracao` enum('normal','substitutiva','complementar') NOT NULL DEFAULT 'normal',
  `regime_apuracao` enum('mensal','trimestral') NOT NULL DEFAULT 'mensal',
  `porte_empresa` enum('micro','pequena','media','grande') NOT NULL,
  `apuracao_consolidada` tinyint(1) NOT NULL DEFAULT 0,
  `apuracao_centralizada` tinyint(1) NOT NULL DEFAULT 0,
  `transferencia_creditos` tinyint(1) NOT NULL DEFAULT 0,
  `tem_creditos_presumidos` tinyint(1) NOT NULL DEFAULT 0,
  `tem_creditos_incentivos_fiscais` tinyint(1) NOT NULL DEFAULT 0,
  `movimento` tinyint(1) NOT NULL DEFAULT 1, -- 1=com movimento, 0=sem movimento
  `substituto_tributario` tinyint(1) NOT NULL DEFAULT 0,
  `tem_escrita_contabil` tinyint(1) NOT NULL DEFAULT 1,
  `quantidade_trabalhadores` int(11) DEFAULT NULL,
  `status_declaracao` enum('rascunho','validada','transmitida','processada','rejeitada') NOT NULL DEFAULT 'rascunho',
  `versao_manual_id` int(11) NOT NULL,
  `hash_arquivo` varchar(64) DEFAULT NULL,
  `data_transmissao` timestamp NULL DEFAULT NULL,
  `protocolo_receita` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_contabilista` (`contabilista_id`),
  KEY `idx_periodo` (`periodo_referencia`),
  KEY `idx_status` (`status_declaracao`),
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`contabilista_id`) REFERENCES `contabilistas` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`versao_manual_id`) REFERENCES `dime_manual_versoes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DIME QUADROS (DIME Frames/Sections)
-- =====================================================

-- Table: dime_quadro_00 (Frame 00 - Initial Declaration Information)
CREATE TABLE `dime_quadro_00` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dime_declaracao_id` int(11) NOT NULL,
  `cnpj_empresa` varchar(18) NOT NULL,
  `inscricao_estadual` varchar(20) NOT NULL,
  `razao_social` varchar(255) NOT NULL,
  `nome_fantasia` varchar(255) DEFAULT NULL,
  `endereco_completo` text NOT NULL,
  `municipio_codigo` varchar(7) NOT NULL,
  `cep` varchar(9) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `cnae_principal` varchar(10) NOT NULL,
  `regime_tributario` enum('simples','presumido','real') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_declaracao` (`dime_declaracao_id`),
  FOREIGN KEY (`dime_declaracao_id`) REFERENCES `dime_declaracoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dime_quadro_01 (Frame 01 - Tax Values Inputs)
CREATE TABLE `dime_quadro_01` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dime_declaracao_id` int(11) NOT NULL,
  `cfop_id` int(11) NOT NULL,
  `valor_contabil` decimal(15,2) NOT NULL DEFAULT 0.00,
  `base_calculo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_imposto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_isento_nao_tributado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_outras` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_declaracao` (`dime_declaracao_id`),
  KEY `idx_cfop` (`cfop_id`),
  FOREIGN KEY (`dime_declaracao_id`) REFERENCES `dime_declaracoes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cfop_id`) REFERENCES `t_cfops` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dime_quadro_02 (Frame 02 - Tax Values Outputs)
CREATE TABLE `dime_quadro_02` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dime_declaracao_id` int(11) NOT NULL,
  `cfop_id` int(11) NOT NULL,
  `valor_contabil` decimal(15,2) NOT NULL DEFAULT 0.00,
  `base_calculo` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_imposto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_isento_nao_tributado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_outras` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_declaracao` (`dime_declaracao_id`),
  KEY `idx_cfop` (`cfop_id`),
  FOREIGN KEY (`dime_declaracao_id`) REFERENCES `dime_declaracoes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cfop_id`) REFERENCES `t_cfops` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dime_quadro_04 (Frame 04 - Debit Calculation Summary)
CREATE TABLE `dime_quadro_04` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dime_declaracao_id` int(11) NOT NULL,
  `item_010_debito_saidas` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '010 - Débito pelas saídas (transportar do Quadro 03, item 080)',
  `item_020_debito_diferencial_ativo_permanente` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '020 - Débito por diferencial de alíquota de ativo permanente',
  `item_030_debito_diferencial_material_consumo` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '030 - Débito por diferencial de alíquota de material de uso ou consumo',
  `item_040_debito_maquinas_equipamentos_importados` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '040 - Débito de máquinas/equipamentos importados para ativo permanente',
  `item_045_debito_diferenca_aliquota_operacao_consumidor` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '045 - Diferença de Alíquota a Consumidor Final de Outro Estado',
  `item_050_estorno_credito` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '050 - Estorno de crédito',
  `item_060_outros_estornos_credito` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '060 - Outros estornos de crédito',
  `item_065_estorno_credito_entrada_credito_presumido` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '065 - Estorno de Crédito da Entrada em decorrência de uso de Crédito Presumido',
  `item_070_outros_debitos` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '070 - Outros débitos',
  `item_990_subtotal_debitos` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '990 - Subtotal de Débitos (calculado)',
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_declaracao` (`dime_declaracao_id`),
  FOREIGN KEY (`dime_declaracao_id`) REFERENCES `dime_declaracoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quadro 04 - Resumo da Apuração dos Débitos';

-- Table: dime_quadro_05 (Frame 05 - Credit Calculation Summary)
CREATE TABLE `dime_quadro_05` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dime_declaracao_id` int(11) NOT NULL,
  -- Estrutura expandida conforme manual
  `item_010_saldo_credor_anterior` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 010: Saldo credor do mês anterior',
  `item_020_credito_entradas` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 020: Crédito pelas entradas',
  `item_030_credito_ativo_permanente` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 030: Crédito de ativo permanente (deprecated 03/2023)',
  `item_040_credito_diferencial_aliquota` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 040: Crédito por diferencial de alíquota (deprecated 03/2023)',
  `item_045_credito_diferenca_aliquota_operacao` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 045: Diferença de alíquota a consumidor final (deprecated 03/2023)',
  `item_050_credito_icms_retido_st` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 050: Crédito ICMS-ST (deprecated 03/2023)',
  `item_060_estorno_nao_aplica` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 060: Estornos (Não se aplica)',
  `item_070_nao_aplica` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 070: (Não se aplica)',
  `item_080_creditos_presumidos` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 080: Créditos presumidos',
  `item_090_creditos_incentivos` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 090: Créditos por incentivos fiscais',
  `item_100_pagamentos_antecipados_credito` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 100: Pagamentos antecipados com direito a crédito',
  `item_110_nao_aplica` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 110: (Não se aplica)',
  `item_120_nao_aplica` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 120: (Não se aplica)',
  `item_130_nao_aplica` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 130: (Não se aplica)',
  `item_140_creditos_regime_especial` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 140: Créditos por Regime Especial',
  `item_150_outros_creditos` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 150: Outros créditos',
  `item_160_nao_aplica` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 160: (Não se aplica)',
  `item_990_subtotal_creditos` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Item 990: Subtotal de créditos',
  -- Colunas legadas mantidas para compatibilidade
  `creditos_operacoes_entradas` decimal(15,2) NOT NULL DEFAULT 0.00,
  `outros_creditos` decimal(15,2) NOT NULL DEFAULT 0.00,
  `estorno_debitos` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_creditos` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_declaracao` (`dime_declaracao_id`),
  FOREIGN KEY (`dime_declaracao_id`) REFERENCES `dime_declaracoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quadro 05 - Resumo da Apuração dos Créditos';

-- =====================================================
-- DCIP TABLES (Declaração de Crédito do ICMS Próprio)
-- =====================================================

-- Table: dcip_declaracoes (DCIP Declarations)
CREATE TABLE `dcip_declaracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `contabilista_id` int(11) NOT NULL,
  `periodo_referencia` varchar(7) NOT NULL, -- Format: YYYY-MM
  `tipo_declaracao` enum('normal','substitutiva','complementar') NOT NULL DEFAULT 'normal',
  `valor_total_creditos` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_creditos_utilizados` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo_creditos_anterior` decimal(15,2) NOT NULL DEFAULT 0.00,
  `saldo_creditos_atual` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status_declaracao` enum('rascunho','validada','transmitida','processada','rejeitada') NOT NULL DEFAULT 'rascunho',
  `hash_arquivo` varchar(64) DEFAULT NULL,
  `data_transmissao` timestamp NULL DEFAULT NULL,
  `protocolo_receita` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_contabilista` (`contabilista_id`),
  KEY `idx_periodo` (`periodo_referencia`),
  KEY `idx_status` (`status_declaracao`),
  FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`contabilista_id`) REFERENCES `contabilistas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dcip_creditos (DCIP Credit Details)
CREATE TABLE `dcip_creditos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dcip_id` int(11) NOT NULL,
  `cfop` varchar(4) NOT NULL,
  `valor_credito` decimal(15,2) NOT NULL DEFAULT 0.00,
  `origem_credito` varchar(2) NOT NULL DEFAULT '01' COMMENT '01=Aquisição, 02=Energia, 03=Outros',
  `descricao` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dcip` (`dcip_id`),
  KEY `idx_cfop` (`cfop`),
  FOREIGN KEY (`dcip_id`) REFERENCES `dcip_declaracoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dcip_utilizacao_creditos (DCIP Credit Usage)
CREATE TABLE `dcip_utilizacao_creditos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dcip_id` int(11) NOT NULL,
  `periodo_utilizacao` varchar(6) NOT NULL COMMENT 'MMAAAA format',
  `valor_utilizado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tipo_utilizacao` varchar(2) NOT NULL DEFAULT '01' COMMENT 'Type of credit usage',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dcip` (`dcip_id`),
  KEY `idx_periodo_utilizacao` (`periodo_utilizacao`),
  FOREIGN KEY (`dcip_id`) REFERENCES `dcip_declaracoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dime_quadro_11 (Frame 11 - Tax Substitution Information)
CREATE TABLE `dime_quadro_11` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dime_declaracao_id` int(11) NOT NULL,
  `cfop_id` int(11) NOT NULL,
  `valor_operacao` decimal(15,2) NOT NULL DEFAULT 0.00,
  `base_calculo_st` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_icms_st` decimal(15,2) NOT NULL DEFAULT 0.00,
  `valor_icms_proprio` decimal(15,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_declaracao` (`dime_declaracao_id`),
  KEY `idx_cfop` (`cfop_id`),
  FOREIGN KEY (`dime_declaracao_id`) REFERENCES `dime_declaracoes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`cfop_id`) REFERENCES `t_cfops` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dime_quadro_12_pagamentos (Frame 12 - Tax Payments)
CREATE TABLE `dime_quadro_12_pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dime_declaracao_id` int(11) NOT NULL,
  `origem` enum('proprio','st','outros') NOT NULL,
  `codigo_receita_id` int(11) NOT NULL,
  `classe_vencimento_id` int(11) NOT NULL,
  `data_vencimento` date NOT NULL,
  `valor` decimal(15,2) NOT NULL,
  `numero_acordo` varchar(50) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_declaracao` (`dime_declaracao_id`),
  KEY `idx_codigo_receita` (`codigo_receita_id`),
  KEY `idx_classe_vencimento` (`classe_vencimento_id`),
  FOREIGN KEY (`dime_declaracao_id`) REFERENCES `dime_declaracoes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`codigo_receita_id`) REFERENCES `t_codigos_receita` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`classe_vencimento_id`) REFERENCES `t_classes_vencimento` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: dime_quadro_48 (Frame 48 - Added Value Distribution Information)
CREATE TABLE `dime_quadro_48` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dime_declaracao_id` int(11) NOT NULL,
  `municipio_id` int(11) NOT NULL,
  `valor_adicionado` decimal(15,2) NOT NULL DEFAULT 0.00,
  `percentual_participacao` decimal(5,2) NOT NULL DEFAULT 0.00,
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_declaracao` (`dime_declaracao_id`),
  KEY `idx_municipio` (`municipio_id`),
  FOREIGN KEY (`dime_declaracao_id`) REFERENCES `dime_declaracoes` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`municipio_id`) REFERENCES `t_municipios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- AUDIT AND LOG TABLES
-- =====================================================

-- Table: system_logs (System Activity Logs)
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `tabela_afetada` varchar(100) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `dados_anteriores` json DEFAULT NULL,
  `dados_novos` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_acao` (`acao`),
  KEY `idx_tabela` (`tabela_afetada`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INITIAL DATA INSERTS
-- =====================================================

-- Insert default admin user (password: admin123)
INSERT INTO `usuarios` (`nome`, `email`, `senha_hash`, `role`, `status`) VALUES
('Administrador do Sistema', 'admin@dime-sc.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ativo');

-- Insert default manual version
INSERT INTO `dime_manual_versoes` (`versao_completa`, `data_publicacao`, `resumo_alteracoes`) VALUES
('1.0.0', CURDATE(), 'Versão inicial do sistema DIME-SC');

-- Insert common CFOP codes
INSERT INTO `t_cfops` (`codigo`, `descricao`, `tipo`) VALUES
('1102', 'Compra para comercialização', 'entrada'),
('1403', 'Compra para industrialização', 'entrada'),
('5102', 'Venda de mercadoria adquirida ou recebida de terceiros', 'saida'),
('5403', 'Venda de produto da industrialização', 'saida'),
('6102', 'Venda de mercadoria adquirida ou recebida de terceiros', 'saida'),
('6403', 'Venda de produto da industrialização', 'saida');

-- Insert common revenue codes
INSERT INTO `t_codigos_receita` (`codigo`, `descricao`) VALUES
('0001', 'ICMS - Imposto sobre Circulação de Mercadorias e Serviços'),
('0002', 'ICMS - Substituição Tributária'),
('0003', 'ICMS - Antecipação Tributária');

-- Insert due date classes
INSERT INTO `t_classes_vencimento` (`codigo`, `descricao`) VALUES
('01', 'Vencimento Normal'),
('02', 'Vencimento em Atraso'),
('03', 'Parcelamento');

-- Insert SC municipalities (sample)
INSERT INTO `t_municipios` (`codigo_ibge`, `nome`, `estado`) VALUES
('4205407', 'Florianópolis', 'SC'),
('4209102', 'Joinville', 'SC'),
('4204202', 'Criciúma', 'SC'),
('4202404', 'Blumenau', 'SC'),
('4216602', 'São José', 'SC');

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Additional indexes for better performance
CREATE INDEX idx_dime_periodo_status ON dime_declaracoes(periodo_referencia, status_declaracao);
CREATE INDEX idx_dcip_periodo_status ON dcip_declaracoes(periodo_referencia, status_declaracao);
CREATE INDEX idx_empresas_contabilista_status ON empresas(contabilista_id, status);
CREATE INDEX idx_usuarios_role_status ON usuarios(role, status);

-- ===================================================================
-- 2. MIGRAÇÕES DE CAMPOS ADICIONAIS (add_user_fields_migration.sql)
-- ===================================================================
-- Adiciona campos primeiro_acesso e status_pagamento à tabela usuarios

-- Add primeiro_acesso field (default 1 for new users, 0 for existing)
ALTER TABLE `usuarios`
ADD COLUMN `primeiro_acesso` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Flag indicating if user needs to complete first access setup';

-- Add status_pagamento field (default 'ativo' for existing users)
ALTER TABLE `usuarios`
ADD COLUMN `status_pagamento` ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo' COMMENT 'Payment status for access control';

-- Update existing admin user to have primeiro_acesso = 0 (already completed setup)
UPDATE `usuarios` SET `primeiro_acesso` = 0 WHERE `role` = 'admin';

-- Create index for better performance on login queries
CREATE INDEX idx_usuarios_primeiro_acesso ON usuarios(primeiro_acesso);
CREATE INDEX idx_usuarios_status_pagamento ON usuarios(status_pagamento);

-- ===================================================================
-- 3. MIGRAÇÃO CAMPOS RECUPERAÇÃO DE SENHA (add_password_recovery_fields_migration.sql)
-- ===================================================================
-- Adiciona campos token_redefinicao e token_expiracao à tabela usuarios

-- Add token_redefinicao field for storing password reset tokens
ALTER TABLE `usuarios`
ADD COLUMN `token_redefinicao` VARCHAR(64) NULL COMMENT 'Token for password reset functionality';

-- Add token_expiracao field for storing token expiration time
ALTER TABLE `usuarios`
ADD COLUMN `token_expiracao` TIMESTAMP NULL COMMENT 'Expiration timestamp for password reset token';

-- Create index for better performance on token lookups
CREATE INDEX idx_usuarios_token_redefinicao ON usuarios(token_redefinicao);
CREATE INDEX idx_usuarios_token_expiracao ON usuarios(token_expiracao);

-- ===================================================================
-- 4. VERIFICAÇÃO E CORREÇÃO DE CAMPOS (verify_user_fields.sql)
-- ===================================================================
-- Verifica e corrige os campos dos usuários após as migrações

-- 3. Corrigir usuários existentes se necessário
-- Para usuários admin: primeiro_acesso deve ser 0
UPDATE usuarios
SET primeiro_acesso = 0, status_pagamento = 'ativo'
WHERE role = 'admin' AND (primeiro_acesso IS NULL OR primeiro_acesso = 1);

-- Para usuários operador existentes: definir valores padrão
UPDATE usuarios
SET primeiro_acesso = 1, status_pagamento = 'ativo'
WHERE role = 'operador' AND (primeiro_acesso IS NULL OR status_pagamento IS NULL);

-- ===================================================================
-- 5. USUÁRIO DE TESTE (OPCIONAL - create_test_user.sql)
-- ===================================================================
-- Cria usuário de teste para desenvolvimento (descomente se necessário)

/*
-- Criar usuário de teste (operador com primeiro acesso)
INSERT INTO usuarios (
    nome,
    email,
    senha_hash,
    role,
    status,
    primeiro_acesso,
    status_pagamento,
    created_at,
    updated_at
) VALUES (
    'Usuário Teste Primeiro Acesso',
    'teste.primeiro@dime-sc.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: password
    'operador',
    'ativo',
    1, -- primeiro_acesso = 1
    'ativo', -- status_pagamento = ativo
    NOW(),
    NOW()
);

-- Verificar se foi criado corretamente
SELECT id, nome, email, role, status, primeiro_acesso, status_pagamento
FROM usuarios
WHERE email = 'teste.primeiro@dime-sc.com';
*/

-- ===================================================================
-- FINALIZAÇÃO DA INSTALAÇÃO
-- ===================================================================

COMMIT;

-- ===================================================================
-- VERIFICAÇÃO FINAL
-- ===================================================================
-- Execute estas consultas para verificar se tudo foi instalado corretamente

/*
-- Verificar estrutura da tabela usuarios
DESCRIBE usuarios;

-- Verificar usuários criados
SELECT id, nome, email, role, status, primeiro_acesso, status_pagamento
FROM usuarios;

-- Verificar tabelas criadas
SHOW TABLES;

-- Verificar dados iniciais
SELECT COUNT(*) as total_cfops FROM t_cfops;
SELECT COUNT(*) as total_municipios FROM t_municipios;
SELECT COUNT(*) as total_codigos_receita FROM t_codigos_receita;
SELECT COUNT(*) as total_classes_vencimento FROM t_classes_vencimento;
*/

-- ===================================================================
-- MIGRAÇÃO: ADICIONAR CONTROLE DE ACESSO DCIP
-- ===================================================================
-- Adiciona coluna acesso_dcip à tabela usuarios para controlar acesso ao menu DCIP

-- Add acesso_dcip field (default 0 for existing users, allowing granular control)
ALTER TABLE `usuarios`
ADD COLUMN `acesso_dcip` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Permite acesso ao menu DCIP (0=não, 1=sim)';

-- Update existing admin user to have acesso_dcip = 1 (admins should have access by default)
UPDATE `usuarios` SET `acesso_dcip` = 1 WHERE `role` = 'admin';

-- Create index for better performance on access control queries
CREATE INDEX idx_usuarios_acesso_dcip ON usuarios(acesso_dcip);
