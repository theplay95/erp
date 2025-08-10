<?php
$pageTitle = 'Backup e Restauração';
require_once '../config/database.php';
requireLogin();
checkAdminAccess();

$message = '';
$error = '';

// Handle backup download
if (isset($_POST['create_backup'])) {
    try {
        $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sqlite';
        $source = __DIR__ . '/../database/erp_system.sqlite';
        $destination = __DIR__ . '/../uploads/' . $backup_file;
        
        // Create uploads directory if it doesn't exist
        $uploads_dir = __DIR__ . '/../uploads';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0755, true);
        }
        
        if (file_exists($source)) {
            if (copy($source, $destination)) {
                $message = "Backup criado com sucesso: $backup_file";
                
                // Log the backup creation
                logAudit($_SESSION['user_id'], 'backup_created', 'database', null, "Backup criado: $backup_file");
                
                // Force download
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $backup_file . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($destination));
                readfile($destination);
                
                // Delete temporary file
                unlink($destination);
                exit();
            } else {
                $error = "Erro ao criar backup do banco de dados.";
            }
        } else {
            $error = "Arquivo de banco de dados não encontrado.";
        }
    } catch (Exception $e) {
        $error = "Erro ao criar backup: " . $e->getMessage();
    }
}

// Handle restore upload
if (isset($_POST['restore_backup']) && isset($_FILES['backup_file'])) {
    try {
        $uploaded_file = $_FILES['backup_file'];
        
        if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
            $temp_file = $uploaded_file['tmp_name'];
            $file_name = $uploaded_file['name'];
            
            // Validate file extension
            if (pathinfo($file_name, PATHINFO_EXTENSION) !== 'sqlite') {
                $error = "Apenas arquivos .sqlite são aceitos.";
            } else {
                $target = __DIR__ . '/../database/erp_system.sqlite';
                
                // Create backup of current database before restore
                $backup_current = __DIR__ . '/../database/backup_before_restore_' . date('Y-m-d_H-i-s') . '.sqlite';
                if (file_exists($target)) {
                    copy($target, $backup_current);
                }
                
                if (move_uploaded_file($temp_file, $target)) {
                    $message = "Banco de dados restaurado com sucesso!";
                    
                    // Log the restore
                    logAudit($_SESSION['user_id'], 'backup_restored', 'database', null, "Backup restaurado: $file_name");
                } else {
                    $error = "Erro ao restaurar o banco de dados.";
                }
            }
        } else {
            $error = "Erro no upload do arquivo.";
        }
    } catch (Exception $e) {
        $error = "Erro ao restaurar backup: " . $e->getMessage();
    }
}

// Get database info
$db_file = __DIR__ . '/../database/erp_system.sqlite';
$db_size = file_exists($db_file) ? filesize($db_file) : 0;
$db_modified = file_exists($db_file) ? filemtime($db_file) : 0;

// Get backup history from audit logs
$stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE action IN ('backup_created', 'backup_restored') ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$backup_history = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Backup e Restauração</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/settings/">Configurações</a></li>
                    <li class="breadcrumb-item active">Backup e Restauração</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Gerenciar Backups</h5>
            </div>
            <div class="card-body">
                <!-- Database Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Informações do Banco de Dados</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Tipo:</strong></td>
                                <td>SQLite</td>
                            </tr>
                            <tr>
                                <td><strong>Tamanho:</strong></td>
                                <td><?= number_format($db_size / 1024, 2) ?> KB</td>
                            </tr>
                            <tr>
                                <td><strong>Última Modificação:</strong></td>
                                <td><?= $db_modified ? date('d/m/Y H:i:s', $db_modified) : 'N/A' ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Create Backup -->
                <div class="mb-4">
                    <h6>Criar Backup</h6>
                    <p class="text-muted">Gere um backup completo do banco de dados atual.</p>
                    <form method="POST" class="d-inline">
                        <button type="submit" name="create_backup" class="btn btn-success">
                            <i class="bi bi-download me-2"></i>Criar e Baixar Backup
                        </button>
                    </form>
                </div>

                <hr>

                <!-- Restore Backup -->
                <div class="mb-4">
                    <h6>Restaurar Backup</h6>
                    <p class="text-muted">Substitua o banco de dados atual por um backup anterior.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="backup_file" class="form-label">Selecione o arquivo de backup (.sqlite)</label>
                            <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sqlite" required>
                        </div>
                        <button type="submit" name="restore_backup" class="btn btn-warning" 
                                onclick="return confirm('ATENÇÃO: Esta ação substituirá todos os dados atuais. Deseja continuar?')">
                            <i class="bi bi-upload me-2"></i>Restaurar Backup
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Backup History -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Histórico de Backups</h5>
            </div>
            <div class="card-body">
                <?php if ($backup_history): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Ação</th>
                                    <th>Detalhes</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backup_history as $log): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <?php if ($log['action'] === 'backup_created'): ?>
                                                <span class="badge bg-success">Backup Criado</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Backup Restaurado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($log['details']) ?></td>
                                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Nenhum backup foi criado ou restaurado ainda.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">Informações Importantes</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Dicas de Backup:</strong>
                    <ul class="mt-2 mb-0">
                        <li>Faça backups regulares</li>
                        <li>Armazene em local seguro</li>
                        <li>Teste a restauração periodicamente</li>
                        <li>Mantenha múltiplas cópias</li>
                    </ul>
                </div>

                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Atenção:</strong>
                    A restauração de backup substitui todos os dados atuais. Um backup automático do estado atual é criado antes da restauração.
                </div>

                <h6>Backup Automático</h6>
                <p class="text-muted small">
                    Para backup automático, configure um agendamento no servidor para executar backups regulares do arquivo de banco.
                </p>

                <div class="d-grid gap-2">
                    <a href="/settings/" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Voltar às Configurações
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>