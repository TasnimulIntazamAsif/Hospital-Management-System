<?php
require_once 'includes/auth-check.php';
requireRole(['admin']);
require_once 'config/database.php';

$currentUser = getCurrentUser();

// Get success/error messages
$successMessage = $_GET['success'] ?? '';
$errorMessage = $_GET['error'] ?? '';

// Get dashboard stats
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
    $totalUsers = $stmt->fetch()['count'];
    
    // Total appointments
    $stmt = $conn->query("SELECT COUNT(*) as count FROM appointments");
    $totalAppointments = $stmt->fetch()['count'];
    
    // Active doctors
    $stmt = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status = 'approved'");
    $activeDoctors = $stmt->fetch()['count'];
    
    // Pending doctors
    $stmt = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status = 'pending'");
    $pendingDoctors = $stmt->fetch()['count'];
    
    // Get pending doctors list
    $stmt = $conn->query("
        SELECT u.name, u.email, u.phone, d.specialty, d.license_number, 
               d.experience_years, d.consultation_fee, d.photo_filename, d.id
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE d.status = 'pending'
        ORDER BY d.created_at DESC
    ");
    $pendingDoctorsList = $stmt->fetchAll();
    
    // Get all users
    $stmt = $conn->query("
        SELECT u.id, u.name, u.email, u.phone, u.role, u.status, u.created_at
        FROM users u
        ORDER BY u.created_at DESC
        LIMIT 50
    ");
    $usersList = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Hospital Management</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .dashboard-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dashboard-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        .dashboard-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .dashboard-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .dashboard-card i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        .dashboard-card h3 {
            margin: 10px 0;
            color: #333;
        }
        .dashboard-card p {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin: 10px 0;
        }
        .data-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        .table-header h3 {
            margin: 0;
            color: #333;
        }
        .table-content {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #dc2626; color: white; }
    </style>
</head>
<body>
    <!-- Dashboard Navigation -->
    <div class="dashboard-header">
        <div class="dashboard-nav">
            <div class="dashboard-title">
                <i class="fas fa-user-tie"></i>
                Admin Dashboard
            </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($currentUser['name']); ?></span>
                <a href="auth-process.php?action=logout" class="btn" style="background: #dc2626; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px;">Logout</a>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <?php if ($errorMessage): ?>
            <div style="background: #fee; color: #c33; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #dc2626;">
                <strong>❌ Error:</strong> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($successMessage): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #10b981;">
                <strong>✅ Success:</strong> <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div style="background: #fee; color: #c33; padding: 15px; margin: 20px 0; border-radius: 5px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Admin Actions Grid -->
        <div class="dashboard-grid">
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('manage-profiles')">
                <i class="fas fa-user-cog"></i>
                <h3>Manage Profiles</h3>
                <p>Add, update, or delete users</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('doctor-schedules')">
                <i class="fas fa-calendar-alt"></i>
                <h3>Doctor Schedules</h3>
                <p>Manage availability</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('records')">
                <i class="fas fa-file-medical"></i>
                <h3>Records</h3>
                <p>View all appointments</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('appointments')">
                <i class="fas fa-clipboard-list"></i>
                <h3>Appointments</h3>
                <p>Cancel & monitor</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('audit-logs')">
                <i class="fas fa-shield-alt"></i>
                <h3>Audit Logs</h3>
                <p>Security & compliance</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('create-user')">
                <i class="fas fa-user-plus"></i>
                <h3>Create Manager/Employee</h3>
                <p>Add new users</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <i class="fas fa-users"></i>
                <h3>Total Users</h3>
                <p><?php echo $totalUsers; ?></p>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Total Appointments</h3>
                <p><?php echo $totalAppointments; ?></p>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-user-md"></i>
                <h3>Active Doctors</h3>
                <p><?php echo $activeDoctors; ?></p>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-clock"></i>
                <h3>Pending Doctors</h3>
                <p><?php echo $pendingDoctors; ?></p>
            </div>
        </div>

        <!-- Pending Doctor Registrations Section -->
        <div class="data-table">
            <div class="table-header">
                <h3><i class="fas fa-user-md"></i> Pending Doctor Registrations</h3>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Specialty</th>
                            <th>License Number</th>
                            <th>Experience</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pendingDoctorsList)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                                    No pending doctor registrations
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingDoctorsList as $doctor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($doctor['name']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['specialty']); ?></td>
                                    <td><?php echo htmlspecialchars($doctor['license_number']); ?></td>
                                    <td><?php echo $doctor['experience_years']; ?> years</td>
                                    <td>
                                        <a href="api/admin.php?action=approve-doctor&id=<?php echo $doctor['id']; ?>" class="btn btn-success">Approve</a>
                                        <a href="api/admin.php?action=reject-doctor&id=<?php echo $doctor['id']; ?>" class="btn btn-danger">Reject</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Manage Profiles Section -->
        <div class="data-table">
            <div class="table-header">
                <h3><i class="fas fa-users"></i> All Users</h3>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><span style="background: #667eea; color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.85rem;"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><span style="background: <?php echo $user['status'] === 'active' ? '#10b981' : '#f59e0b'; ?>; color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.85rem;"><?php echo ucfirst($user['status']); ?></span></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Dynamic Sections (Hidden by default) -->
        
        <!-- Manage Profiles Section -->
        <div id="manage-profiles-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-user-cog"></i> Manage Profiles</h3>
                <button class="btn btn-primary" onclick="showAddUserForm()">Add New User</button>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersList as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><span class="badge"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><span class="badge <?php echo $user['status'] === 'active' ? 'success' : 'warning'; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                <td>
                                    <a href="#" class="btn btn-sm" onclick="editUser(<?php echo $user['id']; ?>)">Edit</a>
                                    <a href="#" class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Doctor Schedules Section -->
        <div id="doctor-schedules-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-calendar-alt"></i> Doctor Schedules & Availability</h3>
            </div>
            <div class="table-content">
                <p style="padding: 20px; color: #666;">Schedule management feature coming soon. Interface ready.</p>
            </div>
        </div>

        <!-- Records/Appointments Section -->
        <div id="records-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-file-medical"></i> All Appointment Records</h3>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="search-appointments" placeholder="Search..." style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                    <select id="filter-status" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button class="btn btn-success" onclick="exportRecords()"><i class="fas fa-download"></i> Export</button>
                </div>
            </div>
            <div class="table-content">
                <p style="padding: 20px; color: #666;">Loading appointment records...</p>
            </div>
        </div>

        <!-- Appointments Management Section -->
        <div id="appointments-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-clipboard-list"></i> Manage Appointments & Monitor Activity</h3>
            </div>
            <div class="table-content">
                <p style="padding: 20px; color: #666;">Appointment management interface will show all appointments with cancel functionality. Activity log integration ready.</p>
        </div>
    </div>

        <!-- Audit Logs Section -->
        <div id="audit-logs-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-shield-alt"></i> Security Audit Logs</h3>
                <button class="btn btn-primary" onclick="refreshAuditLogs()"><i class="fas fa-sync"></i> Refresh</button>
            </div>
            <div class="table-content">
                <p style="padding: 20px; color: #666;">Loading audit logs...</p>
        </div>
    </div>

        <!-- Create Manager/Employee Section -->
        <div id="create-user-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-user-plus"></i> Create New Manager/Employee</h3>
            </div>
            <div class="table-content" style="padding: 20px;">
                <form id="create-user-form" onsubmit="createUser(event)">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label>Full Name *</label>
                            <input type="text" name="name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                        <div>
                            <label>Email *</label>
                            <input type="email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                        <div>
                            <label>Phone *</label>
                            <input type="tel" name="phone" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                        <div>
                            <label>Password *</label>
                            <input type="password" name="password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                        <div>
                            <label>Role *</label>
                            <select name="role" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <option value="manager">Manager</option>
                                <option value="doctor">Doctor</option>
                                <option value="patient">Patient</option>
                            </select>
                        </div>
                        <div>
                            <label>Status *</label>
                            <select name="status" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 20px;"><i class="fas fa-plus"></i> Create User</button>
                </form>
            </div>
        </div>

    </div>

    <script>
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.data-table').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            const section = document.getElementById(sectionName + '-section');
            if (section) {
                section.style.display = 'block';
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function createUser(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            fetch('api/admin.php?action=create-user', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('User created successfully!');
                    form.reset();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                alert('Error creating user: ' + error);
            });
        }

        function editUser(userId) {
            alert('Edit user: ' + userId + '\nFeature implementation pending.');
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                fetch('api/admin.php?action=delete-user&id=' + userId, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('User deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                });
            }
        }

        function exportRecords() {
            alert('Export feature will generate CSV/PDF of appointment records.');
        }

        function refreshAuditLogs() {
            alert('Loading latest audit log entries...');
        }

        function showAddUserForm() {
            showSection('create-user');
        }
    </script>
</body>
</html>
