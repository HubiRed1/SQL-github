<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    die("Database connection failed: " . $e->getMessage());
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add_vehicle':
                $stmt = $pdo->prepare("INSERT INTO vehicles (vin, license_plate, vehicle_type, model_name, max_payload_kg, current_mileage_km, is_rented_from_partner, partner_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available')");
                $partner = !empty($_POST['partner_id']) ? intval($_POST['partner_id']) : null;
                $stmt->execute([
                    $_POST['vin'],
                    $_POST['license_plate'],
                    $_POST['vehicle_type'],
                    $_POST['model_name'],
                    $_POST['max_payload_kg'],
                    $_POST['current_mileage_km'],
                    intval($_POST['is_rented_from_partner']),
                    $partner
                ]);
                $message = "Vehicle added successfully!";
                break;

            case 'add_route':
                $stmt = $pdo->prepare("INSERT INTO freight_routes (origin_name, destination_name, distance_km, estimated_duration_hours, transport_chain_type) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['origin_name'],
                    $_POST['destination_name'],
                    $_POST['distance_km'],
                    $_POST['estimated_duration_hours'],
                    $_POST['transport_chain_type']
                ]);
                $message = "Route added successfully!";
                break;

            case 'add_zone':
                $stmt = $pdo->prepare("INSERT INTO warehouse_zones (warehouse_id, zone_code, segregation_category, max_weight_capacity_kg) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    intval($_POST['warehouse_id']),
                    $_POST['zone_code'],
                    $_POST['segregation_category'],
                    $_POST['max_weight_capacity_kg']
                ]);
                $message = "Warehouse segregation zone added!";
                break;

            case 'update_gps':
                $stmt = $pdo->prepare("INSERT INTO gps_telemetry (vehicle_id, latitude, longitude, speed_kmh, heading_degrees) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    intval($_POST['vehicle_id']),
                    $_POST['latitude'],
                    $_POST['longitude'],
                    $_POST['speed_kmh'],
                    $_POST['heading_degrees']
                ]);
                $message = "GPS telemetry ping saved!";
                break;

            case 'sell_pallet':
                $stmt = $pdo->prepare("INSERT INTO pallet_inventory (warehouse_id, pallet_type, condition_grade, quantity_for_sale, price_per_unit) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    intval($_POST['warehouse_id']),
                    $_POST['pallet_type'],
                    $_POST['condition_grade'],
                    intval($_POST['quantity_for_sale']),
                    $_POST['price_per_unit']
                ]);
                $message = "Pallets listed for sale!";
                break;

            case 'rent_vehicle':
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO vehicle_rentals (employee_id, vehicle_id, purpose) VALUES (?, ?, ?)");
                $stmt->execute([
                    intval($_POST['employee_id']),
                    intval($_POST['vehicle_id']),
                    $_POST['purpose']
                ]);
                $upd = $pdo->prepare("UPDATE vehicles SET status = 'rented_by_employee' WHERE vehicle_id = ?");
                $upd->execute([intval($_POST['vehicle_id'])]);
                $pdo->commit();
                $message = "Employee vehicle rental registered!";
                break;

            case 'create_order':
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO customer_orders (customer_id, route_id, total_price, order_status, delivery_address_string, delivery_latitude, delivery_longitude) VALUES (?, ?, ?, 'pending', ?, ?, ?)");
                $stmt->execute([
                    intval($_POST['customer_id']),
                    intval($_POST['route_id']),
                    $_POST['total_price'],
                    $_POST['delivery_address_string'],
                    $_POST['delivery_latitude'],
                    $_POST['delivery_longitude']
                ]);
                $order_id = $pdo->lastInsertId();

                $snap = $pdo->prepare("INSERT INTO customer_order_history_snapshots (customer_id, previous_delivery_address, previous_latitude, previous_longitude) VALUES (?, ?, ?, ?)");
                $snap->execute([
                    intval($_POST['customer_id']),
                    $_POST['delivery_address_string'],
                    $_POST['delivery_latitude'],
                    $_POST['delivery_longitude']
                ]);

                $cargo = $pdo->prepare("INSERT INTO cargo_items (order_id, weight_kg, volume_m3, handling_type, assigned_warehouse_zone_id) VALUES (?, ?, ?, ?, ?)");
                $zone = !empty($_POST['zone_id']) ? intval($_POST['zone_id']) : null;
                $cargo->execute([
                    $order_id,
                    $_POST['weight_kg'],
                    $_POST['volume_m3'],
                    $_POST['handling_type'],
                    $zone
                ]);

                $pdo->commit();
                $message = "Customer order processed, history snapshot created, cargo segregated!";
                break;

            case 'add_subscription':
                $stmt = $pdo->prepare("INSERT INTO subscriptions (user_id, plan_tier, status, start_date, end_date) VALUES (?, ?, 'active', ?, ?)");
                $stmt->execute([
                    intval($_POST['user_id']),
                    $_POST['plan_tier'],
                    $_POST['start_date'],
                    $_POST['end_date']
                ]);
                $message = "Subscription plan updated!";
                break;
        }
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = "Error executing transaction: " . $e->getMessage();
    }
}

