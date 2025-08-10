<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
checkAdminAccess();

$pageTitle = 'Configurações do Catálogo';

// Processar formulário
if ($_POST) {
    try {
        $stmt = $pdo->prepare("UPDATE catalog_settings SET 
            site_title = ?, site_description = ?, delivery_fee = ?, min_order_value = ?,
            whatsapp_number = ?, instagram_url = ?, facebook_url = ?, 
            primary_color = ?, secondary_color = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = 1");
        
        $stmt->execute([
            $_POST['site_title'],
            $_POST['site_description'],
            $_POST['delivery_fee'],
            $_POST['min_order_value'],
            $_POST['whatsapp_number'],
            $_POST['instagram_url'],
            $_POST['facebook_url'],
            $_POST['primary_color'],
            $_POST['secondary_color']
        ]);
        
        logAudit($_SESSION['user_id'], 'UPDATE', 'catalog_settings', 1, 
                 json_encode(['action' => 'update_catalog_settings']), $_SERVER['REMOTE_ADDR']);
        
        $success = "Configurações do catálogo atualizadas com sucesso!";
    } catch (Exception $e) {
        $error = "Erro ao atualizar configurações: " . $e->getMessage();
    }
}

// Buscar configurações atuais
$stmt = $pdo->prepare("SELECT * FROM catalog_settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->fetch();

if (!$settings) {
    // Criar registro inicial
    $stmt = $pdo->prepare("INSERT INTO catalog_settings (site_title) VALUES ('Mundo da Carne - Catálogo Online')");
    $stmt->execute();
    $settings = [
        'site_title' => 'Mundo da Carne - Catálogo Online',
        'site_description' => 'Os melhores produtos com entrega rápida.',
        'delivery_fee' => 5.00,
        'min_order_value' => 0.00,
        'whatsapp_number' => '',
        'instagram_url' => '',
        'facebook_url' => '',
        'primary_color' => '#dc3545',
        'secondary_color' => '#6c757d'
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
                    <i class="bi bi-shop"></i> <?= $pageTitle ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="/catalog/index.php" target="_blank" class="btn btn-success me-2">
                        <i class="bi bi-eye"></i> Visualizar Catálogo
                    </a>
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
                            <h5 class="card-title mb-0">Configurações Gerais</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Título do Site *</label>
                                    <input type="text" class="form-control" name="site_title" 
                                           value="<?= htmlspecialchars($settings['site_title']) ?>" required>
                                    <div class="form-text">Este título aparece na aba do navegador</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Descrição do Site</label>
                                    <textarea class="form-control" name="site_description" rows="2" 
                                              placeholder="Descrição que aparece nos resultados de busca"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Taxa de Entrega Padrão</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" class="form-control" name="delivery_fee" 
                                                       value="<?= $settings['delivery_fee'] ?>" step="0.01" min="0">
                                            </div>
                                            <div class="form-text">Taxa aplicada quando bairro não especificado</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Valor Mínimo do Pedido</label>
                                            <div class="input-group">
                                                <span class="input-group-text">R$</span>
                                                <input type="number" class="form-control" name="min_order_value" 
                                                       value="<?= $settings['min_order_value'] ?>" step="0.01" min="0">
                                            </div>
                                            <div class="form-text">0 = sem valor mínimo</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <h6 class="mt-4 mb-3">Redes Sociais e Contato</h6>
                                
                                <div class="mb-3">
                                    <label class="form-label">WhatsApp (com DDD)</label>
                                    <input type="tel" class="form-control" name="whatsapp_number" 
                                           value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '') ?>"
                                           placeholder="69999999999">
                                    <div class="form-text">Usado para o botão "Finalizar no WhatsApp"</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Instagram</label>
                                            <input type="url" class="form-control" name="instagram_url" 
                                                   value="<?= htmlspecialchars($settings['instagram_url'] ?? '') ?>"
                                                   placeholder="https://instagram.com/empresa">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Facebook</label>
                                            <input type="url" class="form-control" name="facebook_url" 
                                                   value="<?= htmlspecialchars($settings['facebook_url'] ?? '') ?>"
                                                   placeholder="https://facebook.com/empresa">
                                        </div>
                                    </div>
                                </div>
                                
                                <h6 class="mt-4 mb-3">Personalização Visual</h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Cor Primária</label>
                                            <div class="input-group">
                                                <input type="color" class="form-control form-control-color" name="primary_color" 
                                                       value="<?= $settings['primary_color'] ?>" style="max-width: 60px;">
                                                <input type="text" class="form-control" id="primaryColorText" 
                                                       value="<?= $settings['primary_color'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Cor Secundária</label>
                                            <div class="input-group">
                                                <input type="color" class="form-control form-control-color" name="secondary_color" 
                                                       value="<?= $settings['secondary_color'] ?>" style="max-width: 60px;">
                                                <input type="text" class="form-control" id="secondaryColorText" 
                                                       value="<?= $settings['secondary_color'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Salvar Configurações
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
                            <h6 class="card-title mb-0">Preview das Cores</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="p-3 rounded" id="primaryPreview" 
                                     style="background-color: <?= $settings['primary_color'] ?>; color: white;">
                                    <strong>Cor Primária</strong><br>
                                    Botões principais, destaques
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="p-3 rounded" id="secondaryPreview" 
                                     style="background-color: <?= $settings['secondary_color'] ?>; color: white;">
                                    <strong>Cor Secundária</strong><br>
                                    Textos auxiliares, bordas
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Informações</h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Dica:</strong> As alterações são aplicadas imediatamente no catálogo online.
                            </div>
                            
                            <h6>Funcionalidades:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check text-success"></i> Taxa por bairro</li>
                                <li><i class="bi bi-check text-success"></i> WhatsApp integrado</li>
                                <li><i class="bi bi-check text-success"></i> Cores personalizáveis</li>
                                <li><i class="bi bi-check text-success"></i> Responsivo</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Sincronizar color picker com text input
document.querySelector('input[name="primary_color"]').addEventListener('input', function() {
    document.getElementById('primaryColorText').value = this.value;
    document.getElementById('primaryPreview').style.backgroundColor = this.value;
});

document.querySelector('input[name="secondary_color"]').addEventListener('input', function() {
    document.getElementById('secondaryColorText').value = this.value;
    document.getElementById('secondaryPreview').style.backgroundColor = this.value;
});

// Sincronizar text input com color picker
document.getElementById('primaryColorText').addEventListener('input', function() {
    document.querySelector('input[name="primary_color"]').value = this.value;
    document.getElementById('primaryPreview').style.backgroundColor = this.value;
});

document.getElementById('secondaryColorText').addEventListener('input', function() {
    document.querySelector('input[name="secondary_color"]').value = this.value;
    document.getElementById('secondaryPreview').style.backgroundColor = this.value;
});
</script>

<?php include '../includes/footer.php'; ?>