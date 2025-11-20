<?php
require_once 'includes/auth-check.php';
requireRole(['doctor']);
require_once 'config/database.php';

$currentUser = getCurrentUser();

// Get doctor data
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get doctor info
    $stmt = $conn->prepare("
        SELECT d.*, u.name, u.email, u.phone
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE d.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $doctorInfo = $stmt->fetch();
    
    // Get doctor id
    $doctorId = null;
    if ($doctorInfo && isset($doctorInfo['id'])) {
        $doctorId = $doctorInfo['id'];
    }
    
    // Get appointments
    $appointments = [];
    if ($doctorId) {
        $stmt = $conn->prepare("
            SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason,
                   u.name as patient_name, u.phone as patient_phone
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE a.doctor_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 20
        ");
        $stmt->execute([$doctorId]);
        $appointments = $stmt->fetchAll();
        
        // Count stats
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND status = 'pending'");
        $stmt->execute([$doctorId]);
        $result = $stmt->fetch();
        $pendingCount = $result['total'];
    } else {
        $pendingCount = 0;
    }
    
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
    <title>Doctor Dashboard - Hospital Management</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            color: #10b981;
            margin-bottom: 15px;
        }
        .dashboard-card h3 {
            margin: 10px 0;
            color: #333;
        }
        .dashboard-card p {
            font-size: 2rem;
            font-weight: 700;
            color: #10b981;
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
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        .status-pending { background: #f59e0b; color: white; }
        .status-confirmed { background: #10b981; color: white; }
        .status-cancelled { background: #dc2626; color: white; }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="dashboard-nav">
            <div class="dashboard-title">
                <i class="fas fa-user-md"></i> Doctor Dashboard
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

        <!-- Doctor Actions Grid -->
        <div class="dashboard-grid">
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('appointments')">
                <i class="fas fa-calendar-check"></i>
                <h3>My Appointments</h3>
                <p>Approve & Manage</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('schedule')">
                <i class="fas fa-clock"></i>
                <h3>My Schedule</h3>
                <p>Set Availability</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('certificates')">
                <i class="fas fa-certificate"></i>
                <h3>Certificates</h3>
                <p>Upload & Manage</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('messages')">
                <i class="fas fa-comments"></i>
                <h3>Messages</h3>
                <p>Chat with Patients</p>
            </div>
            <div class="dashboard-card" style="cursor: pointer;" onclick="showSection('prescriptions')">
                <i class="fas fa-prescription-bottle-alt"></i>
                <h3>Prescriptions</h3>
                <p>Create & Manage</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <i class="fas fa-clipboard-list"></i>
                <h3>Pending Appointments</h3>
                <p><?php echo $pendingCount; ?></p>
                </div>
            <div class="dashboard-card">
                <i class="fas fa-briefcase"></i>
                <h3>Specialty</h3>
                <p style="font-size: 1.2rem;"><?php echo htmlspecialchars($doctorInfo['specialty'] ?? 'N/A'); ?></p>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-certificate"></i>
                <h3>License</h3>
                <p style="font-size: 1rem;"><?php echo htmlspecialchars($doctorInfo['license_number'] ?? 'N/A'); ?></p>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-dollar-sign"></i>
                <h3>Consultation Fee</h3>
                <p>$<?php echo number_format($doctorInfo['consultation_fee'] ?? 0, 2); ?></p>
            </div>
        </div>

        <!-- Appointments -->
        <div class="data-table">
            <div class="table-header">
                <h3><i class="fas fa-calendar-check"></i> My Appointments</h3>
                </div>
            <div class="table-content" style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                                    No appointments scheduled
                        </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $apt): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($apt['appointment_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($apt['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($apt['patient_phone']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $apt['status']; ?>">
                                            <?php echo ucfirst($apt['status']); ?>
                                        </span>
                        </td>
                                    <td>
                                        <a href="#" onclick="approveAppointment(<?php echo $apt['id']; ?>); return false;" class="btn btn-success">Approve</a>
                                        <a href="#" onclick="rejectAppointment(<?php echo $apt['id']; ?>); return false;" class="btn btn-danger">Cancel</a>
                        </td>
                        </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Dynamic Sections -->
        
        <!-- My Schedule Section -->
        <div id="schedule-section" class="data-table" style="display: none;">
            <div class="table-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3><i class="fas fa-clock"></i> My Availability Schedule</h3>
                <button class="btn btn-primary" onclick="showScheduleForm()"><i class="fas fa-plus"></i> Add New Availability</button>
            </div>
            <div class="table-content" style="padding: 20px;">
                <div id="schedule-form" style="display: none; background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <form onsubmit="updateSchedule(event)">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                            <div>
                                <label><strong>Day of Week *</strong></label>
                                <select name="day" id="schedule-day" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    <option value="">Select Day</option>
                                    <option value="monday">Monday</option>
                                    <option value="tuesday">Tuesday</option>
                                    <option value="wednesday">Wednesday</option>
                                    <option value="thursday">Thursday</option>
                                    <option value="friday">Friday</option>
                                    <option value="saturday">Saturday</option>
                                    <option value="sunday">Sunday</option>
                                </select>
                        </div>
                            <div>
                                <label><strong>Start Time *</strong></label>
                                <input type="time" name="start_time" id="schedule-start-time" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                            <div>
                                <label><strong>End Time *</strong></label>
                                <input type="time" name="end_time" id="schedule-end-time" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-success" style="padding: 12px 30px;"><i class="fas fa-save"></i> Save Schedule</button>
                            <button type="button" class="btn" onclick="hideScheduleForm()" style="padding: 12px 30px;">Cancel</button>
                    </div>
                    </form>
                    </div>
                <div id="current-schedule">
                    <h4 style="margin-bottom: 15px;">Current Schedule</h4>
                    <div id="schedule-list">
                        <p style="color: #666; padding: 20px; text-align: center;">Loading your availability schedule...</p>
                    </div>
            </div>
        </div>
    </div>

        <!-- Certificates Section -->
        <div id="certificates-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-certificate"></i> My Qualification Certificates</h3>
                <button class="btn btn-primary" onclick="showCertificateUpload()">Upload Certificate</button>
            </div>
            <div class="table-content" style="padding: 20px;">
                <div id="certificate-upload" style="display: none; margin-bottom: 20px;">
                    <form enctype="multipart/form-data" onsubmit="uploadCertificate(event)">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label>Certificate Name *</label>
                                <input type="text" name="certificate_name" required placeholder="e.g., Medical License" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                            <div>
                                <label>Issuing Authority *</label>
                                <input type="text" name="issuing_authority" required placeholder="e.g., Medical Board" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                            <div>
                                <label>Issue Date *</label>
                                <input type="date" name="issue_date" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                            <div>
                                <label>Expiry Date (Optional)</label>
                                <input type="date" name="expiry_date" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        </div>
                    </div>
                        <div>
                            <label>Upload Certificate File (PDF/JPG) *</label>
                            <input type="file" name="certificate_file" accept=".pdf,.jpg,.jpeg,.png" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                        <button type="submit" class="btn btn-success" style="margin-top: 15px;"><i class="fas fa-upload"></i> Upload Certificate</button>
                        <button type="button" class="btn" onclick="hideCertificateUpload()">Cancel</button>
                </form>
            </div>
                <div id="certificates-list">
                    <p style="color: #666;">Your uploaded certificates will appear here.</p>
            </div>
        </div>
    </div>

        <!-- Messages Section -->
        <div id="messages-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-comments"></i> Messages from Patients</h3>
            </div>
            <div class="table-content" style="padding: 20px;">
                <div id="messages-container">
                    <p style="color: #666;">Your patient messages will appear here.</p>
                        </div>
                        </div>
                    </div>
                    
        <!-- Prescriptions Section -->
        <div id="prescriptions-section" class="data-table" style="display: none;">
            <div class="table-header">
                <h3><i class="fas fa-prescription-bottle-alt"></i> My Prescriptions</h3>
                <button class="btn btn-primary" onclick="showPrescriptionForm()">Create Prescription</button>
                    </div>
            <div class="table-content" style="padding: 20px;">
                <div id="prescription-form" style="display: none; margin-bottom: 20px;">
                    <form onsubmit="createPrescription(event)">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label>Patient *</label>
                                <select name="patient_id" required id="patient-select" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    <option value="">Select Patient</option>
                                    <!-- Patients will be loaded dynamically -->
                                        </select>
                                    </div>
                            <div>
                                <label>Prescription Date *</label>
                                <input type="date" name="prescription_date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    </div>
                            <div>
                                <label>Medication Name *</label>
                                <input type="text" name="medication" required placeholder="e.g., Amoxicillin 500mg" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    </div>
                            <div>
                                <label>Dosage *</label>
                                <input type="text" name="dosage" required placeholder="e.g., 1 tablet twice daily" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    </div>
                            <div>
                                <label>Quantity *</label>
                                <input type="number" name="quantity" required placeholder="e.g., 30" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    </div>
                            <div>
                                <label>Duration *</label>
                                <input type="text" name="duration" required placeholder="e.g., 10 days" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                    </div>
                                </div>
                        <div>
                                        <label>Instructions</label>
                            <textarea name="instructions" rows="3" placeholder="Additional instructions for the patient" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
                                    </div>
                        <div>
                            <label>Notes</label>
                            <textarea name="notes" rows="2" placeholder="Doctor's notes" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
                                    </div>
                        <button type="submit" class="btn btn-success" style="margin-top: 15px;"><i class="fas fa-save"></i> Save Prescription</button>
                        <button type="button" class="btn" onclick="hidePrescriptionForm()">Cancel</button>
                    </form>
                                </div>
                <div id="prescriptions-list">
                    <p style="color: #666;">Your prescriptions will appear here.</p>
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
            
            // Load schedule when section is shown
            if (sectionName === 'schedule') {
                loadCurrentSchedule();
            }
        }

        function showScheduleForm() {
            document.getElementById('schedule-form').style.display = 'block';
            // Reset form
            document.getElementById('schedule-day').value = '';
            document.getElementById('schedule-start-time').value = '';
            document.getElementById('schedule-end-time').value = '';
        }

        function hideScheduleForm() {
            document.getElementById('schedule-form').style.display = 'none';
        }

        function updateSchedule(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            
            // Send to API
            fetch('api/appointments.php?action=update-schedule', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ Schedule updated successfully!');
                    event.target.reset();
                    hideScheduleForm();
                    // Reload schedule display
                    setTimeout(() => {
                        loadCurrentSchedule();
                    }, 500);
                } else {
                    alert('Error: ' + (result.message || 'Failed to update schedule'));
                }
            })
            .catch(error => {
                alert('Error updating schedule: ' + error);
            });
        }
        
        function loadCurrentSchedule() {
            // Load and display current schedule
            const scheduleList = document.getElementById('schedule-list');
            
            fetch('api/appointments.php?action=get-schedule')
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data && result.data.length > 0) {
                        let html = '<table style="width: 100%; border-collapse: collapse;">';
                        html += '<thead><tr><th>Day</th><th>Start Time</th><th>End Time</th><th>Actions</th></tr></thead>';
                        html += '<tbody>';
                        
                        result.data.forEach(schedule => {
                            html += '<tr>';
                            html += '<td style="padding: 10px; border-bottom: 1px solid #ddd;">' + schedule.day_of_week.charAt(0).toUpperCase() + schedule.day_of_week.slice(1) + '</td>';
                            html += '<td style="padding: 10px; border-bottom: 1px solid #ddd;">' + schedule.start_time + '</td>';
                            html += '<td style="padding: 10px; border-bottom: 1px solid #ddd;">' + schedule.end_time + '</td>';
                            html += '<td style="padding: 10px; border-bottom: 1px solid #ddd;"><button class="btn btn-danger" onclick="deleteSchedule(\'' + schedule.day_of_week + '\')">Delete</button></td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table>';
                        scheduleList.innerHTML = html;
                    } else {
                        scheduleList.innerHTML = '<p style="color: #666; padding: 20px; text-align: center;">No schedule added yet. Add your availability above.</p>';
                    }
                })
                .catch(error => {
                    scheduleList.innerHTML = '<p style="color: #666; padding: 20px; text-align: center;">No schedule added yet. Add your availability above.</p>';
                });
        }
        
        function deleteSchedule(day) {
            if (confirm('Delete this schedule entry?')) {
                fetch('api/appointments.php?action=delete-schedule&day=' + day, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Schedule deleted successfully!');
                        loadCurrentSchedule();
                    } else {
                        alert('Error: ' + result.message);
                    }
                });
            }
        }

        function showCertificateUpload() {
            document.getElementById('certificate-upload').style.display = 'block';
        }

        function hideCertificateUpload() {
            document.getElementById('certificate-upload').style.display = 'none';
        }

        function uploadCertificate(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            fetch('api/admin.php?action=upload-certificate', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Certificate uploaded successfully!');
                    event.target.reset();
                    hideCertificateUpload();
                    // Reload certificates list
            loadCertificates();
                } else {
                    alert('Error: ' + (result.message || 'Failed to upload certificate'));
                }
            })
            .catch(error => {
                alert('Error uploading certificate: ' + error);
            });
        }

        function loadCertificates() {
            // Load and display uploaded certificates
            document.getElementById('certificates-list').innerHTML = 
                '<p style="color: #666;">Your uploaded certificates will appear here.</p>';
        }

        function showPrescriptionForm() {
            document.getElementById('prescription-form').style.display = 'block';
            // Load patients list
            loadPatients();
        }

        function hidePrescriptionForm() {
            document.getElementById('prescription-form').style.display = 'none';
        }

        function createPrescription(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const data = Object.fromEntries(formData);
            
            fetch('api/prescriptions.php?action=create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Prescription created successfully!');
                    event.target.reset();
                    hidePrescriptionForm();
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                alert('Error creating prescription: ' + error);
            });
        }

        function loadPatients() {
            // Load patients from appointments or patients list
            fetch('api/appointments.php?action=list-patients')
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        const patientSelect = document.getElementById('patient-select');
                        patientSelect.innerHTML = '<option value="">Select Patient</option>';
                        result.data.forEach(patient => {
                            const option = document.createElement('option');
                            option.value = patient.id;
                            option.textContent = patient.name;
                            patientSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Error loading patients:', error));
        }

        // Approve/Reject appointment
        function approveAppointment(appointmentId) {
            if (confirm('Approve this appointment?')) {
                fetch('api/appointments.php?action=approve&id=' + appointmentId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('✅ Appointment approved successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }

        function rejectAppointment(appointmentId) {
            if (confirm('Cancel this appointment?')) {
                fetch('api/appointments.php?action=cancel&id=' + appointmentId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('✅ Appointment cancelled successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error);
                });
            }
        }
    </script>
</body>
</html>
