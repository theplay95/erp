<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="bi bi-shop-window"></i>
        <span>Sistema ERP</span>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="/dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/sales/pos.php">
                    <i class="bi bi-cash-register"></i>
                    PDV (Vendas)
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/sales/index.php">
                    <i class="bi bi-receipt"></i>
                    Vendas
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/products/index.php">
                    <i class="bi bi-box-seam"></i>
                    Produtos
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/inventory/index.php">
                    <i class="bi bi-boxes"></i>
                    Estoque
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/customers/index.php">
                    <i class="bi bi-people"></i>
                    Clientes
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/suppliers/index.php">
                    <i class="bi bi-building"></i>
                    Fornecedores
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/financial/index.php">
                    <i class="bi bi-graph-up"></i>
                    Financeiro
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/cash-register/index.php">
                    <i class="bi bi-wallet2"></i>
                    Caixa
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/delivery/index.php">
                    <i class="bi bi-truck"></i>
                    Delivery
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="/reports/index.php">
                    <i class="bi bi-file-earmark-text"></i>
                    Relatórios
                </a>
            </li>
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="/users/index.php">
                    <i class="bi bi-person-gear"></i>
                    Usuários
                </a>
            </li>
            
            <!-- Configurações -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-gear"></i>
                    Configurações
                </a>
                <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                    <li><a class="dropdown-item" href="/settings/company.php">
                        <i class="bi bi-building"></i> Dados da Empresa
                    </a></li>
                    <li><a class="dropdown-item" href="/settings/catalog.php">
                        <i class="bi bi-shop"></i> Configurações do Catálogo
                    </a></li>
                    <li><a class="dropdown-item" href="/settings/neighborhoods.php">
                        <i class="bi bi-geo-alt"></i> Bairros e Taxas
                    </a></li>
                    <li><a class="dropdown-item" href="/settings/database.php">
                        <i class="bi bi-database"></i> Conexão com Banco
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/settings/backup.php">
                        <i class="bi bi-download"></i> Backup e Restauração
                    </a></li>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
</div>
