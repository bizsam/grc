php
<?php
declare(strict_types=1);
require_once 'auth.php';
require_once 'db_connect.php';

$listingId = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!$listingId) {
    header("Location: browse_ads.php");
    exit;
}

$stmt = $pdo->prepare("SELECT l.*, u.name, c.name as category_name, loc.name as location_name FROM listings l 
                       LEFT JOIN users u ON l.user_id = u.id 
                       LEFT JOIN categories c ON l.category_id = c.id 
                       LEFT JOIN locations loc ON l.location_id = loc.id 
                       WHERE l.id = :id");
$stmt->execute(['id' => $listingId]);
$listing = $stmt->fetch();
if (!$listing) {
    header("Location: browse_ads.php");
    exit;
}

// Increment views
$stmt = $pdo->prepare("UPDATE listings SET views = views + 1 WHERE id = :id");
$stmt->execute(['id' => $listingId]);

// Rate limit for viewing listings (optional enhancement)
$userId = Auth::isLoggedIn() ? Auth::getUserId() : 0;
if (Auth::isLoggedIn() && !Auth::checkRateLimit('view_listing', $userId)) {
    $error = "Too many listing views. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classifieds - Listing</title>
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
        <?php if (isset($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <div class="row">
            <div class="col-md-8">
                <div class="card p-4">
                    <h1 class="mb-3"><?php echo htmlspecialchars($listing['title']);
                    if ($listing['premium']) echo ' <span class="premium-badge">Premium</span>'; ?></h1>
                    <?php if ($listing['images']): ?>
                        <?php
                        $images = json_decode($listing['images'], true) ?: explode(',', $listing['images']);
                        foreach ($images as $image) {
                            if ($image && trim($image) !== '') {
                                echo '<img src="' . htmlspecialchars(trim($image)) . '" class="mb-3" style="max-width: 100%; height: auto;" alt="' . htmlspecialchars($listing['title']) . '">';
                            }
                        }
                        ?>
                    <?php else: ?>
                        <img src="https://via.placeholder.com/600x400" class="mb-3" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                    <?php endif; ?>
                    <p class="lead"><?php echo htmlspecialchars($listing['description']); ?></p>
                    <p><strong>Price:</strong> $<?php echo number_format((float)$listing['price'], 2); ?></p>
                    <p><strong>Views:</strong> <?php echo number_format($listing['views']); ?></p>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($listing['contact_email']); ?></p>
                    <p><strong>Category:</strong> <?php echo htmlspecialchars($listing['category_name'] ?? 'Uncategorized'); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($listing['location_name'] ?? 'Unknown'); ?></p>
                    <p><strong>Posted by:</strong> <?php echo htmlspecialchars($listing['name']); ?> on <?php echo $listing['created_at']; ?></p>
                    <?php if (Auth::isLoggedIn() && $listing['user_id'] != Auth::getUserId()): ?>
                        <a href="messages.php?user=<?php echo $listing['user_id']; ?>" class="btn btn-success mt-3">Message Seller</a>
                        <?php
                        // Check if listing is already saved by the user
                        $userId = Auth::getUserId();
                        $saveStmt = $pdo->prepare("SELECT COUNT(*) FROM saved_listings WHERE user_id = :user_id AND listing_id = :listing_id");
                        $saveStmt->execute(['user_id' => $userId, 'listing_id' => $listingId]);
                        $isSaved = $saveStmt->fetchColumn() > 0;
                        if (Auth::checkRateLimit('save_listing', $userId)) {
                            Auth::incrementRateLimit('save_listing', $userId);
                        ?>
                            <form method="POST" style="display: inline; margin-left: 10px;">
                                <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                                <button type="submit" name="save_listing" class="btn btn-success btn-sm">
                                    <?php echo $isSaved ? 'Unsave Listing' : 'Save Listing'; ?>
                                </button>
                            </form>
                        <?php } else { ?>
                            <span class="text-danger">Too many save attempts. Please try again later.</span>
                        <?php } ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <h3>Similar Listings</h3>
                <div class="card h-100">
                    <?php
                    $similarStmt = $pdo->prepare("SELECT l.*, u.name FROM listings l LEFT JOIN users u ON l.user_id = u.id 
                                                WHERE l.category_id = :cat AND l.id != :id LIMIT 1");
                    $similarStmt->execute(['cat' => $listing['category_id'], 'id' => $listingId]);
                    $similar = $similarStmt->fetch();
                    if ($similar): ?>
                        <img src="<?php echo $similar['images'] ? htmlspecialchars(json_decode($similar['images'], true)[0] ?? explode(',', $similar['images'])[0]) : 'https://via.placeholder.com/300x200'; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($similar['title']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($similar['title']);
                            if ($similar['premium']) echo ' <span class="premium-badge">Premium</span>'; ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($similar['description']); ?></p>
                            <p class="card-text"><strong>Price:</strong> $<?php echo number_format((float)$similar['price'], 2); ?></p>
                            <p><strong>Views:</strong> <?php echo number_format($similar['views']); ?></p>
                            <a href="listing.php?id=<?php echo $similar['id']; ?>" class="btn btn-success">View</a>
                        </div>
                    <?php else: ?>
                        <div class="card-body">
                            <p>No similar listings found.</p>
                        </div>
                    <?php endif; ?>
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

