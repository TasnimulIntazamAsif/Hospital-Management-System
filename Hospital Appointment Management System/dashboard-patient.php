<?php
require_once 'includes/auth-check.php';
requireRole(['patient']);
require_once 'config/database.php';

$currentUser = getCurrentUser();

// Get patient appointments
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get patient info to get patient_id
    $stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $patientInfo = $stmt->fetch();
    $patientId = $patientInfo ? $patientInfo['id'] : null;
    
    $appointments = [];
    if ($patientId) {
        // Get patient appointments
        $stmt = $conn->prepare("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason,
                   u.name as doctor_name, d.specialty, d.consultation_fee
            FROM appointments a
            JOIN doctors d ON a.doctor_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 20
        ");
        
        $stmt->execute([$patientId]);
        $appointments = $stmt->fetchAll();
    }
    
    // Get available doctors
    $stmt = $conn->query("
        SELECT u.name, d.specialty, d.consultation_fee, d.id
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE d.status = 'approved'
        ORDER BY u.name
    ");
    $doctors = $stmt->fetchAll();
    
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
    <title>Patient Dashboard - Hospital Management</title>
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
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
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
        .dashboard-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        .btn-primary { background: #2563eb; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        .status-pending { background: #f59e0b; color: white; }
        .status-confirmed { background: #10b981; color: white; }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-nav">
            <div class="dashboard-title">
                <i class="fas fa-user"></i> Patient Dashboard
            </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($currentUser['name']); ?></span>
                <a href="auth-process.php?action=logout" class="btn" style="background: #dc2626; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px;">Logout</a>
            </div>
        </div>
    </div>

    <div class="dashboard-content">
        
        <!-- Patient Actions Grid -->
        <div class="dashboard-grid">
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('find-doctor')">
                <i class="fas fa-search"></i>
                <h3>Find Doctor</h3>
                <p>Search by specialty</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('appointments')">
                <i class="fas fa-calendar-check"></i>
                <h3>My Appointments</h3>
                <p>Book & Manage</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('payments')">
                <i class="fas fa-credit-card"></i>
                <h3>Payments</h3>
                <p>Secure payment</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('history')">
                <i class="fas fa-history"></i>
                <h3>History</h3>
                <p>Bookings & Payments</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('messages')">
                <i class="fas fa-comments"></i>
                <h3>Messages</h3>
                <p>Chat with doctors</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('profile')">
                <i class="fas fa-user-edit"></i>
                <h3>My Profile</h3>
                <p>Update information</p>
            </div>
        </div>

        <!-- Booking Form Modal -->
        <div id="booking-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; padding: 20px;">
            <div style="max-width: 600px; background: white; margin: 50px auto; padding: 30px; border-radius: 10px; max-height: 90vh; overflow-y: auto;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Book New Appointment</h2>
                    <button onclick="closeBookingModal()" style="background: #dc2626; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer;">✕ Close</button>
                </div>
                <form id="booking-form" onsubmit="submitBooking(event)">
                    <div style="margin-bottom: 15px;">
                        <label>Select Doctor *</label>
                        <select name="doctor_id" id="booking-doctor" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="">Choose a doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" data-fee="<?php echo $doctor['consultation_fee']; ?>">
                                    <?php echo htmlspecialchars($doctor['name']); ?> - <?php echo htmlspecialchars($doctor['specialty']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label>Appointment Date *</label>
                        <input type="date" name="appointment_date" id="booking-date" required min="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label>Appointment Time *</label>
                        <input type="time" name="appointment_time" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label>Reason for Visit *</label>
                        <textarea name="reason" required rows="3" placeholder="Describe your symptoms or reason for the appointment" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label>Consultation Fee</label>
                        <input type="text" id="booking-fee" readonly style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #f5f5f5;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px;">Book Appointment</button>
                </form>
            </div>
        </div>

        <!-- Find Doctor Section -->
        <div id="find-doctor-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-search"></i> Find Doctor by Specialty</h3>
                <select id="specialty-filter" onchange="filterDoctors(this.value)" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="">All Specialties</option>
                    <option value="cardiologist">Cardiologist</option>
                    <option value="neurologist">Neurologist</option>
                    <option value="orthopedic">Orthopedic</option>
                    <option value="pediatrician">Pediatrician</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div class="table-content" style="padding: 20px;">
                <div class="dashboard-grid" id="doctors-grid">
                    <?php foreach ($doctors as $doctor): ?>
                        <div class="dashboard-card" style="text-align: left; cursor: pointer;" onclick="bookWithDoctor(<?php echo $doctor['id']; ?>)">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h4 style="margin: 0;"><?php echo htmlspecialchars($doctor['name']); ?></h4>
                                <span style="background: #10b981; color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.85rem;">Available</span>
                            </div>
                            <p style="margin: 5px 0; color: #666;"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($doctor['specialty']); ?></p>
                            <p style="margin: 5px 0; color: #666;"><i class="fas fa-dollar-sign"></i> $<?php echo number_format($doctor['consultation_fee'], 2); ?></p>
                            <button class="btn btn-primary" style="width: 100%; margin-top: 10px;">Book Appointment</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- My Appointments Section -->
        <div id="appointments-section" class="data-table" style="display: block;">
            <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px;">
                <h3 style="margin: 0;"><i class="fas fa-calendar-check"></i> My Appointments</h3>
                <button class="btn btn-primary" onclick="showBookingForm()">Book New Appointment</button>
            </div>
            <div class="table-content" style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Doctor</th>
                            <th>Specialty</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                                    No appointments yet. <a href="#" onclick="showBookingForm(); return false;" style="color: #2563eb; text-decoration: underline;">Book one now!</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $apt): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($apt['specialty']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $apt['status']; ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($apt['status'] === 'pending'): ?>
                                            <a href="appointment-action.php?action=cancel&id=<?php echo $apt['id']; ?>" class="btn btn-danger">Cancel</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments Section -->
        <div id="payments-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-credit-card"></i> Make Payment</h3>
            </div>
            <div class="table-content" style="padding: 20px;">
                <div id="payment-form" style="max-width: 600px; margin: 0 auto;">
                    <div style="margin-bottom: 20px;">
                        <label>Appointment *</label>
                        <select id="appointment-select" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="">Select Appointment</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label>Payment Method *</label>
                        <select id="payment-method" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="credit_card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="mobile_banking">Mobile Banking</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label>Amount</label>
                        <input type="text" id="payment-amount" readonly style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #f5f5f5;">
                    </div>
                    <button class="btn btn-primary" onclick="processPayment()" style="width: 100%; padding: 15px;">Pay Now</button>
                </div>
            </div>
        </div>

        <!-- History Section -->
        <div id="history-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-history"></i> Booking & Payment History</h3>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="history-search" placeholder="Search..." style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                    <select id="history-filter" style="padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="table-content" style="padding: 20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody">
                        <tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">Loading history...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Messages Section -->
        <div id="messages-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-comments"></i> Messages</h3>
            </div>
            <div class="table-content" style="padding: 20px;">
                <div id="messages-container">
                    <p style="color: #666;">Your messages with doctors will appear here.</p>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div id="profile-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-user-edit"></i> My Profile</h3>
            </div>
            <div class="table-content" style="padding: 20px;">
                <div id="profile-form" style="max-width: 800px; margin: 0 auto;">
                    <form onsubmit="updateProfile(event)">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label>Full Name *</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($currentUser['name']); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            <div>
                                <label>Email *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            <div>
                                <label>Phone *</label>
                                <input type="tel" name="phone" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            <div>
                                <label>Date of Birth</label>
                                <input type="date" name="date_of_birth" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                            </div>
                            <div>
                                <label>Gender</label>
                                <select name="gender" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label>Nationality</label>
                                <select name="nationality" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    <option value="local">Local</option>
                                    <option value="international">International</option>
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label>Address</label>
                            <textarea name="address" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 12px 30px;"><i class="fas fa-save"></i> Update Profile</button>
                    </form>
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

        function filterDoctors(specialty) {
            const grid = document.getElementById('doctors-grid');
            grid.innerHTML = 'Loading doctors...';
            // Filter doctors by specialty
            fetch('api/appointments.php?action=list-doctors&specialty=' + specialty)
                .then(response => response.json())
                .then(result => {
                    // Update grid with filtered doctors
                    console.log('Filtered doctors:', result);
                });
        }

        function showBookingForm() {
            document.getElementById('booking-modal').style.display = 'block';
        }

        function closeBookingModal() {
            document.getElementById('booking-modal').style.display = 'none';
        }

        function submitBooking(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            fetch('api/appointments.php?action=create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Appointment booked successfully!');
                    form.reset();
                    closeBookingModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                alert('Error booking appointment: ' + error);
            });
        }

        // Update consultation fee when doctor is selected
        document.addEventListener('DOMContentLoaded', function() {
            const doctorSelect = document.getElementById('booking-doctor');
            const feeInput = document.getElementById('booking-fee');
            
            if (doctorSelect && feeInput) {
                doctorSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const fee = selectedOption.getAttribute('data-fee');
                    feeInput.value = fee ? '$' + parseFloat(fee).toFixed(2) : '';
                });
            }
        });

        function bookWithDoctor(doctorId) {
            showBookingForm();
            // Set the selected doctor
            document.getElementById('booking-doctor').value = doctorId;
            // Trigger change to update fee
            document.getElementById('booking-doctor').dispatchEvent(new Event('change'));
        }

        function processPayment() {
            const appointmentId = document.getElementById('appointment-select').value;
            const paymentMethod = document.getElementById('payment-method').value;
            
            if (!appointmentId || !paymentMethod) {
                alert('Please select appointment and payment method');
                return;
            }
            
            fetch('api/appointments.php?action=create-payment', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    appointment_id: appointmentId,
                    payment_method: paymentMethod
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Payment processed successfully!');
                } else {
                    alert('Error: ' + result.message);
                }
            });
        }

        function updateProfile(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            
            fetch('api/auth.php?action=update-profile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Profile updated successfully!');
                } else {
                    alert('Error: ' + result.message);
                }
            });
        }
    </script>
</body>
</html>
