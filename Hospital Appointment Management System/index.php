<?php
session_start();
require_once 'config/database.php';

// Get messages from session
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error']);
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Appointment Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .alert {
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .form-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .form-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        .close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <?php if ($error): ?>
        <div class="alert alert-error">
            <span><?php echo htmlspecialchars($error); ?></span>
            <span onclick="this.parentElement.style.display='none'">&times;</span>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <span><?php echo htmlspecialchars($success); ?></span>
            <span onclick="this.parentElement.style.display='none'">&times;</span>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <i class="fas fa-hospital"></i>
                <span>Hospital Management</span>
            </div>
            <div class="nav-menu" id="nav-menu">
                <a href="#home" class="nav-link">Home</a>
                <a href="#about" class="nav-link">About</a>
                <a href="#services" class="nav-link">Services</a>
                <a href="#contact" class="nav-link">Contact</a>
                <div class="nav-auth">
                    <button class="btn-login" onclick="openModal('loginModal')">Login</button>
                    <button class="btn-register" onclick="openModal('registerModal')">Register</button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <h1>Hospital Appointment Management System</h1>
                <p>Streamline your healthcare experience with our comprehensive appointment management platform. Book appointments, manage schedules, and communicate seamlessly.</p>
                <div class="hero-buttons">
                    <button class="btn-primary" onclick="openModal('loginModal')">Get Started</button>
                    <button class="btn-secondary">Learn More</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Modal -->
    <div id="loginModal" class="form-modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('loginModal')">&times;</span>
            <h2>Login</h2>
            <form action="auth-process.php?action=login" method="POST">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="doctor">Doctor</option>
                        <option value="patient">Patient</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Login</button>
            </form>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="registerModal" class="form-modal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <span class="close" onclick="closeModal('registerModal')">&times;</span>
            <h2>Register</h2>
            <form action="auth-process.php?action=register" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="regName">Full Name</label>
                    <input type="text" id="regName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="regEmail">Email</label>
                    <input type="email" id="regEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="regPhone">Phone</label>
                    <input type="tel" id="regPhone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="regPassword">Password</label>
                    <input type="password" id="regPassword" name="password" required>
                </div>
                <div class="form-group">
                    <label for="regRole">Role</label>
                    <select id="regRole" name="role" required onchange="toggleRoleFields()">
                        <option value="">Select Role</option>
                        <option value="doctor">Doctor</option>
                        <option value="patient">Patient</option>
                    </select>
                </div>

                <!-- Doctor Fields -->
                <div id="doctorFields" style="display: none;">
                    <div class="form-group">
                        <label for="regSpecialty">Medical Specialty</label>
                        <select id="regSpecialty" name="specialty">
                            <option value="">Select Specialty</option>
                            <option value="cardiology">Cardiology</option>
                            <option value="dermatology">Dermatology</option>
                            <option value="neurology">Neurology</option>
                            <option value="orthopedics">Orthopedics</option>
                            <option value="pediatrics">Pediatrics</option>
                            <option value="general">General Medicine</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="regLicense">Medical License Number</label>
                        <input type="text" id="regLicense" name="license">
                    </div>
                    <div class="form-group">
                        <label for="regExperience">Years of Experience</label>
                        <input type="number" id="regExperience" name="experience" min="0">
                    </div>
                    <div class="form-group">
                        <label for="regConsultationFee">Consultation Fee ($)</label>
                        <input type="number" id="regConsultationFee" name="consultation_fee" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="regBio">Professional Bio</label>
                        <textarea id="regBio" name="bio" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="regPhoto">Profile Photo</label>
                        <input type="file" id="regPhoto" name="photo" accept="image/*">
                    </div>
                </div>

                <!-- Patient Fields -->
                <div id="patientFields" style="display: none;">
                    <div class="form-group">
                        <label for="regDateOfBirth">Date of Birth</label>
                        <input type="date" id="regDateOfBirth" name="date_of_birth">
                    </div>
                    <div class="form-group">
                        <label for="regGender">Gender</label>
                        <select id="regGender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="regAddress">Address</label>
                        <textarea id="regAddress" name="address" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="regEmergencyContact">Emergency Contact</label>
                        <input type="tel" id="regEmergencyContact" name="emergency_contact">
                    </div>
                    <div class="form-group">
                        <label for="regNationality">Nationality</label>
                        <select id="regNationality" name="nationality">
                            <option value="local">Local</option>
                            <option value="international">International</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="regPhoto">Profile Photo (Optional)</label>
                        <input type="file" id="regPhoto" name="photo" accept="image/*">
                    </div>
                </div>

                <button type="submit" class="btn-primary">Register</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function toggleRoleFields() {
            const role = document.getElementById('regRole').value;
            const doctorFields = document.getElementById('doctorFields');
            const patientFields = document.getElementById('patientFields');
            
            doctorFields.style.display = role === 'doctor' ? 'block' : 'none';
            patientFields.style.display = role === 'patient' ? 'block' : 'none';
        }

        window.onclick = function(event) {
            const modals = document.getElementsByClassName('form-modal');
            for (let modal of modals) {
                if (event.target == modal) {
                    modal.classList.remove('active');
                }
            }
        }
    </script>
</body>
</html>

