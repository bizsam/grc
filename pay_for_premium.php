php
<?php
declare(strict_types=1);
require_once 'auth.php';
require_once 'db_connect.php';

if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$listingId = filter_input(INPUT_GET, 'listing_id', FILTER_SANITIZE_NUMBER_INT);
$userId = Auth::getUserId();

if (!$listingId) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classifieds - Pay for Premium</title>
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
        #paypal-button-container { margin: 20px 0; }
    </style>
</head>
<body>
    <?php include 'navbar.html'; ?>

    <section class="container my-5 main-section">
        <h1 class="text-center mb-4">Pay for Premium Listing</h1>
        <div class="card p-4">
            <p>Upgrade this listing to premium for $9.99 to get priority placement and more visibility.</p>
            <div id="paypal-button-container"></div>
            <script>
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
                                    window.location.href = 'listing.php?id=<?php echo $listingId; ?>';
                                } else {
                                    alert('Upgrade failed. Please try again.');
                                }
                            });
                        });
                    }
                }).render('#paypal-button-container');
            </script>
            <a href="listing.php?id=<?php echo $listingId; ?>" class="btn btn-secondary mt-3">Cancel</a>
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
