php
<?php
declare(strict_types=1);
require_once 'auth.php';

if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$userId = Auth::getUserId();
if (!Auth::checkRateLimit('post_ad', $userId)) {
    $error = "Too many ad postings. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error)) {
    Auth::incrementRateLimit('post_ad', $userId);
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING) ?: '';
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING) ?: '';
    $price = filter_input(INPUT_POST, 'price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) ?: 0.0;
    $contact_email = filter_input(INPUT_POST, 'contact_email', FILTER_SANITIZE_EMAIL) ?: '';
    $category_id = (int)filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT);
    $location_id = (int)filter_input(INPUT_POST, 'location_id', FILTER_SANITIZE_NUMBER_INT);
    $premium = isset($_POST['premium']) ? 1 : 0;
    $images = [];

    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $maxImages = 5; // Limit to 5 images
        $maxSize = 5 * 1024 * 1024; // 5MB per image
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        
        for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
            if (count($images) >= $maxImages) break;
            
            $fileTmpPath = $_FILES['images']['tmp_name'][$i];
            $fileName = $_FILES['images']['name'][$i];
            $fileSize = $_FILES['images']['size'][$i];
            $fileType = $_FILES['images']['type'][$i];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExt, $allowedExts) && $fileSize <= $maxSize && strpos($fileType, 'image/') === 0) {
                $newFileName = uniqid('img_') . '.' . $fileExt;
                $uploadDir = 'uploads/';
                $imagePath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $imagePath)) {
                    $images[] = $imagePath;
                } else {
                    $error = "Failed to upload image: " . htmlspecialchars($fileName);
                    break;
                }
            } else {
                $error = "Invalid image file: " . htmlspecialchars($fileName) . ". Allowed types: JPG, JPEG, PNG, GIF. Max size: 5MB.";
                break;
            }
        }
    }

    require_once 'db_connect.php';
    if ($title && $description && $price >= 0 && filter_var($contact_email, FILTER_VALIDATE_EMAIL) && $category_id && $location_id) {
        $stmt = $pdo->prepare("INSERT INTO listings (title, description, price, contact_email, user_id, images, category_id, location_id, premium, views) VALUES (:title, :description, :price, :contact_email, :user_id, :images, :category_id, :location_id, :premium, 0)");
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'price' => $price,
            'contact_email' => $contact_email,
            'user_id' => $userId,
            'images' => json_encode($images) ?: '', // Store as JSON for multiple images
            'category_id' => $category_id,
            'location_id' => $location_id,
            'premium' => $premium ? 0 : 0 // Default to non-premium unless paid
        ]);

        $listingId = $pdo->lastInsertId();
        if ($premium) {
            header("Location: pay_for_premium.php?listing_id=$listingId");
            exit;
        } else {
            header("Location: home.php?success=published");
            exit;
        }
    } else {
        $error = $error ?? "Please fill out all fields correctly.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classifieds - Post a New Ad</title>
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
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        #paypal-button-container { margin: 20px 0; }
    </style>
</head>
<body>
    <?php include 'navbar.html'; ?>

    <section class="container my-5 main-section">
        <h1 class="text-center mb-4">Post a New Ad</h1>
        <?php if (isset($error)): ?><div class="alert alert-<?php echo strpos($error, 'Too many') !== false ? 'danger' : 'danger'; ?>"><?php echo $error; ?></div><?php endif; ?>
        <form method="POST" enctype="multipart/form-data" class="row g-3">
            <div class="col-md-6">
                <div class="card p-4">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title:</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description:</label>
                        <textarea id="description" name="description" class="form-control" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for "price" class="form-label">Price ($):</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="contact_email" class="form-label">Contact Email:</label>
                        <input type="email" id="contact_email" name="contact_email" class="form-control" required>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card p-4">
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category:</label>
                        <select id="category_id" name="category_id" class="form-control" required>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM categories");
                            while ($category = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="location_id" class="form-label">Location:</label>
                        <select id="location_id" name="location_id" class="form-control" required>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM locations");
                            while ($location = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . $location['id'] . '">' . htmlspecialchars($location['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="images" class="form-label">Upload Images (up to 5, max 5MB each, JPG/PNG/GIF):</label>
                        <input type="file" id="images" name="images[]" class="form-control" multiple accept="image/jpeg,image/png,image/gif" onchange="validateImages(this)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Make Premium (Optional, $9.99):</label>
                        <input type="checkbox" name="premium" id="premium" class="form-check-input">
                        <label for="premium" class="form-check-label">Yes, make this listing premium</label>
                        <div id="paypal-button-container" style="display: none;"></div>
                    </div>
                    <button type="submit" class="btn btn-success">Submit Ad</button>
                </div>
            </div>
        </form>
        <a href="home.php" class="btn btn-secondary mt-3">Back to Home</a>

        <script>
            function validateImages(input) {
                const maxImages = 5;
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                const files = input.files;

                if (files.length > maxImages) {
                    alert('You can upload a maximum of 5 images.');
                    input.value = ''; // Clear the input
                    return false;
                }

                for (let file of files) {
                    if (!allowedTypes.includes(file.type)) {
                        alert('Invalid file type. Allowed types: JPG, PNG, GIF.');
                        input.value = '';
                        return false;
                    }
                    if (file.size > maxSize) {
                        alert('File too large. Maximum size is 5MB per image.');
                        input.value = '';
                        return false;
                    }
                }
                return true;
            }

            document.getElementById('premium').addEventListener('change', function() {
                const container = document.getElementById('paypal-button-container');
                if (this.checked) {
                    container.style.display = 'block';
                    paypal.Buttons({
                        style: { layout: 'horizontal', color: 'gold', shape: 'pill', label: 'pay' },
                        createOrder: function(data, actions) {
                            return actions.order.create({ purchase_units: [{ amount: { value: '9.99' } }] });
                        },
                        onApprove: function(data, actions) {
                            return actions.order.capture().then(function(details) {
                                fetch('update_listing_premium.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'listing_id=<?php echo $listingId; ?>&payment_success=true'
                                }).then(response => response.json()).then(data => {
                                    if (data.success) {
                                        alert('Premium upgrade successful!');
                                        document.querySelector('form').submit(); // Submit the form with premium status
                                    } else {
                                        alert('Upgrade failed. Please try again.');
                                    }
                                });
                            });
                        }
                    }).render('#paypal-button-container');
                } else {
                    container.style.display = 'none';
                }
            });
        </script>
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

