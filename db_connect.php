php
<?php
declare(strict_types=1);

$host = 'localhost';
$dbname = 'classifieds_db';  // Adjust for your database name
$username = 'root';          // Replace with your MySQL username
$password = '';              // Replace with your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    die("Connection failed: " . htmlspecialchars($e->getMessage()));
}
?>

