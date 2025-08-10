<?php
require_once '../config/database.php';

// Create tables for SQLite
$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'user',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'company_settings' => "CREATE TABLE IF NOT EXISTS company_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        company_name TEXT NOT NULL DEFAULT 'Mundo da Carne',
        cnpj TEXT,
        address TEXT,
        phone TEXT,
        email TEXT,
        website TEXT,
        opening_hours TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'catalog_settings' => "CREATE TABLE IF NOT EXISTS catalog_settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_title TEXT NOT NULL DEFAULT 'Mundo da Carne - Catálogo Online',
        site_description TEXT,
        delivery_fee DECIMAL(10,2) DEFAULT 5.00,
        min_order_value DECIMAL(10,2) DEFAULT 0.00,
        whatsapp_number TEXT,
        instagram_url TEXT,
        facebook_url TEXT,
        primary_color TEXT DEFAULT '#dc3545',
        secondary_color TEXT DEFAULT '#6c757d',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'neighborhoods' => "CREATE TABLE IF NOT EXISTS neighborhoods (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 5.00,
        delivery_time_min INTEGER DEFAULT 30,
        delivery_time_max INTEGER DEFAULT 60,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'database_config' => "CREATE TABLE IF NOT EXISTS database_config (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        db_type TEXT NOT NULL DEFAULT 'sqlite',
        host TEXT,
        port INTEGER,
        database_name TEXT,
        username TEXT,
        password TEXT,
        connection_string TEXT,
        is_active INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'audit_logs' => "CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        table_name TEXT,
        record_id INTEGER,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'products' => "CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        cost_price DECIMAL(10,2) DEFAULT 0,
        sku TEXT UNIQUE,
        barcode TEXT,
        category_id INTEGER,
        stock_quantity INTEGER DEFAULT 0,
        min_stock_level INTEGER DEFAULT 0,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'categories' => "CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        parent_id INTEGER,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'customers' => "CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT,
        phone TEXT,
        address TEXT,
        neighborhood TEXT,
        city TEXT DEFAULT 'Porto Velho',
        state TEXT DEFAULT 'RO',
        zip_code TEXT,
        cpf_cnpj TEXT,
        birth_date DATE,
        notes TEXT,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'sales' => "CREATE TABLE IF NOT EXISTS sales (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER REFERENCES customers(id),
        user_id INTEGER REFERENCES users(id),
        total_amount DECIMAL(10,2) NOT NULL,
        discount_amount DECIMAL(10,2) DEFAULT 0,
        tax_amount DECIMAL(10,2) DEFAULT 0,
        payment_method TEXT,
        payment_status TEXT DEFAULT 'pending',
        sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'sale_items' => "CREATE TABLE IF NOT EXISTS sale_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sale_id INTEGER REFERENCES sales(id),
        product_id INTEGER REFERENCES products(id),
        quantity INTEGER NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'suppliers' => "CREATE TABLE IF NOT EXISTS suppliers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact_person TEXT,
        email TEXT,
        phone TEXT,
        address TEXT,
        city TEXT,
        state TEXT,
        zip_code TEXT,
        cnpj TEXT,
        notes TEXT,
        active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'cash_registers' => "CREATE TABLE IF NOT EXISTS cash_registers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER REFERENCES users(id),
        opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        closed_at DATETIME,
        opening_balance DECIMAL(10,2) DEFAULT 0,
        closing_balance DECIMAL(10,2),
        total_sales DECIMAL(10,2) DEFAULT 0,
        status TEXT DEFAULT 'open',
        notes TEXT
    )",
    
    'cash_movements' => "CREATE TABLE IF NOT EXISTS cash_movements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cash_register_id INTEGER REFERENCES cash_registers(id),
        type TEXT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        reference_id INTEGER,
        reference_type TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    
    'delivery_orders' => "CREATE TABLE IF NOT EXISTS delivery_orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sale_id INTEGER REFERENCES sales(id),
        customer_id INTEGER REFERENCES customers(id),
        delivery_person_id INTEGER REFERENCES users(id),
        customer_name TEXT NOT NULL,
        customer_phone TEXT,
        delivery_address TEXT NOT NULL,
        neighborhood TEXT,
        delivery_fee DECIMAL(10,2) DEFAULT 0,
        estimated_time INTEGER DEFAULT 30,
        status TEXT DEFAULT 'pendente',
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )"
];

try {
    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        echo "Tabela '$name' criada com sucesso.\n";
    }
    
    // Insert default data
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Admin', 'admin@mundodacarne.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin']);
    
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO company_settings (company_name, address, phone) VALUES (?, ?, ?)");
    $stmt->execute(['Mundo da Carne', 'Av Mamoré, 3180 - Centro, Porto Velho - RO', '(69) 0000-0000']);
    
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO catalog_settings (site_title, site_description, delivery_fee, primary_color, secondary_color) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Mundo da Carne - Catálogo Online', 'Os melhores produtos com entrega rápida.', 5.00, '#dc3545', '#6c757d']);
    
    $neighborhoods = [
        ['Centro', 3.00, 15, 30],
        ['Tancredo Neves', 5.00, 20, 40],
        ['Liberdade', 6.00, 25, 45],
        ['São Cristóvão', 7.00, 30, 50],
        ['Caiari', 4.50, 20, 35]
    ];
    
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO neighborhoods (name, delivery_fee, delivery_time_min, delivery_time_max) VALUES (?, ?, ?, ?)");
    foreach ($neighborhoods as $neighborhood) {
        $stmt->execute($neighborhood);
    }
    
    echo "Dados iniciais inseridos com sucesso.\n";
    echo "Sistema configurado para SQLite!\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>