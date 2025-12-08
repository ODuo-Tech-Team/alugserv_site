-- =============================================
-- ALUGSERV - DATABASE SCHEMA
-- =============================================
-- Execute este arquivo no MySQL para criar as tabelas
-- O banco de dados já deve existir (criado pelo hosting)

-- IMPORTANTE: No phpMyAdmin da Hostinger, selecione o banco
-- u557425238_alugserv antes de executar este script.
-- NÃO execute as linhas CREATE DATABASE / USE em produção.

-- =============================================
-- TABELA: USUÁRIOS ADMIN
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'editor') DEFAULT 'editor',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir usuário admin padrão (senha: admin123)
-- Hash gerado com: password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO users (username, email, password, name, role, status) VALUES
('admin', 'admin@alugserv.com.br', '$2y$10$xLJmYBKPZ5J3LpXwKqwBheGv2fYBPgAMEn4N0LYpGMqBm6NTfPdJu', 'Administrador', 'admin', 'active');

-- =============================================
-- TABELA: CATEGORIAS DE EQUIPAMENTOS
-- =============================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    image VARCHAR(255) NULL,
    icon VARCHAR(50) NULL,
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir categorias padrão
INSERT INTO categories (name, slug, description, icon, sort_order, status) VALUES
('Acesso e Elevação', 'acesso-e-elevacao', 'Equipamentos para trabalho em altura com segurança', 'shield-check', 1, 'active'),
('Andaimes', 'andaimes', 'Estruturas modulares para diversos tipos de obra', 'grid', 2, 'active'),
('Compactação', 'compactacao', 'Rolos e placas compactadoras para preparação de solo', 'layers', 3, 'active'),
('Concretagem', 'concretagem', 'Betoneiras e equipamentos para preparo de concreto', 'box', 4, 'active'),
('Containers', 'containers', 'Containers para armazenamento e escritório', 'archive', 5, 'active'),
('Jardinagem', 'jardinagem', 'Ferramentas para cuidado de áreas verdes', 'flower', 6, 'active'),
('Escoras', 'escoras', 'Sistemas de escoramento para lajes e estruturas', 'settings', 7, 'active'),
('Ferramentas Elétricas', 'ferramentas-eletricas', 'Furadeiras, lixadeiras, serras e muito mais', 'zap', 8, 'active'),
('Furação e Demolição', 'furacao-e-demolicao', 'Marteletes e equipamentos de alto impacto', 'hammer', 9, 'active'),
('Geradores / Bombas / Compressores', 'geradores-bombas-compressores', 'Equipamentos para energia e movimentação de fluidos', 'cpu', 10, 'active'),
('Limpeza', 'limpeza', 'Lavadoras de alta pressão e equipamentos de limpeza', 'sparkles', 11, 'active');

-- =============================================
-- TABELA: EQUIPAMENTOS
-- =============================================
CREATE TABLE IF NOT EXISTS equipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT NULL,
    short_description VARCHAR(500) NULL,
    category_id INT NOT NULL,
    image VARCHAR(255) NULL,
    gallery JSON NULL,
    price DECIMAL(10,2) NULL,
    price_type ENUM('daily', 'weekly', 'monthly', 'custom') DEFAULT 'daily',
    sku VARCHAR(50) NULL,
    brand VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    specs JSON NULL,
    stock_status ENUM('available', 'rented', 'maintenance', 'unavailable') DEFAULT 'available',
    featured TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices para performance
CREATE INDEX idx_equipments_category ON equipments(category_id);
CREATE INDEX idx_equipments_status ON equipments(status);
CREATE INDEX idx_equipments_slug ON equipments(slug);
CREATE INDEX idx_categories_slug ON categories(slug);

-- =============================================
-- TABELA: SESSÕES (para tokens de autenticação)
-- =============================================
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_sessions_token ON sessions(token);
CREATE INDEX idx_sessions_expires ON sessions(expires_at);

-- =============================================
-- TABELA: LOG DE ATIVIDADES
-- =============================================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_activity_log_user ON activity_log(user_id);
CREATE INDEX idx_activity_log_entity ON activity_log(entity_type, entity_id);

-- =============================================
-- TABELA: CONFIGURAÇÕES DO SITE
-- =============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configurações padrão
INSERT INTO settings (setting_key, setting_value, setting_type) VALUES
('site_name', 'AlugServ', 'text'),
('site_description', 'Locação de equipamentos para construção civil', 'text'),
('whatsapp_louveira', '5519994455111', 'text'),
('whatsapp_jundiai', '5511964801527', 'text'),
('phone_louveira', '(19) 9944-5111', 'text'),
('phone_jundiai', '(11) 96480-1527', 'text'),
('address_louveira', 'R. Bento Martins Cruz, 58 – Vila Pasti, Louveira – SP', 'text'),
('address_jundiai', 'Av. Jundiaí, 1520 – Anhangabaú, Jundiaí – SP', 'text'),
('email', 'contato@alugserv.com.br', 'text'),
('facebook', 'https://facebook.com/alugserv', 'text'),
('instagram', 'https://instagram.com/alugserv', 'text');
