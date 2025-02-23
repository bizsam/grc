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

$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_SANITIZE_NUMBER_INT);
$paymentSuccess = filter_input(INPUT_POST, 'payment_success', FILTER_SANITIZE_STRING) === 'true';

if ($paymentSuccess && $listingId) {
    $stmt = $pdo->prepare("UPDATE listings SET premium = 1 WHERE id = :id");
    $stmt->execute(['id' => $listingId]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Payment or listing ID failed']);
}
?>

