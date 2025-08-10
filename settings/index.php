<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireLogin();
checkAdminAccess();

$pageTitle = 'Configurações do Sistema';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?= $pageTitle ?></h1>
            </div>
            
            <div class="row">
                <!-- Dados da Empresa -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                                    <i class="bi bi-building text-primary" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <h5 class="card-title mb-0">Dados da Empresa</h5>
                                    <p class="card-text text-muted">Informações básicas da empresa</p>
                                </div>
                            </div>
                            <p class="card-text">Configure nome, CNPJ, endereço, telefone e outros dados da empresa.</p>
                            <a href="company.php" class="btn btn-primary">
                                <i class="bi bi-gear"></i> Configurar
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Configurações do Catálogo -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success bg-opacity-10 rounded-circle p-3 me-3">
                                    <i class="bi bi-shop text-success" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <h5 class="card-title mb-0">Catálogo Online</h5>
                                    <p class="card-text text-muted">Configurações da loja online</p>
                                </div>
                            </div>
                            <p class="card-text">Configure taxa de entrega, cores, redes sociais e aparência do catálogo.</p>
                            <a href="catalog.php" class="btn btn-success">
                                <i class="bi bi-shop"></i> Configurar
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Bairros e Taxas -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning bg-opacity-10 rounded-circle p-3 me-3">
                                    <i class="bi bi-geo-alt text-warning" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <h5 class="card-title mb-0">Bairros e Taxas</h5>
                                    <p class="card-text text-muted">Gerenciar áreas de entrega</p>
                                </div>
                            </div>
                            <p class="card-text">Cadastre bairros, defina taxas de entrega e tempos de delivery.</p>
                            <a href="neighborhoods.php" class="btn btn-warning">
                                <i class="bi bi-geo-alt"></i> Gerenciar
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Conexão com Banco -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-info bg-opacity-10 rounded-circle p-3 me-3">
                                    <i class="bi bi-database text-info" style="font-size: 1.5rem;"></i>
                                </div>
                                <div>
                                    <h5 class="card-title mb-0">Banco de Dados</h5>
                                    <p class="card-text text-muted">Configurações de conexão</p>
                                </div>
                            </div>
                            <p class="card-text">Configure conexões com MySQL, PostgreSQL ou mantenha SQLite.</p>
                            <a href="database.php" class="btn btn-info">
                                <i class="bi bi-database"></i> Configurar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>