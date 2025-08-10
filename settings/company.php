<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
checkAdminAccess();

$pageTitle = 'Dados da Empresa';

// Processar formulário
if ($_POST) {
    try {
        $stmt = $pdo->prepare("UPDATE company_settings SET 
            company_name = ?, cnpj = ?, address = ?, phone = ?, 
            email = ?, website = ?, opening_hours = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = 1");
        
        $stmt->execute([
            $_POST['company_name'],
            $_POST['cnpj'],
            $_POST['address'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['website'],
            $_POST['opening_hours']
        ]);
        
        logAudit($_SESSION['user_id'], 'UPDATE', 'company_settings', 1, 
                 json_encode(['action' => 'update_company_data']), $_SERVER['REMOTE_ADDR']);
        
        $success = "Dados da empresa atualizados com sucesso!";
    } catch (Exception $e) {
        $error = "Erro ao atualizar dados: " . $e->getMessage();
    }
}

// Buscar dados atuais
$stmt = $pdo->prepare("SELECT * FROM company_settings WHERE id = 1");
$stmt->execute();
$company = $stmt->fetch();

if (!$company) {
    // Criar registro inicial
    $stmt = $pdo->prepare("INSERT INTO company_settings (company_name) VALUES ('Mundo da Carne')");
    $stmt->execute();
    $company = [
        'company_name' => 'Mundo da Carne',
        'cnpj' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'website' => '',
        'opening_hours' => ''
    ];
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
                    <i class="bi bi-building"></i> <?= $pageTitle ?>
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
                            <h5 class="card-title mb-0">Informações da Empresa</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label class="form-label">Nome da Empresa *</label>
                                            <input type="text" class="form-control" name="company_name" 
                                                   value="<?= htmlspecialchars($company['company_name']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">CNPJ</label>
                                            <input type="text" class="form-control" name="cnpj" 
                                                   value="<?= htmlspecialchars($company['cnpj'] ?? '') ?>"
                                                   placeholder="00.000.000/0001-00" maxlength="18">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Endereço Completo</label>
                                    <textarea class="form-control" name="address" rows="2" 
                                              placeholder="Rua, número, bairro, cidade - UF"><?= htmlspecialchars($company['address'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Telefone</label>
                                            <input type="tel" class="form-control" name="phone" 
                                                   value="<?= htmlspecialchars($company['phone'] ?? '') ?>"
                                                   placeholder="(00) 0000-0000">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">E-mail</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?= htmlspecialchars($company['email'] ?? '') ?>"
                                                   placeholder="contato@empresa.com">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Website</label>
                                    <input type="url" class="form-control" name="website" 
                                           value="<?= htmlspecialchars($company['website'] ?? '') ?>"
                                           placeholder="https://www.empresa.com">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Horário de Funcionamento</label>
                                    <textarea class="form-control" name="opening_hours" rows="3" 
                                              placeholder="Segunda a Sexta: 08:00 às 18:00&#10;Sábado: 08:00 às 16:00&#10;Domingo: Fechado"><?= htmlspecialchars($company['opening_hours'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Salvar Alterações
                                    </button>
                                    <a href="/settings/" class="btn btn-secondary">
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
                            <h6 class="card-title mb-0">Informações</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Importante:</strong> Estes dados aparecerão no catálogo online e nas notas fiscais do sistema.
                            </div>
                            
                            <h6>Onde são utilizados:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check text-success"></i> Catálogo online</li>
                                <li><i class="bi bi-check text-success"></i> Notas fiscais</li>
                                <li><i class="bi bi-check text-success"></i> Relatórios</li>
                                <li><i class="bi bi-check text-success"></i> E-mails automáticos</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>