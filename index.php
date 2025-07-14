<?php
$host = getenv('MYSQL_HOST');
$username = "root";
$password = getenv('MYSQL_ROOT_PASSWORD');
$database = getenv('MYSQL_DATABASE');

// Create connection
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset(getenv('CHARACTER_SET_SERVER') ?: 'utf8mb4');

// Create tables if not exists
$conn->query("CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('stm32','esp','arduino','other') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS fans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    pin_number INT NOT NULL,
    status ENUM('on','off') NOT NULL DEFAULT 'off',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
)");

// Handle device insert
if (isset($_POST['add_device'])) {
    $name = $conn->real_escape_string($_POST['device_name']);
    $type = $conn->real_escape_string($_POST['device_type']);
    $conn->query("INSERT INTO devices (name, type) VALUES ('$name', '$type')");
}

// Handle fan insert
if (isset($_POST['add_fan'])) {
    $device_id = (int)$_POST['device_id'];
    $name = $conn->real_escape_string($_POST['fan_name']);
    $pin_number = (int)$_POST['pin_number'];
    $conn->query("INSERT INTO fans (device_id, name, pin_number) VALUES ($device_id, '$name', $pin_number)");
}

// Handle delete fan
if (isset($_POST['delete_fan'])) {
    $id = (int)$_POST['delete_fan'];
    $conn->query("DELETE FROM fans WHERE id=$id");
}

// Handle toggle fan status
if (isset($_POST['toggle_fan'])) {
    $id = (int)$_POST['toggle_fan'];
    $result = $conn->query("SELECT status FROM fans WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $new_status = $row['status'] === 'on' ? 'off' : 'on';
        $conn->query("UPDATE fans SET status='$new_status' WHERE id=$id");
    }
}

// Fetch all devices
$devices = $conn->query("SELECT * FROM devices ORDER BY name");

// Fetch all fans with device info
$fans = $conn->query("
    SELECT f.*, d.name as device_name, d.type as device_type 
    FROM fans f
    JOIN devices d ON f.device_id = d.id
    ORDER BY d.name, f.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microcontroller Fan Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style/style.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <h1 class="text-center mb-4">Microcontroller Fan Control</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3>Add New Device</h3>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-3">
                                <label for="device_name" class="form-label">Device Name</label>
                                <input type="text" class="form-control" id="device_name" name="device_name" placeholder="e.g., Living Room Controller" required>
                            </div>
                            <div class="mb-3">
                                <label for="device_type" class="form-label">Device Type</label>
                                <select class="form-select" id="device_type" name="device_type" required>
                                    <option value="stm32">STM32</option>
                                    <option value="esp">ESP</option>
                                    <option value="arduino">Arduino</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" name="add_device">Add Device</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3>Add New Fan</h3>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <div class="mb-3">
                                <label for="device_id" class="form-label">Device</label>
                                <select class="form-select" id="device_id" name="device_id" required>
                                    <?php while($device = $devices->fetch_assoc()): ?>
                                        <option value="<?= $device['id'] ?>">
                                            <?= htmlspecialchars($device['name']) ?> (<?= ucfirst($device['type']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="fan_name" class="form-label">Fan Name</label>
                                <input type="text" class="form-control" id="fan_name" name="fan_name" placeholder="e.g., Cooling Fan 1" required>
                            </div>
                            <div class="mb-3">
                                <label for="pin_number" class="form-label">GPIO Pin Number</label>
                                <input type="number" class="form-control" id="pin_number" name="pin_number" min="0" max="40" required>
                            </div>
                            <button type="submit" class="btn btn-success" name="add_fan">Add Fan</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h3>Fan Control Panel</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Fan Name</th>
                                <th>Device</th>
                                <th>GPIO Pin</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($fan = $fans->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($fan['name']) ?></td>
                                <td>
                                    <?= htmlspecialchars($fan['device_name']) ?>
                                    <span class="badge bg-secondary device-badge"><?= ucfirst($fan['device_type']) ?></span>
                                </td>
                                <td><?= $fan['pin_number'] ?></td>
                                <td class="<?= $fan['status'] === 'on' ? 'status-on' : 'status-off' ?>">
                                    <?= ucfirst($fan['status']) ?>
                                </td>
                                <td class="action-buttons">
                                    <form method="post" class="d-inline">
                                        <button type="submit" name="toggle_fan" value="<?= $fan['id'] ?>" 
                                            class="btn btn-sm <?= $fan['status'] === 'on' ? 'btn-warning' : 'btn-success' ?>">
                                            <?= $fan['status'] === 'on' ? 'Turn Off' : 'Turn On' ?>
                                        </button>
                                    </form>
                                    <form method="post" class="d-inline">
                                        <button type="submit" name="delete_fan" value="<?= $fan['id'] ?>" 
                                            class="btn btn-sm btn-danger" 
                                            onclick="return confirm('Are you sure you want to delete this fan?')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>     