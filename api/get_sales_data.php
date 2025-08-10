<?php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

// Get request parameters
$type = $_GET['type'] ?? 'monthly';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

try {
    $data = [];
    
    switch ($type) {
        case 'daily':
            // Get daily sales for current month (SQLite compatible)
            $stmt = $pdo->prepare("
                SELECT 
                    CAST(strftime('%d', created_at) AS INTEGER) as day,
                    COUNT(*) as sales_count,
                    SUM(total) as total_revenue
                FROM sales 
                WHERE strftime('%Y', created_at) = ? AND strftime('%m', created_at) = printf('%02d', ?)
                GROUP BY strftime('%d', created_at)
                ORDER BY strftime('%d', created_at)
            ");
            $stmt->execute([$year, $month]);
            $results = $stmt->fetchAll();
            
            // Fill missing days with zero values
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $dailyData = array_fill(1, $daysInMonth, ['day' => 0, 'sales_count' => 0, 'total_revenue' => 0]);
            
            foreach ($results as $row) {
                $dailyData[$row['day']] = $row;
            }
            
            $data = array_values($dailyData);
            break;
            
        case 'monthly':
            // Get monthly sales for current year (SQLite compatible)
            $stmt = $pdo->prepare("
                SELECT 
                    CAST(strftime('%m', created_at) AS INTEGER) as month,
                    COUNT(*) as sales_count,
                    SUM(total) as total_revenue
                FROM sales 
                WHERE strftime('%Y', created_at) = ?
                GROUP BY strftime('%m', created_at)
                ORDER BY strftime('%m', created_at)
            ");
            $stmt->execute([$year]);
            $results = $stmt->fetchAll();
            
            // Fill missing months with zero values
            $monthlyData = [];
            for ($i = 1; $i <= 12; $i++) {
                $monthlyData[$i] = ['month' => $i, 'sales_count' => 0, 'total_revenue' => 0];
            }
            
            foreach ($results as $row) {
                $monthlyData[$row['month']] = $row;
            }
            
            $data = array_values($monthlyData);
            break;
            
        case 'yearly':
            // Get yearly sales for last 5 years (SQLite compatible)
            $stmt = $pdo->prepare("
                SELECT 
                    CAST(strftime('%Y', created_at) AS INTEGER) as year,
                    COUNT(*) as sales_count,
                    SUM(total) as total_revenue
                FROM sales 
                WHERE CAST(strftime('%Y', created_at) AS INTEGER) >= ?
                GROUP BY strftime('%Y', created_at)
                ORDER BY strftime('%Y', created_at)
            ");
            $stmt->execute([$year - 4]);
            $data = $stmt->fetchAll();
            break;
            
        case 'top_products':
            // Get top selling products for specified period
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.id,
                    p.name,
                    p.category,
                    SUM(si.quantity) as total_sold,
                    SUM(si.quantity * si.price) as total_revenue
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                JOIN sales s ON si.sale_id = s.id
                WHERE DATE(s.created_at) BETWEEN ? AND ?
                GROUP BY p.id, p.name, p.category
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $data = $stmt->fetchAll();
            break;
            
        case 'payment_methods':
            // Get payment method distribution
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(total) as total
                FROM sales 
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY payment_method
                ORDER BY total DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $data = $stmt->fetchAll();
            break;
            
        case 'hourly':
            // Get sales by hour for today (SQLite compatible)
            $date = $_GET['date'] ?? date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT 
                    CAST(strftime('%H', created_at) AS INTEGER) as hour,
                    COUNT(*) as sales_count,
                    SUM(total) as total_revenue
                FROM sales 
                WHERE DATE(created_at) = ?
                GROUP BY strftime('%H', created_at)
                ORDER BY strftime('%H', created_at)
            ");
            $stmt->execute([$date]);
            $results = $stmt->fetchAll();
            
            // Fill missing hours with zero values
            $hourlyData = [];
            for ($i = 0; $i <= 23; $i++) {
                $hourlyData[$i] = ['hour' => $i, 'sales_count' => 0, 'total_revenue' => 0];
            }
            
            foreach ($results as $row) {
                $hourlyData[$row['hour']] = $row;
            }
            
            $data = array_values($hourlyData);
            break;
            
        case 'categories':
            // Get sales by category
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(p.category, 'Sem Categoria') as category,
                    SUM(si.quantity) as total_sold,
                    SUM(si.quantity * si.price) as total_revenue
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                JOIN sales s ON si.sale_id = s.id
                WHERE DATE(s.created_at) BETWEEN ? AND ?
                GROUP BY p.category
                ORDER BY total_revenue DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $data = $stmt->fetchAll();
            break;
            
        case 'financial_summary':
            // Get financial summary
            $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $_GET['date_to'] ?? date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT 
                    type,
                    category,
                    SUM(amount) as total
                FROM financial_transactions 
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY type, category
                ORDER BY type, total DESC
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $results = $stmt->fetchAll();
            
            $income = [];
            $expense = [];
            
            foreach ($results as $row) {
                if ($row['type'] == 'income') {
                    $income[] = $row;
                } else {
                    $expense[] = $row;
                }
            }
            
            $data = [
                'income' => $income,
                'expense' => $expense
            ];
            break;
            
        case 'stock_alerts':
            // Get low stock products
            $limit = $_GET['limit'] ?? 10;
            
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    name,
                    category,
                    stock,
                    price
                FROM products 
                WHERE stock <= 10
                ORDER BY stock ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $data = $stmt->fetchAll();
            break;
            
        case 'recent_sales':
            // Get recent sales
            $limit = $_GET['limit'] ?? 10;
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.id,
                    s.total,
                    s.payment_method,
                    s.created_at,
                    COALESCE(c.name, 'Cliente Avulso') as customer_name,
                    u.username as seller_name
                FROM sales s
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN users u ON s.user_id = u.id
                ORDER BY s.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $results = $stmt->fetchAll();
            
            // Format dates for display
            foreach ($results as &$sale) {
                $sale['formatted_date'] = formatDate($sale['created_at']);
                $sale['formatted_total'] = formatCurrency($sale['total']);
            }
            
            $data = $results;
            break;
            
        default:
            throw new Exception('Invalid data type requested');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'type' => $type,
        'params' => $_GET
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'type' => $type ?? 'unknown'
    ]);
}
?>
