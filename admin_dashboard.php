php
<?php
declare(strict_types=1);
require_once 'auth.php';
require_once 'db_connect.php';

if (!Auth::isLoggedIn() || !Auth::isAdmin()) {
    header("Location: login.php");
    exit;
}

$userId = Auth::getUserId();

// Rate limit for admin actions
if (!Auth::checkRateLimit('admin_action', $userId)) {
    $error = "Too many admin actions. Please try again later.";
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    Auth::incrementRateLimit('admin_action', $userId);
    if (isset($_POST['delete_listing'])) {
        $listingId = (int)$_POST['delete_listing'];
        $stmt = $pdo->prepare("DELETE FROM listings WHERE id = :id");
        $stmt->execute(['id' => $listingId]);
    } elseif (isset($_POST['ban_user'])) {
        $userIdToBan = (int)$_POST['ban_user'];
        if ($userIdToBan !== $userId) {
            $stmt = $pdo->prepare("UPDATE users SET role = 'banned' WHERE id = :id");
            $stmt->execute(['id' => $userIdToBan]);
        }
    } elseif (isset($_POST['delete_category'])) {
        $categoryId = (int)$_POST['delete_category'];
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute(['id' => $categoryId]);
    } elseif (isset($_POST['add_category'])) {
        $newCategory = filter_input(INPUT_POST, 'new_category', FILTER_SANITIZE_STRING);
        if ($newCategory) {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (:name)");
            $stmt->execute(['name' => $newCategory]);
        }
    } elseif (isset($_POST['delete_message'])) {
        $messageId = (int)$_POST['delete_message'];
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = :id");
        $stmt->execute(['id' => $messageId]);
    } elseif (isset($_POST['toggle_listing_premium'])) {
        $listingId = (int)$_POST['listing_id'];
        $stmt = $pdo->prepare("SELECT premium FROM listings WHERE id = :id");
        $stmt->execute(['id' => $listingId]);
        $currentStatus = $stmt->fetchColumn();
        $newStatus = $currentStatus ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE listings SET premium = :premium WHERE id = :id");
        $stmt->execute(['premium' => $newStatus, 'id' => $listingId]);
    } elseif (isset($_POST['toggle_user_premium'])) {
        $userIdToUpdate = (int)$_POST['user_id'];
        if ($userIdToUpdate !== $userId) {
            $stmt = $pdo->prepare("SELECT premium FROM users WHERE id = :id");
            $stmt->execute(['id' => $userIdToUpdate]);
            $currentStatus = $stmt->fetchColumn();
            $newStatus = $currentStatus ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE users SET premium = :premium WHERE id = :id");
            $stmt->execute(['premium' => $newStatus, 'id' => $userIdToUpdate]);
        }
    }
}

