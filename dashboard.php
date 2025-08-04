<?php
session_start();

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ====================
// Database Connection
// ====================
$host = 'localhost';
$dbname = 'tacmsystem';
$username = 'root';
$password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ============================
// Excel Import Without Composer
// ============================
// 1. Download SimpleXLSX.php into this same folder
require_once 'SimpleXLSX.php';

// 2. Handle form submission for Excel import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel_nocomposer') {
    if (isset($_FILES['excel_file']['tmp_name']) && is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
        $xlsx = SimpleXLSX::parse($_FILES['excel_file']['tmp_name']);
        if (!$xlsx) {
            die('Failed to parse Excel file.');
        }
        $rows = $xlsx->rows();
        // Skip header row; assume columns: full_name, age, email, address
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) >= 4) {
                [$name, $age, $email, $address] = $row;
                // Insert into users table
                $stmt = $pdo->prepare('INSERT INTO users (full_name, age, email, address) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, (int)$age, $email, $address]);
            }
        }
        echo "<div class='alert alert-success'>✅ Excel data imported successfully! Refresh to view.</div>";
    } else {
        echo "<div class='alert alert-danger'>⚠️ Please upload a valid .xlsx file.</div>";
    }
}

// ====================
// Handle Other Actions
// ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_user':
            if (!isset($_POST['address'])) die("Address field is missing.");
            $stmt = $pdo->prepare('INSERT INTO users (full_name, age, email, address) VALUES (:name, :age, :email, :address)');
            $stmt->execute([
                ':name' => $_POST['full_name'],
                ':age' => $_POST['age'],
                ':email' => $_POST['email'],
                ':address' => $_POST['address']
            ]);
            break;

        case 'edit_user':
            $stmt = $pdo->prepare('UPDATE users SET full_name = :name, age = :age, email = :email, address = :address WHERE id = :id');
            $stmt->execute([
                ':name' => $_POST['full_name'],
                ':age' => $_POST['age'],
                ':email' => $_POST['email'],
                ':address' => $_POST['address'],
                ':id' => $_POST['id']
            ]);
            break;

        case 'delete_user':
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $_POST['id']]);
            break;

        case 'add_performance':
            $stmt = $pdo->prepare('INSERT INTO performance (user_id, score, task_name, progress_level, date_added) VALUES (:user_id, :score, :task_name, :progress_level, :date_added)');
            $stmt->execute([
                ':user_id' => $_POST['user_id'],
                ':score' => $_POST['score'],
                ':task_name' => $_POST['task_name'],
                ':progress_level' => $_POST['progress_level'],
                ':date_added' => date('Y-m-d')
            ]);
            break;
    }
}

// Fetch data for display
$users = $pdo->query('SELECT * FROM users')->fetchAll(PDO::FETCH_ASSOC);
$performance_data = $pdo->query('SELECT p.*, u.full_name FROM performance p JOIN users u ON p.user_id = u.id')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>User Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
    .logout-btn:hover {
        color: #0d6efd !important; /* Bootstrap primary blue text */
        border-color: #0d6efd !important; /* Bootstrap primary blue border */
        background-color: transparent !important; /* Keep transparent background */
    }
    </style>
</head>
<body>
<div class="container my-5">
    <h1 class="mb-4">User Management System</h1>

<!-- Main Tabs -->
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#userManagement">User Management</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#performanceData">Performance Data</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#userSummary">User Summary</button>
    </li>
    <!-- Logout Button -->
    <li class="nav-item ms-auto">
        <form method="POST" action="logout.php" class="d-inline">
            <button type="submit" class="btn btn-outline-danger nav-link logout-btn" style="cursor:pointer;">Logout</button>
        </form>
    </li>
</ul>

    <div class="tab-content">
        <!-- User Management Tab -->
        <div class="tab-pane fade show active" id="userManagement">
            <ul class="nav nav-tabs mb-3">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#addUser">Add New User</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#usersList">Users List</button></li>
            </ul>

            <div class="tab-content">
                <!-- Add New User + Excel Import -->
                <div class="tab-pane fade show active" id="addUser">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Add New User</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_user" />
                                <div class="mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" required /></div>
                                <div class="mb-3"><label class="form-label">Age</label><input type="number" class="form-control" name="age" required /></div>
                                <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required /></div>
                                <div class="mb-3"><label class="form-label">Address</label><input type="text" class="form-control" name="address" required /></div>
                                <button type="submit" class="btn btn-primary">Add User</button>
                            </form>
                        </div>
                    </div>

                    <!-- Excel Import Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Import Users via Excel (.xlsx)</h5>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_excel_nocomposer" />
                                <div class="mb-3">
                                    <label class="form-label">Choose Excel File (.xlsx)</label>
                                    <input type="file" class="form-control" name="excel_file" accept=".xlsx" required />
                                </div>
                                <button type="submit" class="btn btn-success">Import Excel</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Users List Tab -->
                <div class="tab-pane fade" id="usersList">
                    <h2>Users</h2>
                    <table class="table table-striped">
                        <thead><tr><th>ID</th><th>Name</th><th>Age</th><th>Email</th><th>Address</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= $u['age'] ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['address']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUser<?= $u['id'] ?>">Edit</button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_user" />
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>" />
                                        <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <!-- Modal for Edit -->
                            <div class="modal fade" id="editUser<?= $u['id'] ?>">
                                <div class="modal-dialog"><div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit User</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="edit_user" />
                                            <input type="hidden" name="id" value="<?= $u['id'] ?>" />
                                            <div class="mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($u['full_name']) ?>" required /></div>
                                            <div class="mb-3"><label class="form-label">Age</label><input type="number" class="form-control" name="age" value="<?= $u['age'] ?>" required /></div>
                                            <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($u['email']) ?>" required /></div>
                                            <div class="mb-3"><label class="form-label">Address</label><input type="text" class="form-control" name="address" value="<?= htmlspecialchars($u['address']) ?>" required /></div>

                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </form>
                                    </div>
                                </div></div>
                            </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Performance Data Tab -->
        <div class="tab-pane fade" id="performanceData">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Add Performance Data</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_performance" />
                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <select class="form-select" name="user_id" required>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Score</label><input type="number" class="form-control" name="score" required /></div>
                        <div class="mb-3"><label class="form-label">Task Name</label><input type="text" class="form-control" name="task_name" required /></div>
                        <div class="mb-3"><label class="form-label">Progress Level (%)</label><input type="number" class="form-control" name="progress_level" min="0" max="100" required /></div>
                        <button type="submit" class="btn btn-primary">Add Performance</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- User Summary Tab -->
        <div class="tab-pane fade" id="userSummary">
            <h2>Performance Summary</h2>
            <table class="table table-striped">
                <thead><tr><th>User</th><th>Task</th><th>Score</th><th>Progress</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($performance_data as $perf): ?>
                    <tr>
                        <td><?= htmlspecialchars($perf['full_name']) ?></td>
                        <td><?= htmlspecialchars($perf['task_name']) ?></td>
                        <td><?= $perf['score'] ?></td>
                        <td><?= $perf['progress_level'] ?>%</td>
                        <td><?= $perf['date_added'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
