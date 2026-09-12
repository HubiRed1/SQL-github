<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

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
    echo json_encode(['positions' => [], 'error' => 'DB connection failed']);
    exit;
}

try {
    // Latest position per vehicle
    $rows = $pdo->query("
        SELECT g.telemetry_id, g.vehicle_id, g.latitude, g.longitude,
               g.speed_kmh, g.heading_degrees, g.updated_at,
               v.model_name, v.license_plate, v.vehicle_type, v.status
        FROM gps_telemetry g
        JOIN vehicles v ON g.vehicle_id = v.vehicle_id
        INNER JOIN (
            SELECT vehicle_id, MAX(telemetry_id) AS max_id
            FROM gps_telemetry
            GROUP BY vehicle_id
        ) latest ON g.telemetry_id = latest.max_id
        ORDER BY g.updated_at DESC
    ")->fetchAll();

    echo json_encode(['positions' => $rows]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['positions' => [], 'error' => $e->getMessage()]);
}