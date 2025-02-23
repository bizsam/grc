php
<?php
define('BASE_URL', 'https://yourdomain.com');  // Replace with your domain
define('UPLOAD_DIR', 'uploads/');
?>
manifest.json
json
{
    "name": "Classifieds",
    "short_name": "Classifieds",
    "start_url": "/index.php",
    "display": "standalone",
    "background_color": "#007BFF",
    "theme_color": "#007BFF",
    "icons": [
        {
            "src": "/icon-192x192.png",
            "sizes": "192x192",
            "type": "image/png"
        },
        {
            "src": "/icon-512x512.png",
            "sizes": "512x512",
            "type": "image/png"
        }
    ]
}

