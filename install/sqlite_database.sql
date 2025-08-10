-- ERP System SQLite Database Schema
-- Run this file to create the complete database structure

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role TEXT DEFAULT 'employee' CHECK(role IN ('admin','employee')),
  active INTEGER DEFAULT 1,
  last_login DATETIME DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
INSERT OR IGNORE INTO users (username, password, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Categories table
CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(255) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  stock INTEGER NOT NULL DEFAULT 0,
  category VARCHAR(100) DEFAULT NULL,
  description TEXT,
  image VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Customers table
CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  address TEXT,
  birth_date DATE DEFAULT NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(255) NOT NULL,
  contact_person VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  cnpj VARCHAR(20) DEFAULT NULL,
  address TEXT,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Sales table
CREATE TABLE IF NOT EXISTS sales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER DEFAULT NULL,
  user_id INTEGER NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) DEFAULT 0.00,
  total DECIMAL(10,2) NOT NULL,
  payment_method TEXT NOT NULL CHECK(payment_method IN ('dinheiro','cartao_credito','cartao_debito','pix')),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Sale items table
CREATE TABLE IF NOT EXISTS sale_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  quantity INTEGER NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Financial transactions table
CREATE TABLE IF NOT EXISTS financial_transactions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  type TEXT NOT NULL CHECK(type IN ('income','expense')),
  category VARCHAR(100) DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL,
  description TEXT,
  reference_type TEXT DEFAULT NULL CHECK(reference_type IN ('sale','purchase','manual','salary','tax','other') OR reference_type IS NULL),
  reference_id INTEGER DEFAULT NULL,
  user_id INTEGER DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Cash registers table
CREATE TABLE IF NOT EXISTS cash_registers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  initial_amount DECIMAL(10,2) NOT NULL,
  current_amount DECIMAL(10,2) NOT NULL,
  final_amount DECIMAL(10,2) DEFAULT NULL,
  difference DECIMAL(10,2) DEFAULT NULL,
  status TEXT DEFAULT 'open' CHECK(status IN ('open','closed')),
  notes TEXT,
  opened_at DATETIME DEFAULT NULL,
  closed_at DATETIME DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Cash movements table
CREATE TABLE IF NOT EXISTS cash_movements (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  register_id INTEGER NOT NULL,
  type TEXT NOT NULL CHECK(type IN ('in','out')),
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (register_id) REFERENCES cash_registers(id)
);

-- Inventory adjustments table
CREATE TABLE IF NOT EXISTS inventory_adjustments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL,
  old_quantity INTEGER NOT NULL,
  new_quantity INTEGER NOT NULL,
  reason TEXT,
  user_id INTEGER DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Purchase orders table
CREATE TABLE IF NOT EXISTS purchase_orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  supplier_id INTEGER DEFAULT NULL,
  total DECIMAL(10,2) NOT NULL,
  status TEXT DEFAULT 'pendente' CHECK(status IN ('pendente','confirmado','recebido','cancelado')),
  order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  received_date DATETIME DEFAULT NULL,
  notes TEXT,
  user_id INTEGER DEFAULT NULL,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Purchase items table
CREATE TABLE IF NOT EXISTS purchase_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  purchase_order_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  quantity INTEGER NOT NULL,
  cost_price DECIMAL(10,2) NOT NULL,
  received_quantity INTEGER DEFAULT 0,
  FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Promotions table
CREATE TABLE IF NOT EXISTS promotions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  discount_type TEXT NOT NULL CHECK(discount_type IN ('percentage','fixed')),
  discount_value DECIMAL(10,2) NOT NULL,
  start_date DATETIME DEFAULT NULL,
  end_date DATETIME DEFAULT NULL,
  active INTEGER DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Promotion products table
CREATE TABLE IF NOT EXISTS promotion_products (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  promotion_id INTEGER NOT NULL,
  product_id INTEGER NOT NULL,
  FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Delivery orders table
CREATE TABLE IF NOT EXISTS delivery_orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sale_id INTEGER NOT NULL,
  delivery_address TEXT NOT NULL,
  customer_phone VARCHAR(20) DEFAULT NULL,
  delivery_fee DECIMAL(10,2) DEFAULT 0.00,
  delivery_status TEXT DEFAULT 'pendente' CHECK(delivery_status IN ('pendente','preparando','enviado','entregue','cancelado')),
  delivery_person VARCHAR(255) DEFAULT NULL,
  estimated_time INTEGER DEFAULT NULL,
  notes TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  delivered_at DATETIME DEFAULT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id)
);

-- Audit logs table (using TEXT for JSON in SQLite)
CREATE TABLE IF NOT EXISTS audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  table_name VARCHAR(50) DEFAULT NULL,
  record_id INTEGER DEFAULT NULL,
  old_values TEXT DEFAULT NULL, -- JSON as TEXT
  new_values TEXT DEFAULT NULL, -- JSON as TEXT
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Sample categories
INSERT OR IGNORE INTO categories (name, description) VALUES
('Alimentação', 'Produtos alimentícios'),
('Bebidas', 'Bebidas em geral'),
('Limpeza', 'Produtos de limpeza'),
('Higiene', 'Produtos de higiene pessoal'),
('Diversos', 'Produtos diversos');

-- Sample products
INSERT OR IGNORE INTO products (name, price, stock, category, description) VALUES
('Arroz Branco 5kg', 15.99, 50, 'Alimentação', 'Arroz branco tipo 1'),
('Feijão Preto 1kg', 8.50, 30, 'Alimentação', 'Feijão preto selecionado'),
('Coca-Cola 2L', 7.99, 25, 'Bebidas', 'Refrigerante Coca-Cola 2 litros'),
('Detergente Líquido', 3.25, 40, 'Limpeza', 'Detergente líquido neutro'),
('Sabonete', 2.99, 60, 'Higiene', 'Sabonete antibacteriano'),
('Açúcar Cristal 1kg', 4.99, 35, 'Alimentação', 'Açúcar cristal refinado'),
('Óleo de Soja 900ml', 6.49, 20, 'Alimentação', 'Óleo de soja refinado'),
('Água Mineral 500ml', 1.99, 100, 'Bebidas', 'Água mineral natural'),
('Papel Higiênico 4 Rolos', 12.99, 15, 'Higiene', 'Papel higiênico folha dupla'),
('Macarrão Espaguete 500g', 4.99, 45, 'Alimentação', 'Macarrão espaguete');