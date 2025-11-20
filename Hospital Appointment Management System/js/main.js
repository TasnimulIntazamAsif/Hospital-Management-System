// Main JavaScript functionality
class HospitalManagementSystem {
    constructor() {
        this.currentUser = null;
        this.currentRole = null;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadUserData();
        this.setupMobileMenu();
    }

    setupEventListeners() {
        // Login form
        document.getElementById('loginForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleLogin();
        });

        // Register form
        document.getElementById('registerForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleRegister();
        });

        // Contact form
        document.getElementById('contactForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleContactForm();
        });

        // Modal close events
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    }

    setupMobileMenu() {
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('nav-menu');

        hamburger?.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Close mobile menu when clicking on a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                hamburger?.classList.remove('active');
                navMenu?.classList.remove('active');
            });
        });
    }

    handleLogin() {
        const email = document.getElementById('loginEmail').value;
        const password = document.getElementById('loginPassword').value;
        const role = document.getElementById('loginRole').value;

        // Simulate authentication
        const user = this.authenticateUser(email, password, role);
        
        if (user) {
            this.currentUser = user;
            this.currentRole = role;
            localStorage.setItem('currentUser', JSON.stringify(user));
            localStorage.setItem('currentRole', role);
            
            this.closeModal('loginModal');
            this.redirectToDashboard(role);
        } else {
            this.showNotification('Invalid credentials. Please try again.', 'error');
        }
    }

    handleRegister() {
        const name = document.getElementById('regName').value;
        const email = document.getElementById('regEmail').value;
        const phone = document.getElementById('regPhone').value;
        const password = document.getElementById('regPassword').value;
        const role = document.getElementById('regRole').value;

        // Validate required fields
        if (!name || !email || !phone || !password || !role) {
            this.showNotification('Please fill in all required fields.', 'error');
            return;
        }

        // Additional validation for doctor registration
        if (role === 'doctor') {
            const specialty = document.getElementById('regSpecialty').value;
            const license = document.getElementById('regLicense').value;
            const photo = document.getElementById('regPhoto').files[0];
            const certificates = document.getElementById('regCertificates').files;

            if (!specialty || !license) {
                this.showNotification('Please fill in medical specialty and license number.', 'error');
                return;
            }

            if (!photo) {
                this.showNotification('Please upload a profile photo.', 'error');
                return;
            }

            if (certificates.length === 0) {
                this.showNotification('Please upload at least one medical certificate.', 'error');
                return;
            }
        }

        // Additional validation for patient registration
        if (role === 'patient') {
            const nationality = document.getElementById('regNationality').value;
            
            if (nationality === 'international') {
                const passport = document.getElementById('regPassport').files[0];
                const passportNumber = document.getElementById('regPassportNumber').value;
                const passportExpiry = document.getElementById('regPassportExpiry').value;

                if (!passport) {
                    this.showNotification('Please upload passport document for international patients.', 'error');
                    return;
                }

                if (!passportNumber) {
                    this.showNotification('Please enter passport number.', 'error');
                    return;
                }

                if (!passportExpiry) {
                    this.showNotification('Please enter passport expiry date.', 'error');
                    return;
                }
            }
        }

        // Simulate registration
        const user = {
            id: Date.now(),
            name,
            email,
            phone,
            role,
            createdAt: new Date().toISOString()
        };

        // Add role-specific data
        if (role === 'doctor') {
            user.specialty = document.getElementById('regSpecialty').value;
            user.licenseNumber = document.getElementById('regLicense').value;
            user.experience = document.getElementById('regExperience').value || 0;
            user.consultationFee = document.getElementById('regConsultationFee').value || 0;
            user.bio = document.getElementById('regBio').value;
            user.photo = this.handlePhotoUpload();
            user.certificates = this.handleCertificateUpload();
            user.status = 'pending'; // Pending admin approval
        } else if (role === 'patient') {
            user.dateOfBirth = document.getElementById('regDateOfBirth').value;
            user.gender = document.getElementById('regGender').value;
            user.address = document.getElementById('regAddress').value;
            user.emergencyContact = document.getElementById('regEmergencyContact').value;
            user.nationality = document.getElementById('regNationality').value;
            user.photo = this.handlePhotoUpload(); // Optional for patients
            user.status = 'active'; // Patients are active immediately
            
            // Add passport information for international patients
            if (user.nationality === 'international') {
                user.passport = this.handlePassportUpload();
                user.passportNumber = document.getElementById('regPassportNumber').value;
                user.passportExpiry = document.getElementById('regPassportExpiry').value;
            }
        }

        // Store user data
        const users = JSON.parse(localStorage.getItem('users') || '[]');
        users.push(user);
        localStorage.setItem('users', JSON.stringify(users));

        // Store role-specific data separately
        if (role === 'doctor') {
            const doctors = JSON.parse(localStorage.getItem('doctors') || '[]');
            doctors.push({
                id: user.id,
                userId: user.id,
                specialty: user.specialty,
                licenseNumber: user.licenseNumber,
                experienceYears: user.experience,
                consultationFee: user.consultationFee,
                bio: user.bio,
                photo: user.photo,
                certificates: user.certificates,
                status: 'pending'
            });
            localStorage.setItem('doctors', JSON.stringify(doctors));
        } else if (role === 'patient') {
            const patients = JSON.parse(localStorage.getItem('patients') || '[]');
            const patientData = {
                id: user.id,
                userId: user.id,
                dateOfBirth: user.dateOfBirth,
                gender: user.gender,
                address: user.address,
                emergencyContact: user.emergencyContact,
                nationality: user.nationality,
                photo: user.photo,
                medicalHistory: '',
                medicalDocuments: []
            };
            
            // Add passport information for international patients
            if (user.nationality === 'international') {
                patientData.passport = user.passport;
                patientData.passportNumber = user.passportNumber;
                patientData.passportExpiry = user.passportExpiry;
            }
            
            patients.push(patientData);
            localStorage.setItem('patients', JSON.stringify(patients));
        }

        this.showNotification('Registration successful! Please login.', 'success');
        this.closeModal('registerModal');
        setTimeout(() => this.showLoginModal(), 1000);
    }

    handlePhotoUpload() {
        const photoFile = document.getElementById('regPhoto').files[0];
        if (photoFile) {
            // In a real application, you would upload to server
            // For demo purposes, we'll store the file name
            return {
                fileName: photoFile.name,
                fileSize: photoFile.size,
                fileType: photoFile.type,
                uploadedAt: new Date().toISOString()
            };
        }
        return null;
    }

    handleCertificateUpload() {
        const certificateFiles = document.getElementById('regCertificates').files;
        const certificates = [];
        
        for (let i = 0; i < certificateFiles.length; i++) {
            const file = certificateFiles[i];
            certificates.push({
                fileName: file.name,
                fileSize: file.size,
                fileType: file.type,
                uploadedAt: new Date().toISOString(),
                status: 'pending'
            });
        }
        
        return certificates;
    }

    handlePassportUpload() {
        const passportFile = document.getElementById('regPassport').files[0];
        if (passportFile) {
            // In a real application, you would upload to server
            // For demo purposes, we'll store the file name
            return {
                fileName: passportFile.name,
                fileSize: passportFile.size,
                fileType: passportFile.type,
                uploadedAt: new Date().toISOString()
            };
        }
        return null;
    }

    handleContactForm() {
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const message = document.getElementById('message').value;

        // Store contact message
        const messages = JSON.parse(localStorage.getItem('contactMessages') || '[]');
        messages.push({
            name,
            email,
            message,
            timestamp: new Date().toISOString()
        });
        localStorage.setItem('contactMessages', JSON.stringify(messages));

        this.showNotification('Message sent successfully!', 'success');
        document.getElementById('contactForm').reset();
    }

    authenticateUser(email, password, role) {
        // Simulate authentication with demo users
        const demoUsers = {
            admin: { id: 1, name: 'Admin User', email: 'admin@hospital.com', role: 'admin' },
            doctor: { id: 2, name: 'Dr. Smith', email: 'doctor@hospital.com', role: 'doctor' },
            patient: { id: 3, name: 'John Doe', email: 'patient@hospital.com', role: 'patient' },
            manager: { id: 4, name: 'Manager User', email: 'manager@hospital.com', role: 'manager' }
        };

        // For demo purposes, accept any password
        return demoUsers[role] || null;
    }

    redirectToDashboard(role) {
        window.location.href = `dashboard-${role}.html`;
    }

    loadUserData() {
        const user = localStorage.getItem('currentUser');
        const role = localStorage.getItem('currentRole');
        
        if (user && role) {
            this.currentUser = JSON.parse(user);
            this.currentRole = role;
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }

    closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
}