// --- Seed warehouses if empty ---
$warehouses = $pdo->query("SELECT * FROM warehouses")->fetchAll();
if (empty($warehouses)) {
    $pdo->exec("INSERT INTO warehouses (name, city, latitude, longitude, total_pallet_capacity, available_pallet_spaces) VALUES
        ('Central Terminal Alpha', 'Gdynia Port', 54.5310, 18.5390, 5000, 4200),
        ('Euro Hub Logistics', 'Warsaw West', 52.2297, 21.0122, 3500, 2100)");
    $warehouses = $pdo->query("SELECT * FROM warehouses")->fetchAll();
}

// --- Seed users if empty ---
$users = $pdo->query("SELECT * FROM users")->fetchAll();
if (empty($users)) {
    $hash = password_hash("secure123", PASSWORD_DEFAULT);
    $seed = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, role, hire_date) VALUES (?, ?, ?, ?, ?, ?)");
    $seed->execute(['Jan', 'Kowalski', 'jan.k@cartez.com', $hash, 'admin', '2022-01-15']);
    $seed->execute(['Marek', 'Nowak', 'marek.n@cartez.com', $hash, 'employee', '2024-06-01']);
    $seed->execute(['Alice', 'Smith', 'alice@customer.com', $hash, 'customer', null]);
    $users = $pdo->query("SELECT * FROM users")->fetchAll();
}

