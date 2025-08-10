<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
checkAdminAccess();

$pageTitle = 'Configurações de Banco de Dados';

// Processar formulário
if ($_POST) {
    try {
        if (isset($_POST['test_connection'])) {
            // Testar conexão
            $test_result = testDatabaseConnection($_POST);
            if ($test_result['success']) {
                $success = "Conexão testada com sucesso! " . $test_result['message'];
            } else {
                $error = "Erro na conexão: " . $test_result['message'];
            }
        } elseif (isset($_POST['save_config'])) {
            // Salvar configuração
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO database_config 
                (id, db_type, host, port, database_name, username, password, connection_string, is_active) 
                VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $connection_string = buildConnectionString($_POST);
            
            $stmt->execute([
                $_POST['db_type'],
                $_POST['host'] ?? null,
                $_POST['port'] ?? null,
                $_POST['database_name'] ?? null,
                $_POST['username'] ?? null,
                $_POST['password'] ?? null,
                $connection_string,
                isset($_POST['is_active']) ? 1 : 0
            ]);
            
            logAudit($_SESSION['user_id'], 'UPDATE', 'database_config', 1, 
                     json_encode(['action' => 'update_db_config', 'db_type' => $_POST['db_type']]), $_SERVER['REMOTE_ADDR']);
            
            $success = "Configuração de banco salva com sucesso!";
            
            if (isset($_POST['is_active'])) {
                $success .= " ATENÇÃO: Você precisa reiniciar o sistema para aplicar as mudanças.";
            }
        }
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
    }
}

// Buscar configuração atual
$stmt = $pdo->prepare("SELECT * FROM database_config WHERE id = 1");
$stmt->execute();
$db_config = $stmt->fetch();

// Configuração padrão SQLite se não existir
if (!$db_config) {
    $db_config = [
        'db_type' => 'sqlite',
        'host' => '',
        'port' => '',
        'database_name' => 'database/erp_system.sqlite',
        'username' => '',
        'password' => '',
        'is_active' => 1
    ];
}

