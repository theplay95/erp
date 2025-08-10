<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'erp_system';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
    }

    public function getConnection() {
        $this->conn = null;

        try {
            // First try PostgreSQL from environment
            if (isset($_ENV['DATABASE_URL'])) {
                try {
                    $this->conn = new PDO($_ENV['DATABASE_URL'], null, null, array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ));
                } catch (PDOException $pgException) {
                    // If PostgreSQL fails, fallback to SQLite
                    error_log("PostgreSQL failed, using SQLite fallback: " . $pgException->getMessage());
                    $db_path = __DIR__ . '/../database/erp_system.sqlite';
                    $dir = dirname($db_path);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    
                    $this->conn = new PDO(
                        "sqlite:" . $db_path,
                        null,
                        null,
                        array(
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        )
                    );
                    
                    $this->conn->exec("PRAGMA foreign_keys = ON");
                }
            } else {
                // No environment variable, use SQLite
                $db_path = __DIR__ . '/../database/erp_system.sqlite';
                $dir = dirname($db_path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                $this->conn = new PDO(
                    "sqlite:" . $db_path,
                    null,
                    null,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    )
                );
                
                $this->conn->exec("PRAGMA foreign_keys = ON");
            }
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            return null;
        }

        return $this->conn;
    }
}

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in function
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit();
    }
}

// Get current user
function getCurrentUser() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Format date
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Format currency
function formatCurrency($amount) {
    return 'R$ ' . number_format((float)$amount, 2, ',', '.');
}

// Check admin access
function checkAdminAccess() {
    if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
        header('Location: /auth/login.php');
        exit('Acesso negado. Apenas administradores podem acessar esta página.');
    }
}

// Log audit trail
function logAudit($userId, $action, $tableName = null, $recordId = null, $details = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $tableName,
            $recordId,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}
?>
