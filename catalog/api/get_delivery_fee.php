<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$neighborhood = trim($input['neighborhood'] ?? '');

if (empty($neighborhood)) {
    echo json_encode([
        'success' => false,
        'fee' => 5.00,
        'message' => 'Bairro não informado, usando taxa padrão'
    ]);
    exit();
}

try {
    // First try exact match
    $stmt = $pdo->prepare("SELECT * FROM neighborhoods WHERE LOWER(name) = LOWER(?) AND active = 1");
    $stmt->execute([$neighborhood]);
    $result = $stmt->fetch();
    
    // If no exact match, try partial match
    if (!$result) {
        $stmt = $pdo->prepare("SELECT * FROM neighborhoods WHERE LOWER(name) LIKE LOWER(?) AND active = 1 ORDER BY name LIMIT 1");
        $stmt->execute(["%$neighborhood%"]);
        $result = $stmt->fetch();
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'fee' => (float)$result['delivery_fee'],
            'delivery_time_min' => (int)$result['delivery_time_min'],
            'delivery_time_max' => (int)$result['delivery_time_max'],
            'neighborhood_name' => $result['name'],
            'message' => "Taxa para {$result['name']}: R$ " . number_format($result['delivery_fee'], 2, ',', '.')
        ]);
    } else {
        // Get default neighborhoods for suggestion
        $stmt = $pdo->prepare("SELECT name FROM neighborhoods WHERE active = 1 ORDER BY name LIMIT 5");
        $stmt->execute();
        $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => false,
            'fee' => 5.00,
            'message' => 'Bairro não encontrado, usando taxa padrão',
            'suggestions' => $suggestions
        ]);
    }
} catch (Exception $e) {
    error_log("Delivery fee error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'fee' => 5.00,
        'message' => 'Erro ao consultar taxa, usando padrão'
    ]);
}
?>