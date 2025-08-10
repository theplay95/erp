<?php
require_once '../config/database.php';
require_once '../config/session.php';

// Set JSON header
header('Content-Type: application/json');

// Require login for all API calls
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = Database::getInstance();
    
    switch ($method) {
        case 'GET':
            handleGet($pdo);
            break;
        case 'POST':
            handlePost($pdo, $input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

function handleGet($pdo) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'summary':
            getFinancialSummary($pdo);
            break;
        case 'chart_data':
            getChartData($pdo);
            break;
        case 'export':
            exportData($pdo);
            break;
        case 'categories':
            getCategories($pdo);
            break;
        default:
            getTransactions($pdo);
    }
}

function handlePost($pdo, $input) {
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create_transaction':
            createTransaction($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
}

function getTransactions($pdo) {
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $type = $_GET['type'] ?? '';
    $category = $_GET['category'] ?? '';
    
    $sql = "
        SELECT ft.*, u.name as user_name
        FROM financial_transactions ft
        LEFT JOIN users u ON ft.user_id = u.id
        WHERE 1=1
    ";
    $params = [];
    
    if ($start_date && $end_date) {
        $sql .= " AND DATE(ft.created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    if ($type && in_array($type, ['income', 'expense'])) {
        $sql .= " AND ft.type = ?";
        $params[] = $type;
    }
    
    if ($category) {
        $sql .= " AND ft.category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY ft.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $transactions
    ]);
}

function getFinancialSummary($pdo) {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
            COUNT(CASE WHEN type = 'income' THEN 1 END) as income_count,
            COUNT(CASE WHEN type = 'expense' THEN 1 END) as expense_count
        FROM financial_transactions 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $summary = $stmt->fetch();
    
    // Category breakdown
    $stmt = $pdo->prepare("
        SELECT 
            category,
            type,
            SUM(amount) as total_amount,
            COUNT(*) as transaction_count
        FROM financial_transactions 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY category, type
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $categories = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'categories' => $categories
    ]);
}

function getChartData($pdo) {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $period = $_GET['period'] ?? 'daily'; // daily, weekly, monthly
    
    $dateFormat = '%Y-%m-%d';
    $dateInterval = 'DATE(created_at)';
    
    switch ($period) {
        case 'weekly':
            $dateFormat = '%Y-%u';
            $dateInterval = 'YEARWEEK(created_at)';
            break;
        case 'monthly':
            $dateFormat = '%Y-%m';
            $dateInterval = 'DATE_FORMAT(created_at, "%Y-%m")';
            break;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            $dateInterval as period,
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
        FROM financial_transactions 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY $dateInterval
        ORDER BY period
    ");
    $stmt->execute([$start_date, $end_date]);
    $data = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'period' => $period
    ]);
}

function getCategories($pdo) {
    $stmt = $pdo->query("
        SELECT category, COUNT(*) as usage_count
        FROM financial_transactions 
        WHERE category IS NOT NULL 
        GROUP BY category 
        ORDER BY usage_count DESC
    ");
    $categories = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $categories
    ]);
}

function createTransaction($pdo, $input) {
    $type = $input['type'] ?? '';
    $category = trim($input['category'] ?? '');
    $amount = floatval($input['amount'] ?? 0);
    $description = trim($input['description'] ?? '');
    $reference_type = $input['reference_type'] ?? null;
    $reference_id = intval($input['reference_id'] ?? 0) ?: null;
    
    // Validation
    if (!in_array($type, ['income', 'expense'])) {
        echo json_encode(['success' => false, 'message' => 'Tipo de transação inválido']);
        return;
    }
    
    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Categoria é obrigatória']);
        return;
    }
    
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valor deve ser maior que zero']);
        return;
    }
    
    if (empty($description)) {
        echo json_encode(['success' => false, 'message' => 'Descrição é obrigatória']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO financial_transactions (type, category, amount, description, reference_type, reference_id, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $type,
            $category,
            $amount,
            $description,
            $reference_type,
            $reference_id,
            $_SESSION['user_id']
        ]);
        
        $transaction_id = $pdo->lastInsertId();
        
        logAudit('create_transaction_api', 'financial_transactions', $transaction_id, null, [
            'type' => $type,
            'category' => $category,
            'amount' => $amount
        ]);
        
        // Get created transaction
        $stmt = $pdo->prepare("
            SELECT ft.*, u.name as user_name
            FROM financial_transactions ft
            LEFT JOIN users u ON ft.user_id = u.id
            WHERE ft.id = ?
        ");
        $stmt->execute([$transaction_id]);
        $transaction = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Transação criada com sucesso',
            'data' => $transaction
        ]);
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao criar transação']);
    }
}

function exportData($pdo) {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as data,
                type as tipo,
                category as categoria,
                amount as valor,
                description as descricao,
                reference_type as referencia_tipo,
                reference_id as referencia_id
            FROM financial_transactions 
            WHERE DATE(created_at) BETWEEN ? AND ?
            ORDER BY created_at
        ");
        $stmt->execute([$start_date, $end_date]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="transacoes_financeiras_' . $start_date . '_' . $end_date . '.csv"');
        
        // Output CSV
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers
        fputcsv($output, ['Data', 'Tipo', 'Categoria', 'Valor', 'Descrição', 'Tipo Referência', 'ID Referência'], ';');
        
        // Data
        foreach ($transactions as $transaction) {
            $transaction['tipo'] = $transaction['tipo'] === 'income' ? 'Receita' : 'Despesa';
            $transaction['valor'] = number_format($transaction['valor'], 2, ',', '.');
            fputcsv($output, $transaction, ';');
        }
        
        fclose($output);
        exit();
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro ao exportar dados']);
    }
}
?>
