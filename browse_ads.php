php
<?php
declare(strict_types=1);
require_once 'auth.php';
require_once 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classifieds - Browse Ads</title>
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
    </style>
</head>
<body>
    <?php include 'navbar.html'; ?>

    <section class="container my-3">
        <div class="search-bar">
            <form class="row g-2" method="GET" action="browse_ads.php">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search for..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="location" placeholder="Location in..." value="<?php echo htmlspecialchars($_GET['location'] ?? ''); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-control" name="category">
                        <option value="">Category</option>
                        <option value="auto" <?php echo ($_GET['category'] ?? '') === 'auto' ? 'selected' : ''; ?>>Auto/Moto</option>
                        <option value="fashion" <?php echo ($_GET['category'] ?? '') === 'fashion' ? 'selected' : ''; ?>>Fashion</option>
                        <option value="mother" <?php echo ($_GET['category'] ?? '') === 'mother' ? 'selected' : ''; ?>>Mother & Child</option>
                        <option value="jobs" <?php echo ($_GET['category'] ?? '') === 'jobs' ? 'selected' : ''; ?>>Jobs</option>
                        <option value="realestate" <?php echo ($_GET['category'] ?? '') === 'realestate' ? 'selected' : ''; ?>>Real Estate</option>
                        <option value="pets" <?php echo ($_GET['category'] ?? '') === 'pets' ? 'selected' : ''; ?>>Pets</option>
                        <option value="sport" <?php echo ($_GET['category'] ?? '') === 'sport' ? 'selected' : ''; ?>>Sport</option>
                        <option value="more" <?php echo ($_GET['category'] ?? '') === 'more' ? 'selected' : ''; ?>>More</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-success w-100">Search</button>
                </div>
            </form>
        </div>
    </section>

    <section class="container my-5 main-section">
        <h1 class="text-center mb-4">Browse All Ads</h1>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php
            $query = "SELECT l.*, u.name, u.id as user_id FROM listings l LEFT JOIN users u ON l.user_id = u.id";
            $params = [];
            if (isset($_GET['cat'])) {
                $cat = filter_input(INPUT_GET, 'cat', FILTER_SANITIZE_STRING);
                $query .= " WHERE l.category_id = (SELECT id FROM categories WHERE name = :cat)";
                $params['cat'] = $cat;
            }
            $location = filter_input(INPUT_GET, 'location', FILTER_SANITIZE_STRING);
            if ($location) {
                $query .= isset($_GET['cat']) ? " AND l.location_id = (SELECT id FROM locations WHERE name LIKE :location)" : " WHERE l.location_id = (SELECT id FROM locations WHERE name LIKE :location)";
                $params['location'] = "%" . $location . "%";
            }
            $query .= " ORDER BY l.premium DESC, l.created_at DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            foreach ($stmt as $row) {
                echo '<div class="col">';
                echo '<div class="card h-100">';
                if ($row['images']) {
                    $images = json_decode($row['images'], true) ?: explode(',', $row['images']);
                    echo '<img src="' . htmlspecialchars(trim($images[0] ?? '')) . '" class="card-img-top" alt="' . htmlspecialchars($row['title']) . '">';
                } else {
                    echo '<img src="https://via.placeholder.com/300x200" class="card-img-top" alt="' . htmlspecialchars($row['title']) . '">';
                }
                echo '<div class="card-body">';
                echo '<h5 class="card-title">' . htmlspecialchars($row['title']) . '</h5>';
                echo '<p class="card-text">' . htmlspecialchars($row['description']) . '</p>';
                echo '<p class="card-text"><strong>Price:</strong> $' . number_format((float)$row['price'], 2) . '</p>';
                if ($row['premium']) echo ' <span class="premium-badge">Premium</span>';
                echo '<p><strong>Views:</strong> ' . number_format($row['views']) . '</p>';
                echo '<p class="card-text"><small class="text-muted">' . htmlspecialchars($row['name']) . '</small></p>';
                echo '<a href="listing.php?id=' . $row['id'] . '" class="btn btn-success">View</a>';
                echo '</div></div></div>';
            }
            if (!$location) {
                echo '<p class="text-center">Please allow location access to see local ads.</p>';
            }
            ?>
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