// Handle search
$searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classifieds - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#007BFF">
    <script src="https://www.paypal.com/sdk/js?client-id=YOUR_SANDBOX_CLIENT_ID¤cy=USD"></script>
    <style>
        body { font-family: Arial, sans-serif; }
        .navbar { background-color: #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .navbar-brand { font-weight: bold; font-size: 1.5rem; color: #28a745; }
        .nav-link { color: #333; }
        .main-section { background-color: #f0f0f0; padding: 40px 0; }
        .card { background: #fff; border: none; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .card:hover { transform: scale(1.03); }
        .btn-success { background-color: #28a745; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .premium-badge { background-color: #ffc107; color: #000; padding: 2px 8px; border-radius: 5px; font-size: 0.8rem; }
        footer { background-color: #1a1a1a; color: #fff; padding: 40px 0; }
        footer .btn { background-color: #28a745; color: white; }
        footer a { color: #fff; text-decoration: none; }
        .footer-bottom { background-color: #28a745; padding: 10px 0; color: #fff; }
        #installBtn { display: none; }
        .message { margin: 10px 0; padding: 10px; background-color: #f9f9f9; border-radius: 5px; }
        .unread { font-weight: bold; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .search-bar { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php include 'navbar.html'; ?>

    <section class="container my-5 main-section">
        <h1 class="text-center mb-4">Admin Dashboard</h1>
        <?php if (isset($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        
        <!-- Search Bar -->
        <div class="search-bar">
            <form class="row g-2" method="GET" action="">
                <div class="col-md-8 offset-md-2">
                    <input type="text" class="form-control" name="search" placeholder="Search listings, users, or messages..." value="<?php echo htmlspecialchars($searchTerm ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100">Search</button>
                </div>
            </form>
        </div>

        <div class="row row-cols-1 row-cols-md-2 g-4">
            <!-- Listings Management -->
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Manage Listings</h5>
                        <?php
                        $query = "SELECT l.*, u.name FROM listings l LEFT JOIN users u ON l.user_id = u.id";
                        $params = [];
                        if ($searchTerm) {
                            $query .= " WHERE l.title LIKE :search OR u.name LIKE :search";
                            $params['search'] = "%" . $searchTerm . "%";
                        }
                        $listingsStmt = $pdo->prepare($query);
                        $listingsStmt->execute($params);
                        while ($listing = $listingsStmt->fetch()) {
                            echo '<p>' . htmlspecialchars($listing['title']) . ' by ' . htmlspecialchars($listing['name']);
                            if ($listing['premium']) echo ' <span class="premium-badge">Premium</span>';
                            echo ' ';
                            echo '<form method="POST" style="display: inline;"><button type="submit" name="delete_listing" value="' . $listing['id'] . '" class="btn btn-danger btn-sm">Delete</button></form>';
                            echo '<form method="POST" style="display: inline;"><button type="submit" name="toggle_listing_premium" value="' . $listing['id'] . '" class="btn btn-success btn-sm">' . ($listing['premium'] ? 'Remove Premium' : 'Make Premium') . '</button></form>';
                            if (!$listing['premium']) {
                                echo '<div id="paypal-button-container-' . $listing['id'] . '" style="display: inline; margin-left: 10px;"></div>';
                                echo '<script>
                                    paypal.Buttons({
                                        style: { layout: "horizontal", color: "gold", shape: "pill", label: "pay" },
                                        createOrder: function(data, actions) {
                                            return actions.order.create({ purchase_units: [{ amount: { value: "9.99" } }] });
                                        },
                                        onApprove: function(data, actions) {
                                            return actions.order.capture().then(function(details) {
                                                fetch("update_listing_premium.php", {
                                                    method: "POST",
                                                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                                    body: "listing_id=' . $listing['id'] . '&payment_success=true"
                                                }).then(response => response.json()).then(data => {
                                                    if (data.success) {
                                                        alert("Premium upgrade successful!");
                                                        window.location.reload();
                                                    } else {
                                                        alert("Upgrade failed. Please try again.");
                                                    }
                                                });
                                            });
                                        }
                                    }).render("#paypal-button-container-' . $listing['id'] . '");
                                </script>';
                            }
                            echo '</p>';
                        }
                        if (!$listingsStmt->rowCount()) {
                            echo '<p>No listings found.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <!-- Users Management -->
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Manage Users</h5>
                        <?php
                        $query = "SELECT id, name, role, email, premium FROM users WHERE role != 'admin'";
                        $params = [];
                        if ($searchTerm) {
                            $query .= " AND (name LIKE :search OR email LIKE :search)";
                            $params['search'] = "%" . $searchTerm . "%";
                        }
                        $usersStmt = $pdo->prepare($query);
                        $usersStmt->execute($params);
                        while ($user = $usersStmt->fetch()) {
                            echo '<p>' . htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['email']) . ', ' . htmlspecialchars($user['role']) . ')';
                            if ($user['premium']) echo ' <span class="premium-badge">Premium</span>';
                            echo ' ';
                            if ($user['role'] !== 'banned') {
                                echo '<form method="POST" style="display: inline;"><button type="submit" name="ban_user" value="' . $user['id'] . '" class="btn btn-danger btn-sm">Ban</button></form>';
                            } else {
                                echo '<span class="text-muted">Banned</span>';
                            }
                            echo '<form method="POST" style="display: inline;"><button type="submit" name="toggle_user_premium" value="' . $user['id'] . '" class="btn btn-success btn-sm">' . ($user['premium'] ? 'Remove Premium' : 'Make Premium') . '</button></form>';
                            if (!$user['premium']) {
                                echo '<div id="paypal-button-container-user-' . $user['id'] . '" style="display: inline; margin-left: 10px;"></div>';
                                echo '<script>
                                    paypal.Buttons({
                                        style: { layout: "horizontal", color: "gold", shape: "pill", label: "pay" },
                                        createOrder: function(data, actions) {
                                            return actions.order.create({ purchase_units: [{ amount: { value: "9.99" } }] });
                                        },
                                        onApprove: function(data, actions) {
                                            return actions.order.capture().then(function(details) {
                                                fetch("update_premium.php", {
                                                    method: "POST",
                                                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                                                    body: "user_id=' . $user['id'] . '&payment_success=true"
                                                }).then(response => response.json()).then(data => {
                                                    if (data.success) {
                                                        alert("Premium upgrade successful!");
                                                        window.location.reload();
                                                    } else {
                                                        alert("Upgrade failed. Please try again.");
                                                    }
                                                });
                                            });
                                        }
                                    }).render("#paypal-button-container-user-' . $user['id'] . '");
                                </script>';
                            }
                            echo '</p>';
                        }
                        if (!$usersStmt->rowCount()) {
                            echo '<p>No users found.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            <!-- Categories Management -->
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Manage Categories</h5>
                        <?php
                        $query = "SELECT * FROM categories";
                        $params = [];
                        if ($searchTerm) {
                            $query .= " WHERE name LIKE :search";
                            $params['search'] = "%" . $searchTerm . "%";
                        }
                        $categoriesStmt = $pdo->prepare($query);
                        $categoriesStmt->execute($params);
                        while ($category = $categoriesStmt->fetch()) {
                            echo '<p>' . htmlspecialchars($category['name']) . ' ';
                            echo '<form method="POST" style="display: inline;"><button type="submit" name="delete_category" value="' . $category['id'] . '" class="btn btn-danger btn-sm">Delete</button></form></p>';
                        }
                        if (!$categoriesStmt->rowCount()) {
                            echo '<p>No categories found.</p>';
                        }
                        ?>
                        <form method="POST" class="mt-3">
                            <input type="text" name="new_category" class="form-control mb-2" placeholder="New Category">
                            <button type="submit" name="add_category" class="btn btn-success">Add Category</button>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Messages Management -->
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">Manage Messages</h5>
                        <?php
                        $query = "SELECT m.*, u1.name as sender_name, u2.name as receiver_name 
                                  FROM messages m 
                                  JOIN users u1 ON m.sender_id = u1.id 
                                  JOIN users u2 ON m.receiver_id = u2.id";
                        $params = [];
                        if ($searchTerm) {
                            $query .= " WHERE m.message LIKE :search OR u1.name LIKE :search OR u2.name LIKE :search";
                            $params['search'] = "%" . $searchTerm . "%";
                        }
                        $messagesStmt = $pdo->prepare($query);
                        $messagesStmt->execute($params);
                        while ($message = $messagesStmt->fetch()) {
                            echo '<p>' . htmlspecialchars($message['sender_name']) . ' to ' . htmlspecialchars($message['receiver_name']) . ': ' . htmlspecialchars($message['message']) . ' ';
                            echo '<form method="POST" style="display: inline;"><button type="submit" name="delete_message" value="' . $message['id'] . '" class="btn btn-danger btn-sm">Delete</button></form></p>';
                        }
                        if (!$messagesStmt->rowCount()) {
                            echo '<p>No messages found.</p>';
                        }
                        ?>
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

