php
<?php
declare(strict_types=1);
require_once 'auth.php';
require_once 'db_connect.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = Auth::getUserId();
$paymentSuccess = filter_input(INPUT_POST, 'payment_success', FILTER_SANITIZE_STRING) === 'true';

if ($paymentSuccess) {
    $stmt = $pdo->prepare("UPDATE users SET premium = 1 WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    Auth::resetRateLimit('dashboard_action', $userId); // Reset rate limit after premium upgrade
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Payment failed']);
}
?>

