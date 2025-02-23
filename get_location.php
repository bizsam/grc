php
<?php
declare(strict_types=1);
require_once 'db_connect.php';

header('Content-Type: application/json');

$latitude = filter_input(INPUT_POST, 'latitude', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$longitude = filter_input(INPUT_POST, 'longitude', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

if ($latitude && $longitude) {
    // Simulate geolocation lookup (in a real app, use a geocoding API like Google Maps)
    $stmt = $pdo->prepare("SELECT name FROM locations WHERE ST_Distance_Sphere(
        POINT(:longitude, :latitude),
        POINT(longitude, latitude)
    ) ORDER BY ST_Distance_Sphere(
        POINT(:longitude, :latitude),
        POINT(longitude, latitude)
    ) LIMIT 1");
    $stmt->execute(['latitude' => $latitude, 'longitude' => $longitude]);
    $location = $stmt->fetchColumn();

    if ($location) {
        echo json_encode(['location' => $location]);
    } else {
        echo json_encode(['location' => 'Unknown']);
    }
} else {
    echo json_encode(['error' => 'Invalid coordinates']);
}
?>

