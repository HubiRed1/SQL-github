<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$host = '127.0.0.1';
$db   = 'cartez_express';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'detail' => 'DB connection failed']);
    exit;
}

// GET ?list=1  -> return vehicles as JSON (for the tracker dropdown)
if (isset($_GET['list'])) {
    try {
        $rows = $pdo->query("SELECT vehicle_id, model_name, license_plate, vehicle_type FROM vehicles ORDER BY vehicle_id")->fetchAll();
        echo json_encode(['vehicles' => $rows]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['vehicles' => [], 'error' => $e->getMessage()]);
    }
    exit;
}

// Accept JSON body
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'detail' => 'Invalid JSON payload']);
    exit;
}

$vehicle_id      = intval($data['vehicle_id'] ?? 0);
$latitude        = $data['latitude']   ?? null;
$longitude       = $data['longitude']  ?? null;
$speed_kmh       = isset($data['speed_kmh']) ? floatval($data['speed_kmh']) : 0.00;
$heading_degrees = isset($data['heading_degrees']) ? floatval($data['heading_degrees']) : 0.00;

if ($vehicle_id <= 0 || $latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'detail' => 'Missing vehicle_id / latitude / longitude']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO gps_telemetry (vehicle_id, latitude, longitude, speed_kmh, heading_degrees) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $vehicle_id,
        $latitude,
        $longitude,
        $speed_kmh,
        $heading_degrees,
    ]);
    echo json_encode(['status' => 'ok', 'telemetry_id' => (int)$pdo->lastInsertId()]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'detail' => $e->getMessage()]);
}