function testDatabaseConnection($config) {
    try {
        switch ($config['db_type']) {
            case 'mysql':
                $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database_name']}";
                $pdo = new PDO($dsn, $config['username'], $config['password']);
                return ['success' => true, 'message' => 'Conexão MySQL estabelecida com sucesso!'];
                
            case 'postgresql':
                $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database_name']}";
                $pdo = new PDO($dsn, $config['username'], $config['password']);
                return ['success' => true, 'message' => 'Conexão PostgreSQL estabelecida com sucesso!'];
                
            case 'sqlite':
                $dsn = "sqlite:{$config['database_name']}";
                $pdo = new PDO($dsn);
                return ['success' => true, 'message' => 'Arquivo SQLite acessível!'];
                
            default:
                return ['success' => false, 'message' => 'Tipo de banco não suportado'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function buildConnectionString($config) {
    switch ($config['db_type']) {
        case 'mysql':
            return "mysql:host={$config['host']};port={$config['port']};dbname={$config['database_name']}";
        case 'postgresql':
            return "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database_name']}";
        case 'sqlite':
            return "sqlite:{$config['database_name']}";
        default:
            return '';
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="bi bi-database"></i> <?= $pageTitle ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/settings/" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Configuração da Conexão</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="dbConfigForm">
                                <div class="mb-3">
                                    <label class="form-label">Tipo de Banco de Dados *</label>
                                    <select class="form-select" name="db_type" id="dbType" onchange="toggleFields()">
                                        <option value="sqlite" <?= $db_config['db_type'] == 'sqlite' ? 'selected' : '' ?>>SQLite (Recomendado)</option>
                                        <option value="mysql" <?= $db_config['db_type'] == 'mysql' ? 'selected' : '' ?>>MySQL</option>
                                        <option value="postgresql" <?= $db_config['db_type'] == 'postgresql' ? 'selected' : '' ?>>PostgreSQL</option>
                                    </select>
                                </div>
                                
                                <!-- Campos SQLite -->
                                <div id="sqliteFields" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Caminho do Arquivo *</label>
                                        <input type="text" class="form-control" name="database_name" 
                                               value="<?= htmlspecialchars($db_config['database_name'] ?? 'database/erp_system.sqlite') ?>"
                                               placeholder="database/erp_system.sqlite">
                                        <div class="form-text">Caminho relativo ao arquivo SQLite</div>
                                    </div>
                                </div>
                                
                                <!-- Campos MySQL/PostgreSQL -->
                                <div id="serverFields" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label class="form-label">Servidor *</label>
                                                <input type="text" class="form-control" name="host" 
                                                       value="<?= htmlspecialchars($db_config['host'] ?? '') ?>"
                                                       placeholder="localhost">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Porta</label>
                                                <input type="number" class="form-control" name="port" 
                                                       value="<?= $db_config['port'] ?? '' ?>"
                                                       placeholder="3306">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Nome do Banco *</label>
                                        <input type="text" class="form-control" name="database_name" 
                                               value="<?= htmlspecialchars($db_config['database_name'] ?? '') ?>"
                                               placeholder="erp_system">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Usuário *</label>
                                                <input type="text" class="form-control" name="username" 
                                                       value="<?= htmlspecialchars($db_config['username'] ?? '') ?>"
                                                       placeholder="root">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Senha</label>
                                                <input type="password" class="form-control" name="password" 
                                                       value="<?= htmlspecialchars($db_config['password'] ?? '') ?>"
                                                       placeholder="••••••••">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" 
                                               id="isActive" <?= $db_config['is_active'] ? 'checked' : '' ?>>
                                        <label class="form-check-label text-warning" for="isActive">
                                            <strong>Ativar esta configuração (requer reinicialização do sistema)</strong>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" name="test_connection" class="btn btn-outline-primary">
                                        <i class="bi bi-plug"></i> Testar Conexão
                                    </button>
                                    <button type="submit" name="save_config" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Salvar Configuração
                                    </button>
                                    <a href="/settings/index.php" class="btn btn-secondary">
                                        <i class="bi bi-x-lg"></i> Cancelar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Status Atual</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Banco Ativo:</strong><br>
                                <?php if ($db_config['db_type'] == 'sqlite'): ?>
                                    <span class="badge bg-success">SQLite</span>
                                <?php elseif ($db_config['db_type'] == 'mysql'): ?>
                                    <span class="badge bg-primary">MySQL</span>
                                <?php elseif ($db_config['db_type'] == 'postgresql'): ?>
                                    <span class="badge bg-info">PostgreSQL</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Arquivo/Servidor:</strong><br>
                                <code><?= htmlspecialchars($db_config['host'] ?: $db_config['database_name']) ?></code>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Informações</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Atenção:</strong> Mudanças na conexão do banco podem afetar o funcionamento do sistema.
                            </div>
                            
                            <h6>Tipos suportados:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check text-success"></i> SQLite (padrão)</li>
                                <li><i class="bi bi-check text-success"></i> MySQL 5.7+</li>
                                <li><i class="bi bi-check text-success"></i> PostgreSQL 12+</li>
                            </ul>
                            
                            <div class="alert alert-info mt-3">
                                <i class="bi bi-info-circle"></i>
                                Teste sempre a conexão antes de ativar!
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function toggleFields() {
    const dbType = document.getElementById('dbType').value;
    const sqliteFields = document.getElementById('sqliteFields');
    const serverFields = document.getElementById('serverFields');
    
    if (dbType === 'sqlite') {
        sqliteFields.style.display = 'block';
        serverFields.style.display = 'none';
        
        // Clear server fields
        serverFields.querySelectorAll('input').forEach(input => {
            if (input.name !== 'database_name') input.value = '';
        });
    } else {
        sqliteFields.style.display = 'none';
        serverFields.style.display = 'block';
        
        // Set default ports
        const portInput = document.querySelector('input[name="port"]');
        if (dbType === 'mysql' && !portInput.value) {
            portInput.value = '3306';
        } else if (dbType === 'postgresql' && !portInput.value) {
            portInput.value = '5432';
        }
        
        // Clear SQLite database name for server fields
        const dbNameInput = serverFields.querySelector('input[name="database_name"]');
        if (dbNameInput.value.includes('.sqlite')) {
            dbNameInput.value = '';
        }
    }
}

// Initialize fields on load
document.addEventListener('DOMContentLoaded', function() {
    toggleFields();
});
</script>

<?php include '../includes/footer.php'; ?>