// Dashboard functionality
class Dashboard {
    constructor(role) {
        this.role = role;
        this.init();
    }

    init() {
        this.loadDashboardData();
        this.setupEventListeners();
        this.updateUserInfo();
    }

    loadDashboardData() {
        // Load role-specific data
        switch (this.role) {
            case 'admin':
                this.loadAdminData();
                break;
            case 'doctor':
                this.loadDoctorData();
                break;
            case 'patient':
                this.loadPatientData();
                break;
            case 'manager':
                this.loadManagerData();
                break;
        }
    }

    loadAdminData() {
        // Load admin-specific data
        this.loadUsers();
        this.loadAppointments();
        this.loadAuditLogs();
    }

    loadDoctorData() {
        // Load doctor-specific data
        this.loadDoctorAppointments();
        this.loadDoctorSchedule();
    }

    loadPatientData() {
        // Load patient-specific data
        this.loadPatientAppointments();
        this.loadDoctors();
    }

    loadManagerData() {
        // Load manager-specific data
        this.loadPayments();
        this.loadAppointmentLifecycle();
    }

    loadUsers() {
        const users = JSON.parse(localStorage.getItem('users') || '[]');
        const usersTable = document.getElementById('usersTable');
        
        if (usersTable) {
            const tbody = usersTable.querySelector('tbody');
            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td>${user.phone}</td>
                    <td><span class="status-badge status-${user.role}">${user.role}</span></td>
                    <td>
                        <button class="btn-warning" onclick="editUser(${user.id})">Edit</button>
                        <button class="btn-danger" onclick="deleteUser(${user.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }
    }

    loadAppointments() {
        const appointments = JSON.parse(localStorage.getItem('appointments') || '[]');
        const appointmentsTable = document.getElementById('appointmentsTable');
        
        if (appointmentsTable) {
            const tbody = appointmentsTable.querySelector('tbody');
            tbody.innerHTML = appointments.map(appointment => `
                <tr>
                    <td>${appointment.patientName}</td>
                    <td>${appointment.doctorName}</td>
                    <td>${appointment.date}</td>
                    <td>${appointment.time}</td>
                    <td><span class="status-badge status-${appointment.status}">${appointment.status}</span></td>
                    <td>
                        <button class="btn-danger" onclick="cancelAppointment(${appointment.id})">Cancel</button>
                    </td>
                </tr>
            `).join('');
        }
    }

    loadAuditLogs() {
        const logs = JSON.parse(localStorage.getItem('auditLogs') || '[]');
        const logsTable = document.getElementById('auditLogsTable');
        
        if (logsTable) {
            const tbody = logsTable.querySelector('tbody');
            tbody.innerHTML = logs.map(log => `
                <tr>
                    <td>${log.timestamp}</td>
                    <td>${log.user}</td>
                    <td>${log.action}</td>
                    <td>${log.details}</td>
                </tr>
            `).join('');
        }
    }

    loadDoctorAppointments() {
        const appointments = JSON.parse(localStorage.getItem('appointments') || '[]');
        const doctorAppointments = appointments.filter(apt => apt.doctorId === this.getCurrentUserId());
        
        const appointmentsTable = document.getElementById('doctorAppointmentsTable');
        if (appointmentsTable) {
            const tbody = appointmentsTable.querySelector('tbody');
            tbody.innerHTML = doctorAppointments.map(appointment => `
                <tr>
                    <td>${appointment.patientName}</td>
                    <td>${appointment.date}</td>
                    <td>${appointment.time}</td>
                    <td><span class="status-badge status-${appointment.status}">${appointment.status}</span></td>
                    <td>
                        <button class="btn-success" onclick="approveAppointment(${appointment.id})">Approve</button>
                        <button class="btn-danger" onclick="rejectAppointment(${appointment.id})">Reject</button>
                    </td>
                </tr>
            `).join('');
        }
    }

    loadPatientAppointments() {
        const appointments = JSON.parse(localStorage.getItem('appointments') || '[]');
        const patientAppointments = appointments.filter(apt => apt.patientId === this.getCurrentUserId());
        
        const appointmentsTable = document.getElementById('patientAppointmentsTable');
        if (appointmentsTable) {
            const tbody = appointmentsTable.querySelector('tbody');
            tbody.innerHTML = patientAppointments.map(appointment => `
                <tr>
                    <td>${appointment.doctorName}</td>
                    <td>${appointment.date}</td>
                    <td>${appointment.time}</td>
                    <td><span class="status-badge status-${appointment.status}">${appointment.status}</span></td>
                    <td>
                        <button class="btn-warning" onclick="rescheduleAppointment(${appointment.id})">Reschedule</button>
                        <button class="btn-danger" onclick="cancelAppointment(${appointment.id})">Cancel</button>
                    </td>
                </tr>
            `).join('');
        }
    }

    loadDoctors() {
        const doctors = JSON.parse(localStorage.getItem('doctors') || '[]');
        const doctorsTable = document.getElementById('doctorsTable');
        
        if (doctorsTable) {
            const tbody = doctorsTable.querySelector('tbody');
            tbody.innerHTML = doctors.map(doctor => `
                <tr>
                    <td>${doctor.name}</td>
                    <td>${doctor.specialty}</td>
                    <td>${doctor.availability}</td>
                    <td>
                        <button class="btn-primary" onclick="bookAppointment(${doctor.id})">Book</button>
                    </td>
                </tr>
            `).join('');
        }
    }

    loadPayments() {
        const payments = JSON.parse(localStorage.getItem('payments') || '[]');
        const paymentsTable = document.getElementById('paymentsTable');
        
        if (paymentsTable) {
            const tbody = paymentsTable.querySelector('tbody');
            tbody.innerHTML = payments.map(payment => `
                <tr>
                    <td>${payment.patientName}</td>
                    <td>${payment.amount}</td>
                    <td>${payment.method}</td>
                    <td><span class="status-badge status-${payment.status}">${payment.status}</span></td>
                    <td>
                        <button class="btn-success" onclick="approvePayment(${payment.id})">Approve</button>
                        <button class="btn-danger" onclick="rejectPayment(${payment.id})">Reject</button>
                    </td>
                </tr>
            `).join('');
        }
    }

    setupEventListeners() {
        // Add event listeners for dashboard-specific functionality
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-action]')) {
                const action = e.target.getAttribute('data-action');
                this.handleAction(action, e.target);
            }
        });
    }

    handleAction(action, element) {
        switch (action) {
            case 'add-user':
                this.showAddUserForm();
                break;
            case 'add-appointment':
                this.showAddAppointmentForm();
                break;
            case 'view-reports':
                this.showReports();
                break;
            case 'manage-schedule':
                this.showScheduleManager();
                break;
            case 'upload-certificate':
                this.showCertificateUpload();
                break;
            case 'start-chat':
                this.openChat();
                break;
        }
    }

    updateUserInfo() {
        const userInfo = document.querySelector('.user-info');
        if (userInfo) {
            const currentUser = JSON.parse(localStorage.getItem('currentUser') || '{}');
            const avatar = userInfo.querySelector('.user-avatar');
            const name = userInfo.querySelector('.user-name');
            
            if (avatar) {
                avatar.textContent = currentUser.name ? currentUser.name.charAt(0).toUpperCase() : 'U';
            }
            if (name) {
                name.textContent = currentUser.name || 'User';
            }
        }
    }

    getCurrentUserId() {
        const currentUser = JSON.parse(localStorage.getItem('currentUser') || '{}');
        return currentUser.id;
    }

    showAddUserForm() {
        // Implementation for adding new users
        console.log('Show add user form');
    }

    showAddAppointmentForm() {
        // Implementation for adding new appointments
        console.log('Show add appointment form');
    }

    showReports() {
        // Implementation for showing reports
        console.log('Show reports');
    }

    showScheduleManager() {
        // Implementation for schedule management
        console.log('Show schedule manager');
    }

    showCertificateUpload() {
        // Implementation for certificate upload
        console.log('Show certificate upload');
    }

    openChat() {
        // Implementation for opening chat
        console.log('Open chat');
    }
}

