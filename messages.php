php
<?php
declare(strict_types=1);
require_once 'auth.php';
require_once 'db_connect.php';

if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$userId = Auth::getUserId();

// Rate limit for sending messages
if (!Auth::checkRateLimit('send_message', $userId)) {
    $error = "Too many messages sent. Please try again later.";
}

// Send a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error) && isset($_POST['send_message'])) {
    Auth::incrementRateLimit('send_message', $userId);
    $receiverId = (int)$_POST['receiver_id'];
    $listingId = !empty($_POST['listing_id']) ? (int)$_POST['listing_id'] : null;
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

    $stmt = $pdo->prepare("SELECT allow_messages FROM users WHERE id = :id");
    $stmt->execute(['id' => $receiverId]);
    $receiver = $stmt->fetch();
    
    $blockedStmt = $pdo->prepare("SELECT COUNT(*) FROM blocked_users WHERE user_id = :receiver_id AND blocked_user_id = :sender_id");
    $blockedStmt->execute(['receiver_id' => $receiverId, 'sender_id' => $userId]);
    $isBlocked = $blockedStmt->fetchColumn() > 0;

    if ($receiver && $receiver['allow_messages'] && !$isBlocked && $message) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, listing_id, message) VALUES (:sender, :receiver, :listing, :message)");
        $stmt->execute(['sender' => $userId, 'receiver' => $receiverId, 'listing' => $listingId, 'message' => $message]);
    } else {
        $error = "Cannot send message: User has disabled messages or blocked you.";
    }
}

// Mark messages as read
if (isset($_GET['mark_read'])) {
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = :id AND receiver_id = :user_id");
    $stmt->execute(['id' => (int)$_GET['mark_read'], 'user_id' => $userId]);
}

// Fetch conversations
$conversationsStmt = $pdo->prepare("
    SELECT u.id, u.name, COUNT(CASE WHEN m.is_read = 0 AND m.receiver_id = :user_id THEN 1 END) as unread
    FROM users u
    LEFT JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = :user_id) OR (m.sender_id = :user_id AND m.receiver_id = u.id)
    WHERE u.id != :user_id AND m.id IS NOT NULL
    GROUP BY u.id, u.name
");
$conversationsStmt->execute(['user_id' => $userId]);
$conversations = $conversationsStmt->fetchAll();

// Fetch messages for selected conversation
$selectedUserId = isset($_GET['user']) ? (int)$_GET['user'] : null;
if ($selectedUserId) {
    $messagesStmt = $pdo->prepare("
        SELECT m.*, l.title as listing_title, u_sender.name as sender_name
        FROM messages m
        LEFT JOIN listings l ON m.listing_id = l.id
        JOIN users u_sender ON m.sender_id = u_sender.id
        WHERE (m.sender_id = :user_id AND m.receiver_id = :selected_id)
           OR (m.sender_id = :selected_id AND m.receiver_id = :user_id)
        ORDER BY m.sent_at ASC
    ");
    $messagesStmt->execute(['user_id' => $userId, 'selected_id' => $selectedUserId]);
    $messages = $messagesStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classifieds - Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#007BFF">
    <style>
        body { font-family: Arial, sans-serif; }
        .navbar { background-color: #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .navbar-brand { font-weight: bold; font-size: 1.5rem; color: #28a745; }
        .nav-link { color: #333; }
        .hero { background: linear-gradient(135deg, #007BFF, #00C4FF); color: white; padding: 4rem 0; text-align: center; position: relative; }
        .hero img { max-width: 100%; height: auto; }
        .hero .social-icons { margin-top: 20px; display: flex; justify-content: center; gap: 10px; }
        .hero .social-icons img { width: 24px; height: 24px; }
        .search-bar { background: #fff; padding: 20px; margin: 20px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .search-bar .form-control, .search-bar .btn { border-radius: 0; border: none; }
        .search-bar .btn { background-color: #28a745; color: white; }
        .categories { margin-top: 10px; }
        .categories a { color: #333; text-decoration: none; margin: 0 5px; }
        .main-section { background-color: #f0f0f0; padding: 40px 0; }
        .card { background: #fff; border: none; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card:hover { transform: scale(1.03); }
        .card img { height: 200px; object-fit: cover; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .form-control { border-radius: 0; }
        .premium-badge { background-color: #ffc107; color: #000; padding: 2px 8px; border-radius: 5px; font-size: 0.8rem; }
        footer { background-color: #1a1a1a; color: #fff; padding: 40px 0; }
        footer .btn { background-color: #28a745; color: white; }
        footer a { color: #fff; text-decoration: none; }
        .footer-bottom { background-color: #28a745; padding: 10px 0; color: #fff; }
        #installBtn { display: none; }
        .message { margin: 10px 0; padding: 10px; background-color: #f9f9f9; border-radius: 5px; }
        .unread { font-weight: bold; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php include 'navbar.html'; ?>

    <section class="container my-5 main-section">
        <h1 class="text-center mb-4">Messages</h1>
        <?php if (isset($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <div class="row">
            <div class="col-md-3">
                <h2>Conversations</h2>
                <?php foreach ($conversations as $convo): ?>
                    <p>
                        <a href="?user=<?php echo $convo['id']; ?>" class="<?php echo $convo['unread'] > 0 ? 'unread' : ''; ?>">
                            <?php echo htmlspecialchars($convo['name']);
                            $stmt = $pdo->prepare("SELECT premium FROM users WHERE id = :id");
                            $stmt->execute(['id' => $convo['id']]);
                            if ($stmt->fetchColumn()) echo ' <span class="premium-badge">Premium</span>'; ?> 
                            (<?php echo $convo['unread']; ?> unread)
                        </a>
                    </p>
                <?php endforeach; ?>
            </div>
            <div class="col-md-9">
                <?php if ($selectedUserId): ?>
                    <h2>Messages with <?php echo htmlspecialchars($conversations[array_search($selectedUserId, array_column($conversations, 'id'))]['name'] ?? 'User'); ?></h2>
                    <div class="message-list">
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['receiver_id'] == $userId && !$message['is_read'] ? 'unread' : ''; ?>">
                                <strong><?php echo htmlspecialchars($message['sender_name']); ?></strong> 
                                <?php if ($message['listing_title']): ?>
                                    (About: <?php echo htmlspecialchars($message['listing_title']); ?>)
                                <?php endif; ?>
                                <br><?php echo htmlspecialchars($message['message']); ?>
                                <small class="text-muted"> - <?php echo $message['sent_at']; ?></small>
                                <?php if ($message['receiver_id'] == $userId && !$message['is_read']): ?>
                                    <a href="?user=<?php echo $selectedUserId; ?>&mark_read=<?php echo $message['id']; ?>" class="btn btn-success btn-sm">Mark Read</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="receiver_id" value="<?php echo $selectedUserId; ?>">
                        <input type="hidden" name="listing_id" value="<?php echo isset($_GET['listing']) ? (int)$_GET['listing'] : ''; ?>">
                        <div class="mb-3">
                            <textarea name="message" class="form-control" placeholder="Type your message..." required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-success">Send</button>
                    </form>
                <?php else: ?>
                    <p>Select a conversation to view messages.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'footer.html'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let deferredPrompt;
        const installBtn = document.getElementById('installBtn');
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installBtn.style.display = 'inline-block';
        });
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                deferredPrompt = null;
                installBtn.style.display = 'none';
            }
        });
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js');
            });
        }
        window.addEventListener('appinstalled', () => {
            installBtn.style.display = 'none';
        });
    </script>
</body>
</html>