$vehicles = $pdo->query("SELECT * FROM vehicles")->fetchAll();
$routes = $pdo->query("SELECT * FROM freight_routes")->fetchAll();
$zones = $pdo->query("SELECT * FROM warehouse_zones")->fetchAll();
$avail_vehicles = array_filter($vehicles, function($v) { return $v['status'] === 'available'; });
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cartez Express - Operations Platform</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1, h2 { color: #1e293b; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 4px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 12px; }
        label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 0.9em; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box; }
        button { background: #d97706; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 8px; }
        button:hover { background: #b45309; }
        button:disabled { background: #cbd5e1; cursor: not-allowed; }
        .alert { background: #fef3c7; border-left: 4px solid #d97706; color: #92400e; padding: 12px; margin-bottom: 20px; border-radius: 4px; }
	#map { position: relative; overflow: hidden; }
	.leaflet-container { position: relative; overflow: hidden; background: #ddd; }
	.leaflet-pane { position: absolute; left: 0; top: 0; }
	.leaflet-tile-pane { z-index: 200; }
	.leaflet-overlay-pane { z-index: 400; }
	.leaflet-marker-pane { z-index: 600; }
	.leaflet-popup-pane { z-index: 700; }
	.leaflet-tile { position: absolute; }
	.leaflet-marker-icon, .leaflet-marker-shadow { position: absolute; }
	.leaflet-popup { position: absolute; }
	.leaflet-control { position: relative; }
    </style>
	<link rel="stylesheet" href__="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
<div class="container">
    <h1>Cartez Express Platform Integration Console</h1>

    <?php if (!empty($message)): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2>1. Register Vehicle (Land/Air/Water)</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_vehicle">
                <div class="form-group">
                    <label>VIN</label>
                    <input type="text" name="vin" required maxlength="17">
                </div>
                <div class="form-group">
                    <label>License Plate</label>
                    <input type="text" name="license_plate" required>
                </div>
                <div class="form-group">
                    <label>Network Medium</label>
                    <select name="vehicle_type">
                        <option value="land_truck">Land Truck</option>
                        <option value="air_cargo">Air Cargo</option>
                        <option value="water_vessel">Water Vessel</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Model Name</label>
                    <input type="text" name="model_name" required>
                </div>
                <div class="form-group">
                    <label>Max Payload (KG)</label>
                    <input type="number" step="0.01" name="max_payload_kg" required>
                </div>
                <div class="form-group">
                    <label>Current Mileage (KM)</label>
                    <input type="number" step="0.01" name="current_mileage_km" required>
                </div>
                <div class="form-group">
                    <label>Is Partner Company Rental?</label>
                    <select name="is_rented_from_partner">
                        <option value="0">No (Owned)</option>
                        <option value="1">Yes (Partner Leased)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Partner ID (Optional)</label>
                    <input type="number" name="partner_id">
                </div>
                <button type="submit">Deploy Fleet Asset</button>
            </form>
        </div>

        <div class="card">
            <h2>2. Define Intermodal Freight Route</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_route">
                <div class="form-group">
                    <label>Origin Hub</label>
                    <input type="text" name="origin_name" required>
                </div>
                <div class="form-group">
                    <label>Destination Hub</label>
                    <input type="text" name="destination_name" required>
                </div>
                <div class="form-group">
                    <label>Distance (KM)</label>
                    <input type="number" step="0.01" name="distance_km" required>
                </div>
                <div class="form-group">
                    <label>Baseline Target Duration (Hours)</label>
                    <input type="number" step="0.01" name="estimated_duration_hours" required>
                </div>
                <div class="form-group">
                    <label>Transport Network Chain</label>
                    <select name="transport_chain_type">
                        <option value="land_only">Land Only Network</option>
                        <option value="sea_land">Sea-Land Network</option>
                        <option value="air_land">Air-Land Network</option>
                        <option value="tri_modal_intermodal">Tri-Modal Intermodal System</option>
                    </select>
                </div>
                <button type="submit">Establish Network Route</button>
            </form>
        </div>

        <div class="card">
            <h2>3. Warehousing Zone Assignment</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_zone">
                <div class="form-group">
                    <label>Select Target Warehouse Hub</label>
                    <select name="warehouse_id">
                        <?php foreach($warehouses as $w): ?>
                            <option value="<?= $w['warehouse_id'] ?>"><?= htmlspecialchars($w['name'] . ' - ' . $w['city']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Zone Code</label>
                    <input type="text" name="zone_code" placeholder="e.g. COLD-02" required>
                </div>
                <div class="form-group">
                    <label>Segregation Safety Category</label>
                    <select name="segregation_category">
                        <option value="general">General Cargo Storage</option>
                        <option value="hazardous">Hazardous / Chemical Control</option>
                        <option value="perishable">Perishable / Cold Chain</option>
                        <option value="high_value">High Value Vault Protection</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Max Weight Capacity (KG)</label>
                    <input type="number" step="0.01" name="max_weight_capacity_kg" required>
                </div>
                <button type="submit">Isolate & Partition Zone</button>
            </form>
        </div>

        <div class="card">
            <h2>4. Dispatch GPS Fleet Telemetry</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_gps">
                <div class="form-group">
                    <label>Select Tracking Target Vehicle</label>
                    <select name="vehicle_id">
                        <?php if (empty($vehicles)): ?>
                            <option value="">-- No Vehicles Active (Add Form 1 First) --</option>
                        <?php else: ?>
                            <?php foreach($vehicles as $v): ?>
                                <option value="<?= $v['vehicle_id'] ?>"><?= htmlspecialchars($v['model_name'] . ' [' . $v['license_plate'] . ']') ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Google Maps Latitude Pin</label>
                    <input type="number" step="0.00000001" name="latitude" required>
                </div>
                <div class="form-group">
                    <label>Google Maps Longitude Pin</label>
                    <input type="number" step="0.00000001" name="longitude" required>
                </div>
                <div class="form-group">
                    <label>Current Speed (KM/H)</label>
                    <input type="number" step="0.01" name="speed_kmh" required>
                </div>
                <div class="form-group">
                    <label>Heading Vector (Degrees)</label>
                    <input type="number" step="0.01" name="heading_degrees" required>
                </div>
                <button type="submit" <?= empty($vehicles) ? 'disabled' : '' ?>>Transmit Telemetry Metrics</button>
            </form>
        </div>

        <div class="card">
            <h2>5. Pallet Management Trade Desk</h2>
            <form method="POST">
                <input type="hidden" name="action" value="sell_pallet">
                <div class="form-group">
                    <label>Storage Warehouse Location</label>
                    <select name="warehouse_id">
                        <?php foreach($warehouses as $w): ?>
                            <option value="<?= $w['warehouse_id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pallet Manufacturing Class</label>
                    <select name="pallet_type">
                        <option value="euro_wooden">Euro Wooden</option>
                        <option value="standard_plastic">Standard Plastic</option>
                        <option value="industrial_metal">Industrial Metal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Condition Quality Classification</label>
                    <select name="condition_grade">
                        <option value="new">Brand New Factory Specs</option>
                        <option value="used_grade_a">Used Grade A Cert</option>
                        <option value="used_grade_b">Used Grade B Standard</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity Unit Lot</label>
                    <input type="number" name="quantity_for_sale" required>
                </div>
                <div class="form-group">
                    <label>Unit Valuation Pricing</label>
                    <input type="number" step="0.01" name="price_per_unit" required>
                </div>
                <button type="submit">Publish Pallet Stock</button>
            </form>
        </div>

        <div class="card">
            <h2>6. Corporate Asset Rental Allocation</h2>
            <form method="POST">
                <input type="hidden" name="action" value="rent_vehicle">
                <div class="form-group">
                    <label>Employee Authorization Record</label>
                    <select name="employee_id">
                        <?php foreach($users as $u): if($u['role'] === 'employee' || $u['role'] === 'admin'): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name'] . ' (' . $u['role'] . ')') ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Target Fleet Asset</label>
                    <select name="vehicle_id">
                        <?php if (empty($avail_vehicles)): ?>
                            <option value="">-- No Available Vehicles Asset Active --</option>
                        <?php else: ?>
                            <?php foreach($avail_vehicles as $v): ?>
                                <option value="<?= $v['vehicle_id'] ?>"><?= htmlspecialchars($v['model_name'] . ' - ' . $v['vin']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rental Allocation Justification</label>
                    <textarea name="purpose" required rows="2"></textarea>
                </div>
                <button type="submit" <?= empty($avail_vehicles) ? 'disabled' : '' ?>>Approve Internal Rental Lease</button>
            </form>
        </div>

        <div class="card">
            <h2>7. Dispatch Customer Delivery Order</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_order">
                <div class="form-group">
                    <label>Customer Account Identity</label>
                    <select name="customer_id">
                        <?php foreach($users as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assigned Route Node</label>
                    <select name="route_id">
                        <?php if (empty($routes)): ?>
                            <option value="">-- No Active Routes (Add Form 2 First) --</option>
                        <?php else: ?>
                            <?php foreach($routes as $r): ?>
                                <option value="<?= $r['route_id'] ?>"><?= htmlspecialchars($r['origin_name'] . ' ➔ ' . $r['destination_name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Financial Total Invoicing</label>
                    <input type="number" step="0.01" name="total_price" required>
                </div>
                <div class="form-group">
                    <label>Google Maps Formatted Address String</label>
                    <input type="text" name="delivery_address_string" placeholder="e.g. 1600 Amphitheatre Pkwy" required>
                </div>
                <div class="form-group">
                    <label>Destination Lat Coordinates Pin</label>
                    <input type="number" step="0.00000001" name="delivery_latitude" required>
                </div>
                <div class="form-group">
                    <label>Destination Lng Coordinates Pin</label>
                    <input type="number" step="0.00000001" name="delivery_longitude" required>
                </div>
                <div class="form-group">
                    <label>Cargo Unit Weight (KG)</label>
                    <input type="number" step="0.01" name="weight_kg" required>
                </div>
                <div class="form-group">
                    <label>Cargo Unit Volume (M³)</label>
                    <input type="number" step="0.01" name="volume_m3" required>
                </div>
                <div class="form-group">
                    <label>Cargo Unit Handling Protocol</label>
                    <select name="handling_type">
                        <option value="general">General Cargo</option>
                        <option value="hazardous">Hazardous Materials</option>
                        <option value="perishable">Perishable Cold Chain</option>
                        <option value="high_value">High Value Cargo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assigned Segregation Zone (Optional Allocation)</label>
                    <select name="zone_id">
                        <option value="">No Active Zone Mapping (Direct Transit)</option>
                        <?php foreach($zones as $z): ?>
                            <option value="<?= $z['zone_id'] ?>"><?= htmlspecialchars($z['zone_code'] . ' (' . $z['segregation_category'] . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" <?= empty($routes) ? 'disabled' : '' ?>>Commit Order & Capture Input History</button>
            </form>
        </div>

        <div class="card">
            <h2>8. Loyalty & Premium Subscription Desk</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_subscription">
                <div class="form-group">
                    <label>Target Account Identity</label>
                    <select name="user_id">
                        <?php foreach($users as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Subscription Tier Level (Perks Matrix)</label>
                    <select name="plan_tier">
                        <option value="standard">Standard Account Tier</option>
                        <option value="silver_express">Silver Express Access</option>
                        <option value="gold_prime">Gold Prime Network Perks</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Activation Validity Date</label>
                    <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Termination Expiration Date</label>
                    <input type="date" name="end_date" required value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                </div>
                <button type="submit">Provision Account Access Tier</button>
            </form>
        </div>

        <div class="card" style="grid-column: 1 / -1;">
            <h2>9. Global Operations Real-Time Live Telemetry</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <?php
                $metric_vehicles = $pdo->query("SELECT COUNT(*) as total, SUM(current_mileage_km) as total_km FROM vehicles")->fetch();
                $metric_orders = $pdo->query("SELECT COUNT(*) as total, SUM(total_price) as revenue FROM customer_orders")->fetch();
                $metric_subs = $pdo->query("SELECT COUNT(*) as total FROM subscriptions WHERE status = 'active'")->fetch();
                $metric_pallets = $pdo->query("SELECT SUM(quantity_for_sale) as total_pallets, SUM(quantity_for_sale * price_per_unit) as stock_value FROM pallet_inventory")->fetch();
                ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 0.85em; text-transform: uppercase; color: #64748b; font-weight: 700;">Fleet Network Stature</div>
                    <div style="font-size: 1.8em; font-weight: 700; color: #0f172a; margin: 5px 0;"><?= intval($metric_vehicles['total']) ?> Units</div>
                    <div style="font-size: 0.85em; color: #b45309; font-weight: 600;"><?= number_format(floatval($metric_vehicles['total_km']), 1) ?> Fleet KM Logged</div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 0.85em; text-transform: uppercase; color: #64748b; font-weight: 700;">Pipeline Manifest Orders</div>
                    <div style="font-size: 1.8em; font-weight: 700; color: #0f172a; margin: 5px 0;"><?= intval($metric_orders['total']) ?> Manifests</div>
                    <div style="font-size: 0.85em; color: #b45309; font-weight: 600;">$<?= number_format(floatval($metric_orders['revenue']), 2) ?> Invoiced</div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 0.85em; text-transform: uppercase; color: #64748b; font-weight: 700;">Active Premium Subscribers</div>
                    <div style="font-size: 1.8em; font-weight: 700; color: #0f172a; margin: 5px 0;"><?= intval($metric_subs['total']) ?> Accounts</div>
                    <div style="font-size: 0.85em; color: #10b981; font-weight: 600;">Priority Logistics Tier Active</div>
                </div>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 6px; text-align: center;">
                    <div style="font-size: 0.85em; text-transform: uppercase; color: #64748b; font-weight: 700;">Pallet Trading Inventory</div>
                    <div style="font-size: 1.8em; font-weight: 700; color: #0f172a; margin: 5px 0;"><?= intval($metric_pallets['total_pallets']) ?> Available</div>
                    <div style="font-size: 0.85em; color: #b45309; font-weight: 600;">$<?= number_format(floatval($metric_pallets['stock_value']), 2) ?> Book Value</div>
                </div>
            </div>
        </div>

        <div class="card" style="grid-column: 1 / -1;">
            <h2>10. Real-Time Fleet Live GPS Telemetry Signals</h2>
            <div style="overflow-x: auto; margin-top: 15px;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9em;">
                    <thead>
                        <tr style="background: #f1f5f9; color: #475569; border-bottom: 2px solid #cbd5e1;">
                            <th style="padding: 10px;">Timestamp</th>
                            <th style="padding: 10px;">Vehicle Profile</th>
                            <th style="padding: 10px;">Google Maps Node Coordinates</th>
                            <th style="padding: 10px;">Velocity Vector</th>
                            <th style="padding: 10px;">Heading Vector</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $telemetry_logs = $pdo->query("SELECT g.*, v.model_name, v.license_plate FROM gps_telemetry g JOIN vehicles v ON g.vehicle_id = v.vehicle_id ORDER BY g.updated_at DESC LIMIT 5")->fetchAll();
                        if (empty($telemetry_logs)):
                        ?>
                            <tr>
                                <td colspan="5" style="padding: 15px; text-align: center; color: #94a3b8;">No active GPS coordinates transmitted to console yet. Utilize Form 4 to dispatch a ping.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($telemetry_logs as $log): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0; background: #fff;">
                                    <td style="padding: 10px; font-family: monospace; color: #64748b;"><?= htmlspecialchars($log['updated_at']) ?></td>
                                    <td style="padding: 10px; font-weight: 600; color: #1e293b;"><?= htmlspecialchars($log['model_name']) ?> <span style="font-weight: 400; font-family: monospace; font-size: 0.85em; color: #64748b;">[<?= htmlspecialchars($log['license_plate']) ?>]</span></td>
                                    <td style="padding: 10px; font-family: monospace; color: #b45309;"><a href__="https://www.google.com/maps?q=<?= floatval($log['latitude']) ?>,<?= floatval($log['longitude']) ?>" target="_blank" style="color: #d97706; text-decoration: none; font-weight: 600;">Lat: <?= floatval($log['latitude']) ?>, Lng: <?= floatval($log['longitude']) ?> ↗</a></td>
                                    <td style="padding: 10px; font-weight: 600; color: #0f172a;"><?= number_format(floatval($log['speed_kmh']), 1) ?> KM/H</td>
                                    <td style="padding: 10px; color: #475569;"><?= number_format(floatval($log['heading_degrees']), 1) ?>° Degree Vector</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
		<div class="card" style="grid-column: 1 / -1;">
    <h2>11. Live Fleet GPS Map</h2>
    <div style="display:flex; gap:10px; align-items:center; margin:10px 0;">
        <label style="margin:0; font-weight:600;">Auto-refresh:</label>
        <select id="refreshRate" onchange="setRefreshRate(this.value)">
            <option value="2000">Every 2 seconds</option>
            <option value="5000" selected>Every 5 seconds</option>
            <option value="10000">Every 10 seconds</option>
            <option value="0">Off</option>
        </select>
        <span id="lastUpdate" style="font-size:.85em; color:#64748b; margin-left:auto;">—</span>
    </div>
    <div id="map" style="height: 500px; border-radius: 8px; border: 1px solid #e2e8f0;"></div>
</div>
        </div>

    </div>
</div>
<script>
(function () {
    const API_URL = "get_latest_positions.php";
    let map, markers = {}, refreshTimer = null;

    const typeIcon = (type) => {
        const colors = { land_truck: '#d97706', air_cargo: '#3b82f6', water_vessel: '#0891b2' };
        const c = colors[type] || '#64748b';
        return L.divIcon({
            className: '',
            html: `<div style="background:${c};width:22px;height:22px;border-radius:50% 50% 50% 0;
                    transform:rotate(-45deg);border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.5);"></div>`,
            iconSize: [22, 22], iconAnchor: [11, 22]
        });
    };

    function initMap() {
    map = L.map('map').setView([52.2297, 21.0122], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© OpenStreetMap'
    }).addTo(map);
    // Force Leaflet to re-measure the container once it's laid out
    setTimeout(() => map.invalidateSize(), 100);
    window.addEventListener('resize', () => map.invalidateSize());
}

    async function fetchPositions() {
        try {
            const res = await fetch(API_URL + '?t=' + Date.now());
            const data = await res.json();
            const list = data.positions || [];
            const seen = new Set();
            list.forEach(p => {
                seen.add(String(p.vehicle_id));
                const lat = parseFloat(p.latitude), lng = parseFloat(p.longitude);
                const popup = `<b>${p.model_name}</b> [${p.license_plate || '—'}]<br>
                    Type: ${p.vehicle_type}<br>
                    Status: ${p.status}<br>
                    Speed: ${parseFloat(p.speed_kmh).toFixed(1)} km/h<br>
                    Heading: ${parseFloat(p.heading_degrees).toFixed(1)}°<br>
                    Updated: ${p.updated_at}`;
                if (markers[p.vehicle_id]) {
                    markers[p.vehicle_id].setLatLng([lat, lng]).bindPopup(popup);
                } else {
                    markers[p.vehicle_id] = L.marker([lat, lng], { icon: typeIcon(p.vehicle_type) })
                        .addTo(map).bindPopup(popup);
                }
            });
            // Remove markers for vehicles no longer returning
            Object.keys(markers).forEach(vid => {
                if (!seen.has(vid)) { map.removeLayer(markers[vid]); delete markers[vid]; }
            });
            // Auto-fit if there are positions
            if (list.length === 1) {
                map.setView([parseFloat(list[0].latitude), parseFloat(list[0].longitude)], 15);
            } else if (list.length > 1) {
                const bounds = list.map(p => [parseFloat(p.latitude), parseFloat(p.longitude)]);
                map.fitBounds(bounds, { padding: [40, 40] });
            }
            document.getElementById('lastUpdate').textContent =
                'Updated: ' + new Date().toLocaleTimeString();
        } catch (e) {
            document.getElementById('lastUpdate').textContent = 'Error: ' + e.message;
        }
    }

    window.setRefreshRate = function (ms) {
        if (refreshTimer) { clearInterval(refreshTimer); refreshTimer = null; }
        if (parseInt(ms) > 0) refreshTimer = setInterval(fetchPositions, parseInt(ms));
    };

    initMap();
    fetchPositions();
    setRefreshRate('5000');
})();
</script>
</body>
</html>