// Global functions for dashboard actions
function editUser(userId) {
    console.log('Edit user:', userId);
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user?')) {
        const users = JSON.parse(localStorage.getItem('users') || '[]');
        const updatedUsers = users.filter(user => user.id !== userId);
        localStorage.setItem('users', JSON.stringify(updatedUsers));
        
        // Reload the table
        if (window.dashboard) {
            window.dashboard.loadUsers();
        }
    }
}

function cancelAppointment(appointmentId) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
        const appointments = JSON.parse(localStorage.getItem('appointments') || '[]');
        const appointment = appointments.find(apt => apt.id === appointmentId);
        if (appointment) {
            appointment.status = 'cancelled';
            localStorage.setItem('appointments', JSON.stringify(appointments));
            
            // Reload the table
            if (window.dashboard) {
                window.dashboard.loadAppointments();
            }
        }
    }
}

function approveAppointment(appointmentId) {
    const appointments = JSON.parse(localStorage.getItem('appointments') || '[]');
    const appointment = appointments.find(apt => apt.id === appointmentId);
    if (appointment) {
        appointment.status = 'confirmed';
        localStorage.setItem('appointments', JSON.stringify(appointments));
        
        // Reload the table
        if (window.dashboard) {
            window.dashboard.loadDoctorAppointments();
        }
    }
}

