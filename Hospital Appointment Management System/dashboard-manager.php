<?php
require_once 'includes/auth-check.php';
requireRole(['manager']);
require_once 'config/database.php';

$currentUser = getCurrentUser();

// Initialize variables
$payments = [];
$pendingPayments = 0;
$pendingAppointments = 0;
$error = null;

// Get payment data
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get pending payments
    $stmt = $conn->query("
        SELECT p.id, p.amount, p.payment_method, p.status, p.payment_date as created_at,
               u.name as patient_name, u.phone
        FROM payments p
        JOIN appointments a ON p.appointment_id = a.id
        JOIN patients pt ON a.patient_id = pt.id
        JOIN users u ON pt.user_id = u.id
        WHERE p.status = 'pending'
        ORDER BY p.payment_date DESC
        LIMIT 20
    ");
    $payments = $stmt->fetchAll();
    
    // Count stats
    $stmt = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'");
    $pendingPayments = $stmt->fetch()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'");
    $pendingAppointments = $stmt->fetch()['total'];
    
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
    <title>Manager Dashboard - Hospital Management</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 20px;
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
            color: #f59e0b;
            margin-bottom: 15px;
        }
        .dashboard-card h3 {
            margin: 10px 0;
            color: #333;
        }
        .dashboard-card p {
            font-size: 2rem;
            font-weight: 700;
            color: #f59e0b;
            margin: 10px 0;
        }
        .data-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
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
    <div class="dashboard-header">
        <div class="dashboard-nav">
            <div class="dashboard-title">
                <i class="fas fa-clipboard-check"></i> Manager Dashboard
            </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($currentUser['name']); ?></span>
                <a href="auth-process.php?action=logout" class="btn" style="background: #dc2626; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px;">Logout</a>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        <?php if (isset($error)): ?>
            <div style="background: #fee; color: #c33; padding: 15px; margin: 20px 0; border-radius: 5px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Manager Actions Grid -->
        <div class="dashboard-grid">
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('payments')">
                <i class="fas fa-money-check-alt"></i>
                <h3>Verify Payments</h3>
                <p>Approve/Reject</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('tokens')">
                <i class="fas fa-ticket-alt"></i>
                <h3>Issue Tokens</h3>
                <p>Generate booking tokens</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('lifecycle')">
                <i class="fas fa-sync-alt"></i>
                <h3>Appointment Lifecycle</h3>
                <p>Track & Update</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('reports')">
                <i class="fas fa-chart-bar"></i>
                <h3>Reports</h3>
                <p>Generate & Export</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>Pending Payments</h3>
                <p><?php echo $pendingPayments; ?></p>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-calendar-check"></i>
                <h3>Pending Appointments</h3>
                <p><?php echo $pendingAppointments; ?></p>
            </div>
        </div>

        <!-- Payments Verification Section -->
        <div id="payments-section" class="data-table" style="display: block;">
            <div class="table-header">
                <h3><i class="fas fa-money-bill"></i> Verify & Confirm Payments</h3>
            </div>
            <div class="table-content" style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                                    No pending payments
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['phone']); ?></td>
                                    <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($payment['created_at'])); ?></td>
                                    <td>
                                        <a href="#" class="btn btn-success" onclick="verifyPayment(<?php echo $payment['id']; ?>)">Verify</a>
                                        <a href="#" class="btn btn-danger" onclick="rejectPayment(<?php echo $payment['id']; ?>)">Reject</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Issue Tokens Section -->
        <div id="tokens-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-ticket-alt"></i> Issue Booking Tokens</h3>
            </div>
            <div class="table-content" style="padding: 20px;">
                <div id="verified-payments-list">
                    <?php
                    // Get verified payments
                    $stmt = $conn->query("
                        SELECT p.id, p.appointment_id, p.amount, u.name as patient_name, 
                               a.appointment_date, a.appointment_time
                        FROM payments p
                        JOIN appointments a ON p.appointment_id = a.id
                        JOIN patients pt ON a.patient_id = pt.id
                        JOIN users u ON pt.user_id = u.id
                        WHERE p.status = 'completed'
                        ORDER BY p.verified_at DESC
                        LIMIT 20
                    ");
                    $verifiedPayments = $stmt->fetchAll();
                    ?>
                    <table>
                    <thead>
                        <tr>
                                <th>Patient</th>
                            <th>Appointment</th>
                                <th>Amount</th>
                                <th>Token Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php if (empty($verifiedPayments)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">No verified payments available</td></tr>
                            <?php else: ?>
                                <?php foreach ($verifiedPayments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['patient_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($payment['appointment_date'])); ?> <?php echo date('h:i A', strtotime($payment['appointment_time'])); ?></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><span id="token-status-<?php echo $payment['id']; ?>">Not Issued</span></td>
                                        <td>
                                            <button class="btn btn-success" onclick="issueToken(<?php echo $payment['id']; ?>, <?php echo $payment['appointment_id']; ?>)">
                                                Issue Token
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>

        <!-- Appointment Lifecycle Section -->
        <div id="lifecycle-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-sync-alt"></i> Appointment Lifecycle Tracking</h3>
                <div style="display: flex; gap: 10px;">
                    <select id="lifecycle-filter-status" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                    </select>
                    <select id="lifecycle-filter-doctor" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">All Doctors</option>
                        </select>
                    <input type="date" id="lifecycle-filter-date" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
            </div>
            <div class="table-content" style="padding: 20px; overflow-x: auto;">
                <table id="lifecycle-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Current Status</th>
                            <th>Change Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="lifecycle-tbody">
                        <tr><td colspan="7" style="text-align: center; padding: 30px; color: #999;">Loading appointments...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Reports Section -->
        <div id="reports-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-chart-bar"></i> Payment & Booking Reports</h3>
            </div>
            <div class="table-content" style="padding: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div>
                        <label>Report Type *</label>
                        <select id="report-type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="daily">Daily Report</option>
                        <option value="weekly">Weekly Report</option>
                        <option value="monthly">Monthly Report</option>
                            <option value="custom">Custom Range</option>
                    </select>
                </div>
                    <div>
                        <label>Start Date</label>
                        <input type="date" id="report-start-date" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
                    <div>
                        <label>End Date</label>
                        <input type="date" id="report-end-date" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-bottom: 30px;">
                    <button class="btn btn-success" onclick="generateReport()" style="padding: 12px 30px;">
                        <i class="fas fa-chart-line"></i> Generate Report
                    </button>
                    <button class="btn" onclick="exportReport('pdf')" style="padding: 12px 30px;">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                    <button class="btn" onclick="exportReport('csv')" style="padding: 12px 30px;">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                </div>
                <div id="report-results" style="background: #f9f9f9; padding: 20px; border-radius: 8px; min-height: 200px;">
                    <p style="color: #666; text-align: center;">Generated report will appear here</p>
            </div>
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

        function verifyPayment(paymentId) {
            if (confirm('Approve this payment?')) {
                fetch('api/appointments.php?action=verify-payment&id=' + paymentId, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Payment verified successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                });
            }
        }

        function rejectPayment(paymentId) {
            if (confirm('Reject this payment?')) {
                fetch('api/appointments.php?action=reject-payment&id=' + paymentId, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Payment rejected');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                });
            }
        }

        function issueToken(paymentId, appointmentId) {
            if (confirm('Issue booking token for this appointment?')) {
                // Generate unique token
                const token = 'TKN' + Date.now() + Math.random().toString(36).substr(2, 9).toUpperCase();
                
                fetch('api/appointments.php?action=issue-token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        payment_id: paymentId,
                        appointment_id: appointmentId,
                        token: token
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        document.getElementById('token-status-' + paymentId).innerHTML = 
                            '<span style="color: #10b981;">Token Issued: ' + token + '</span>';
                        alert('Token issued: ' + token + '\nSent to patient.');
                    } else {
                        alert('Error: ' + result.message);
                    }
                });
            }
        }

        function generateReport() {
            const reportType = document.getElementById('report-type').value;
            const startDate = document.getElementById('report-start-date').value;
            const endDate = document.getElementById('report-end-date').value;
            
            fetch('api/admin.php?action=generate-report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: reportType,
                    start_date: startDate,
                    end_date: endDate
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    document.getElementById('report-results').innerHTML = 
                        '<h4>Report Generated</h4>' +
                        '<p>Total Payments: $' + (result.data.total_payments || 0) + '</p>' +
                        '<p>Total Appointments: ' + (result.data.total_appointments || 0) + '</p>' +
                        '<p>Completed: ' + (result.data.completed || 0) + '</p>' +
                        '<p>Pending: ' + (result.data.pending || 0) + '</p>';
                } else {
                    alert('Error: ' + result.message);
                }
            });
        }

        function exportReport(format) {
            const reportType = document.getElementById('report-type').value;
            const startDate = document.getElementById('report-start-date').value;
            const endDate = document.getElementById('report-end-date').value;
            
            window.location.href = 'api/admin.php?action=export-report&type=' + reportType + 
                                  '&start_date=' + startDate + '&end_date=' + endDate + '&format=' + format;
        }
    </script>
</body>
</html>
