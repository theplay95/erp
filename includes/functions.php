<?php
/**
 * Common functions used throughout the ERP system
 */



function isLoggedInNew() {
    return isset($_SESSION['user_id']);
}





// formatCurrency function moved to config/database.php to avoid conflicts

function formatPhone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) == 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
    } elseif (strlen($phone) == 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
    }
    return $phone;
}

function sanitizeInput($input) {
    return trim(strip_tags($input));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateOrderId() {
    return date('YmdHis') . rand(100, 999);
}

function getDeliveryFeeByNeighborhood($neighborhood) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT delivery_fee FROM neighborhoods WHERE name = ? AND active = 1");
        $stmt->execute([$neighborhood]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $result['delivery_fee'];
        }
        
        // Return default fee if neighborhood not found
        $stmt = $pdo->prepare("SELECT delivery_fee FROM catalog_settings WHERE id = 1");
        $stmt->execute();
        $settings = $stmt->fetch();
        return $settings ? $settings['delivery_fee'] : 5.00;
        
    } catch (Exception $e) {
        return 5.00; // Default fallback
    }
}

function getCompanySettings() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM company_settings WHERE id = 1");
        $stmt->execute();
        return $stmt->fetch() ?: [
            'company_name' => 'Mundo da Carne',
            'address' => 'Av Mamoré, 3180 - Centro',
            'phone' => '(69) 0000-0000',
            'cnpj' => '00.000.000/0001-00'
        ];
    } catch (Exception $e) {
        return [
            'company_name' => 'Mundo da Carne',
            'address' => 'Av Mamoré, 3180 - Centro',
            'phone' => '(69) 0000-0000',
            'cnpj' => '00.000.000/0001-00'
        ];
    }
}

function getCatalogSettings() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM catalog_settings WHERE id = 1");
        $stmt->execute();
        return $stmt->fetch() ?: [
            'site_title' => 'Mundo da Carne - Catálogo Online',
            'delivery_fee' => 5.00,
            'primary_color' => '#dc3545',
            'secondary_color' => '#6c757d'
        ];
    } catch (Exception $e) {
        return [
            'site_title' => 'Mundo da Carne - Catálogo Online',
            'delivery_fee' => 5.00,
            'primary_color' => '#dc3545',
            'secondary_color' => '#6c757d'
        ];
    }
}
?>