function rejectAppointment(appointmentId) {
    if (confirm('Are you sure you want to reject this appointment?')) {
        const appointments = JSON.parse(localStorage.getItem('appointments') || '[]');
        const appointment = appointments.find(apt => apt.id === appointmentId);
        if (appointment) {
            appointment.status = 'rejected';
            localStorage.setItem('appointments', JSON.stringify(appointments));
            
            // Reload the table
            if (window.dashboard) {
                window.dashboard.loadDoctorAppointments();
            }
        }
    }
}

function rescheduleAppointment(appointmentId) {
    console.log('Reschedule appointment:', appointmentId);
}

function bookAppointment(doctorId) {
    console.log('Book appointment with doctor:', doctorId);
}

function approvePayment(paymentId) {
    const payments = JSON.parse(localStorage.getItem('payments') || '[]');
    const payment = payments.find(pay => pay.id === paymentId);
    if (payment) {
        payment.status = 'approved';
        localStorage.setItem('payments', JSON.stringify(payments));
        
        // Reload the table
        if (window.dashboard) {
            window.dashboard.loadPayments();
        }
    }
}

function rejectPayment(paymentId) {
    if (confirm('Are you sure you want to reject this payment?')) {
        const payments = JSON.parse(localStorage.getItem('payments') || '[]');
        const payment = payments.find(pay => pay.id === paymentId);
        if (payment) {
            payment.status = 'rejected';
            localStorage.setItem('payments', JSON.stringify(payments));
            
            // Reload the table
            if (window.dashboard) {
                window.dashboard.loadPayments();
            }
        }
    }
}

