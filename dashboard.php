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

// Rate limit for dashboard actions (e.g., blocking users)
if (!Auth::checkRateLimit('dashboard_action', $userId)) {
    $error = "Too many actions. Please try again later.";
}

// Toggle messaging setting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error) && isset($_POST['toggle_messages'])) {
    Auth::incrementRateLimit('dashboard_action', $userId);
    $allowMessages = (int)!$_POST['current_setting'];
    $stmt = $pdo->prepare("UPDATE users SET allow_messages = :allow WHERE id = :id");
    $stmt->execute(['allow' => $allowMessages, 'id' => $userId]);
}

// Block a user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error) && isset($_POST['block_user'])) {
    Auth::incrementRateLimit('dashboard_action', $userId);
    $blockedUserId = (int)$_POST['block_user'];
    if ($blockedUserId !== $userId) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO blocked_users (user_id, blocked_user_id) VALUES (:user_id, :blocked_id)");
        $stmt->execute(['user_id' => $userId, 'blocked_id' => $blockedUserId]);
    }
}

// Unsave a listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error) && isset($_POST['unsave_listing'])) {
    Auth::incrementRateLimit('dashboard_action', $userId);
    $savedId = (int)$_POST['unsave_listing'];
    $stmt = $pdo->prepare("DELETE FROM saved_listings WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $savedId, 'user_id' => $userId]);
    header("Location: dashboard.php");
    exit;
}

// Fetch user settings, blocked users, and premium status
$stmt = $pdo->prepare("SELECT allow_messages, premium FROM users WHERE id = :id");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
$allowMessages = (bool)$user['allow_messages'];
$isPremium = (bool)$user['premium'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error) && isset($_POST['upgrade_premium'])) {
    Auth::incrementRateLimit('dashboard_action', $userId);
    $success = isset($_POST['payment_success']) && $_POST['payment_success'] === 'true';
    if ($success) {
        $stmt = $pdo->prepare("UPDATE users SET premium = 1 WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $isPremium = true;
        Auth::resetRateLimit('dashboard_action', $userId); // Reset after premium upgrade
    }
}

$blockedStmt = $pdo->prepare("SELECT u.id, u.name FROM blocked_users bu JOIN users u ON bu.blocked_user_id = u.id WHERE bu.user_id = :user_id");
$blockedStmt->execute(['user_id' => $userId]);
$blockedUsers = $blockedStmt->fetchAll();

$savedStmt = $pdo->prepare("SELECT l.*, u.name FROM saved_listings sl 
                          JOIN listings l ON sl.listing_id = l.id 
                          JOIN users u ON l.user_id = u.id 
                          WHERE sl.user_id = :user_id 
                          ORDER BY sl.saved_at DESC");
$savedStmt->execute(['user_id' => $userId]);
$savedListings = $savedStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classifieds - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#007BFF">
    <script src="https://www.paypal.com/sdk/js?client-id=YOUR_SANDBOX_CLIENT_ID¤cy=USD"></script>
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
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        #paypal-button-container { margin: 20px 0; }
    </style>
</head>
<body>
    <?php include 'navbar.html'; ?>

    <section class="container my-5 main-section">
        <h1 class="text-center mb-4">Dashboard</h1>
        <?php if (isset($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <p>Welcome, <?php echo htmlspecialchars(Auth::getUserName());
        if (Auth::isLoggedIn()) {
            $stmt = $pdo->prepare("SELECT premium FROM users WHERE id = :id");
            $stmt->execute(['id' => Auth::getUserId()]);
            if ($stmt->fetchColumn()) echo ' <span class="premium-badge">Premium</span>';
        }
        ?>!</p>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Settings</h5>
                        <form method="POST">
                            <input type="hidden" name="current_setting" value="<?php echo (int)$allowMessages; ?>">
                            <button type="submit" name="toggle_messages" class="btn btn-success">
                                <?php echo $allowMessages ? 'Turn Off Messages' : 'Turn On Messages'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Blocked Users</h5>
                        <?php if (empty($blockedUsers)): ?>
                            <p>No users blocked.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($blockedUsers as $blocked): ?>
                                    <li class="list-group-item"><?php echo htmlspecialchars($blocked['name']); ?> (Blocked on <?php echo $blocked['blocked_at']; ?>)</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Block a User</h5>
                        <form method="POST">
                            <select name="block_user" class="form-control mb-2" required>
                                <?php
                                $usersStmt = $pdo->prepare("SELECT id, name FROM users WHERE id != :user_id AND id NOT IN (SELECT blocked_user_id FROM blocked_users WHERE user_id = :user_id)");
                                $usersStmt->execute(['user_id' => $userId]);
                                foreach ($usersStmt as $user) {
                                    echo '<option value="' . $user['id'] . '">' . htmlspecialchars($user['name']) . '</option>';
                                }
                                ?>
                            </select>
                            <button type="submit" class="btn btn-success">Block User</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php if (!$isPremium): ?>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">Upgrade to Premium</h5>
                            <p>Enjoy exclusive benefits like priority listings and ad-free experience for $9.99/month.</p>
                            <div id="paypal-button-container"></div>
                            <script>
                                paypal.Buttons({
                                    style: { layout: 'horizontal', color: 'gold', shape: 'pill', label: 'pay' },
                                    createOrder: function(data, actions) {
                                        return actions.order.create({ purchase_units: [{ amount: { value: '9.99' } }] });
                                    },
                                    onApprove: function(data, actions) {
                                        return actions.order.capture().then(function(details) {
                                            fetch('update_premium.php', {
                                                method: 'POST',
                                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                                body: 'user_id=<?php echo $userId; ?>&payment_success=true'
                                            }).then(response => response.json()).then(data => {
                                                if (data.success) {
                                                    alert('Upgrade successful! You are now a Premium user.');
                                                    window.location.reload();
                                                } else {
                                                    alert('Upgrade failed. Please try again.');
                                                }
                                            });
                                        });
                                    }
                                }).render('#paypal-button-container');
                            </script>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Saved Listings</h5>
                        <?php
                        if (empty($savedListings)): ?>
                            <p>No saved listings.</p>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 g-4">
                                <?php foreach ($savedListings as $saved): ?>
                                    <div class="col">
                                        <div class="card h-100">
                                            <img src="<?php echo $saved['images'] ? htmlspecialchars(json_decode($saved['images'], true)[0] ?? explode(',', $saved['images'])[0]) : 'https://via.placeholder.com/300x200'; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($saved['title']); ?>">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($saved['title']);
                                                if ($saved['premium']) echo ' <span class="premium-badge">Premium</span>'; ?></h5>
                                                <p class="card-text"><?php echo htmlspecialchars($saved['description']); ?></p>
                                                <p class="card-text"><strong>Price:</strong> $<?php echo number_format((float)$saved['price'], 2); ?></p>
                                                <p><strong>Views:</strong> <?php echo number_format($saved['views']); ?></p>
                                                <p class="card-text"><small class="text-muted">Posted by <?php echo htmlspecialchars($saved['name']); ?></small></p>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="unsave_listing" value="<?php echo $saved['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Unsave</button>
                                                </form>
                                                <a href="listing.php?id=<?php echo $saved['listing_id']; ?>" class="btn btn-success btn-sm">View</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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