// Utility functions
function showLoginModal() {
    document.getElementById('loginModal').style.display = 'block';
}

function showRegisterModal() {
    document.getElementById('registerModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function scrollToSection(sectionId) {
    document.getElementById(sectionId).scrollIntoView({ behavior: 'smooth' });
}

function logout() {
    localStorage.removeItem('currentUser');
    localStorage.removeItem('currentRole');
    window.location.href = 'index.html';
}

// Registration utility functions
function toggleRoleFields() {
    const role = document.getElementById('regRole').value;
    const doctorFields = document.getElementById('doctorFields');
    const patientFields = document.getElementById('patientFields');
    
    // Hide all role-specific fields first
    doctorFields.style.display = 'none';
    patientFields.style.display = 'none';
    
    // Remove all required attributes
    document.getElementById('regSpecialty').required = false;
    document.getElementById('regLicense').required = false;
    document.getElementById('regPhoto').required = false;
    document.getElementById('regCertificates').required = false;
    
    if (role === 'doctor') {
        doctorFields.style.display = 'block';
        // Make doctor-specific fields required
        document.getElementById('regSpecialty').required = true;
        document.getElementById('regLicense').required = true;
        document.getElementById('regPhoto').required = true;
        document.getElementById('regCertificates').required = true;
    } else if (role === 'patient') {
        patientFields.style.display = 'block';
        // Patient photo is optional, so no required attributes needed
    }
}

function togglePassportField() {
    const nationality = document.getElementById('regNationality').value;
    const passportField = document.getElementById('passportField');
    
    if (nationality === 'international') {
        passportField.style.display = 'block';
        // Make passport fields required for international patients
        document.getElementById('regPassport').required = true;
        document.getElementById('regPassportNumber').required = true;
        document.getElementById('regPassportExpiry').required = true;
    } else {
        passportField.style.display = 'none';
        // Remove required attributes for local patients
        document.getElementById('regPassport').required = false;
        document.getElementById('regPassportNumber').required = false;
        document.getElementById('regPassportExpiry').required = false;
    }
}

function previewPassport() {
    const file = document.getElementById('regPassport').files[0];
    const preview = document.getElementById('passportPreview');
    
    if (file) {
        const fileIcon = getFileIcon(file.type);
        preview.innerHTML = `
            <div class="passport-preview">
                <div class="document-item" style="display: flex; align-items: center; padding: 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e5e7eb;">
                    <i class="${fileIcon}" style="margin-right: 10px; color: #dc2626; font-size: 1.5rem;"></i>
                    <div>
                        <p style="margin: 0; font-weight: 500; color: #1e293b;">${file.name}</p>
                        <small style="color: #64748b;">${(file.size / 1024).toFixed(1)} KB</small>
                    </div>
                </div>
            </div>
        `;
    } else {
        preview.innerHTML = '';
    }
}

function previewPhoto() {
    const file = document.getElementById('regPhoto').files[0];
    const preview = document.getElementById('photoPreview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `
                <div class="photo-preview">
                    <img src="${e.target.result}" alt="Photo Preview" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #e5e7eb;">
                    <p style="margin-top: 5px; font-size: 0.9rem; color: #64748b;">${file.name}</p>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '';
    }
}

function previewCertificates() {
    const files = document.getElementById('regCertificates').files;
    const preview = document.getElementById('certificatesPreview');
    
    if (files.length > 0) {
        let previewHTML = '<div class="certificates-preview"><h4 style="margin-bottom: 10px; color: #1e293b;">Uploaded Certificates:</h4>';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const fileIcon = getFileIcon(file.type);
            previewHTML += `
                <div class="certificate-item" style="display: flex; align-items: center; padding: 8px; background: #f8fafc; border-radius: 6px; margin-bottom: 8px;">
                    <i class="${fileIcon}" style="margin-right: 10px; color: #2563eb;"></i>
                    <div>
                        <p style="margin: 0; font-weight: 500; color: #1e293b;">${file.name}</p>
                        <small style="color: #64748b;">${(file.size / 1024).toFixed(1)} KB</small>
                    </div>
                </div>
            `;
        }
        
        previewHTML += '</div>';
        preview.innerHTML = previewHTML;
    } else {
        preview.innerHTML = '';
    }
}

function getFileIcon(fileType) {
    if (fileType.includes('pdf')) {
        return 'fas fa-file-pdf';
    } else if (fileType.includes('image')) {
        return 'fas fa-file-image';
    } else {
        return 'fas fa-file';
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
    // Check if we're on a dashboard page
    const currentPage = window.location.pathname;
    if (currentPage.includes('dashboard-')) {
        const role = currentPage.split('dashboard-')[1].split('.')[0];
        window.dashboard = new Dashboard(role);
    } else {
        window.hms = new HospitalManagementSystem();
    }
});

// Add notification styles
const notificationStyles = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 3000;
        transform: translateX(400px);
        transition: transform 0.3s ease;
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-success {
        background: #10b981;
    }
    
    .notification-error {
        background: #dc2626;
    }
    
    .notification-info {
        background: #2563eb;
    }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }
`;

// Add styles to the document
const styleSheet = document.createElement('style');
styleSheet.textContent = notificationStyles;
document.head.appendChild(styleSheet);
