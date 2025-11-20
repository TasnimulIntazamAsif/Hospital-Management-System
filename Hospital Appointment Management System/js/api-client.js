// js/api-client.js - API client for PHP backend
class APIClient {
    constructor() {
        this.baseURL = '/api';
        this.token = localStorage.getItem('authToken');
    }

    setToken(token) {
        this.token = token;
        localStorage.setItem('authToken', token);
    }

    clearToken() {
        this.token = null;
        localStorage.removeItem('authToken');
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}/${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        };

        if (this.token) {
            config.headers['Authorization'] = `Bearer ${this.token}`;
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }

            return data;
        } catch (error) {
            console.error('API Request Error:', error);
            throw error;
        }
    }

    // Authentication methods
    async login(email, password) {
        const response = await this.request('auth/login', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });

        if (response.success && response.data.token) {
            this.setToken(response.data.token);
        }

        return response;
    }

    async register(userData) {
        const response = await this.request('auth/register', {
            method: 'POST',
            body: JSON.stringify(userData)
        });

        if (response.success && response.data.token) {
            this.setToken(response.data.token);
        }

        return response;
    }

    async logout() {
        try {
            await this.request('auth/logout', { method: 'POST' });
        } finally {
            this.clearToken();
        }
    }

    async getProfile() {
        return await this.request('auth/profile');
    }

    async updateProfile(profileData) {
        return await this.request('auth/update-profile', {
            method: 'PUT',
            body: JSON.stringify(profileData)
        });
    }

    async updateProfileWithPhoto(profileData, photoFile = null) {
        const formData = new FormData();
        
        // Add text fields
        if (profileData.name) formData.append('name', profileData.name);
        if (profileData.phone) formData.append('phone', profileData.phone);
        if (profileData.specialty) formData.append('specialty', profileData.specialty);
        if (profileData.bio) formData.append('bio', profileData.bio);
        if (profileData.consultation_fee) formData.append('consultation_fee', profileData.consultation_fee);
        if (profileData.address) formData.append('address', profileData.address);
        if (profileData.emergency_contact) formData.append('emergency_contact', profileData.emergency_contact);
        if (profileData.medical_history) formData.append('medical_history', profileData.medical_history);
        
        // Add photo file if provided
        if (photoFile) {
            formData.append('photo', photoFile);
        }
        
        const response = await fetch(`${this.baseURL}/auth/update-profile`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.token}`
            },
            body: formData
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Update failed');
        }

        return data;
    }

    // Prescription methods
    async createPrescription(prescriptionData) {
        return await this.request('prescriptions/create', {
            method: 'POST',
            body: JSON.stringify(prescriptionData)
        });
    }

    async getPrescriptions(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await this.request(`prescriptions/list?${queryString}`);
    }

    async getPrescription(id) {
        return await this.request(`prescriptions/get?id=${id}`);
    }

    async updatePrescription(id, prescriptionData) {
        return await this.request(`prescriptions/update?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(prescriptionData)
        });
    }

    async deletePrescription(id) {
        return await this.request(`prescriptions/delete?id=${id}`, {
            method: 'DELETE'
        });
    }

    async printPrescription(id) {
        return await this.request(`prescriptions/print?id=${id}`);
    }

    async getPrescriptionTemplates() {
        return await this.request('prescriptions/templates');
    }

    async savePrescriptionTemplate(templateData) {
        return await this.request('prescriptions/save-template', {
            method: 'POST',
            body: JSON.stringify(templateData)
        });
    }

    async usePrescriptionTemplate(templateId) {
        return await this.request(`prescriptions/use-template?template_id=${templateId}`, {
            method: 'POST'
        });
    }

    // Medicine methods
    async getMedicines(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await this.request(`medicines/list?${queryString}`);
    }

    async getMedicine(id) {
        return await this.request(`medicines/get?id=${id}`);
    }

    async createMedicine(medicineData) {
        return await this.request('medicines/create', {
            method: 'POST',
            body: JSON.stringify(medicineData)
        });
    }

    async updateMedicine(id, medicineData) {
        return await this.request(`medicines/update?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(medicineData)
        });
    }

    async deleteMedicine(id) {
        return await this.request(`medicines/delete?id=${id}`, {
            method: 'DELETE'
        });
    }

    async getMedicineCategories() {
        return await this.request('medicines/categories');
    }

    async getLowStockMedicines(threshold = 50) {
        return await this.request(`medicines/stock-low?threshold=${threshold}`);
    }

    async searchMedicines(query, limit = 10) {
        return await this.request(`medicines/search?q=${encodeURIComponent(query)}&limit=${limit}`);
    }

    // Test methods
    async getTests(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await this.request(`tests/list?${queryString}`);
    }

    async getTest(id) {
        return await this.request(`tests/get?id=${id}`);
    }

    async createTest(testData) {
        return await this.request('tests/create', {
            method: 'POST',
            body: JSON.stringify(testData)
        });
    }

    async updateTest(id, testData) {
        return await this.request(`tests/update?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(testData)
        });
    }

    async deleteTest(id) {
        return await this.request(`tests/delete?id=${id}`, {
            method: 'DELETE'
        });
    }

    async getTestCategories() {
        return await this.request('tests/categories');
    }

    async searchTests(query, limit = 10) {
        return await this.request(`tests/search?q=${encodeURIComponent(query)}&limit=${limit}`);
    }

    // Appointment methods
    async getAppointments(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await this.request(`appointments/list?${queryString}`);
    }

    async getAppointment(id) {
        return await this.request(`appointments/get?id=${id}`);
    }

    async createAppointment(appointmentData) {
        return await this.request('appointments/create', {
            method: 'POST',
            body: JSON.stringify(appointmentData)
        });
    }

    async updateAppointment(id, appointmentData) {
        return await this.request(`appointments/update?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(appointmentData)
        });
    }

    async cancelAppointment(id) {
        return await this.request(`appointments/cancel?id=${id}`, {
            method: 'POST'
        });
    }

    async completeAppointment(id) {
        return await this.request(`appointments/complete?id=${id}`, {
            method: 'POST'
        });
    }

    async getDoctorSchedule() {
        return await this.request('appointments/schedule');
    }

    async updateDoctorSchedule(scheduleData) {
        return await this.request('appointments/schedule', {
            method: 'POST',
            body: JSON.stringify(scheduleData)
        });
    }

    async getAvailableSlots(doctorId, date) {
        return await this.request(`appointments/available-slots?doctor_id=${doctorId}&date=${date}`);
    }

    // Payment methods
    async getPayments(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await this.request(`appointments/payments?${queryString}`);
    }

    async createPayment(paymentData) {
        return await this.request('appointments/create-payment', {
            method: 'POST',
            body: JSON.stringify(paymentData)
        });
    }

    async verifyPayment(id) {
        return await this.request(`appointments/verify-payment?id=${id}&action=verify`, {
            method: 'POST'
        });
    }

    async rejectPayment(id) {
        return await this.request(`appointments/verify-payment?id=${id}&action=reject`, {
            method: 'POST'
        });
    }

    // Admin methods
    async getPendingDoctors() {
        return await this.request('admin/pending-doctors');
    }

    async approveDoctor(id) {
        return await this.request(`admin/approve-doctor?id=${id}`, {
            method: 'POST'
        });
    }

    async rejectDoctor(id, reason) {
        return await this.request(`admin/reject-doctor?id=${id}`, {
            method: 'POST',
            body: JSON.stringify({ reason })
        });
    }

    async getUsers(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await this.request(`admin/users?${queryString}`);
    }

    async updateUserStatus(id, status) {
        return await this.request(`admin/update-user-status?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify({ status })
        });
    }

    async getCertificates(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await this.request(`admin/certificates?${queryString}`);
    }

    async verifyCertificate(id) {
        return await this.request(`admin/verify-certificate?id=${id}`, {
            method: 'POST'
        });
    }

    async rejectCertificate(id, reason) {
        return await this.request(`admin/reject-certificate?id=${id}`, {
            method: 'POST',
            body: JSON.stringify({ reason })
        });
    }

    async getDashboardStats() {
        return await this.request('admin/dashboard-stats');
    }

    async getAuditLogs(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await this.request(`admin/audit-logs?${queryString}`);
    }

    async getActivityLogs(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        return await this.request(`admin/activity-logs?${queryString}`);
    }

    // File upload methods
    async uploadFile(file, type = 'general') {
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch(`${this.baseURL}/upload?type=${type}`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${this.token}`
            },
            body: formData
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Upload failed');
        }

        return data;
    }

    async downloadFile(filePath) {
        const response = await fetch(`${this.baseURL}/download?file=${encodeURIComponent(filePath)}`, {
            headers: {
                'Authorization': `Bearer ${this.token}`
            }
        });

        if (!response.ok) {
            throw new Error('Download failed');
        }

        return response.blob();
    }

    // Utility methods
    async healthCheck() {
        return await this.request('health');
    }

    isAuthenticated() {
        return !!this.token;
    }

    getCurrentUser() {
        try {
            const token = this.token;
            if (!token) return null;

            // Decode JWT token (simple base64 decode for demo)
            const payload = JSON.parse(atob(token.split('.')[1]));
            return payload;
        } catch (error) {
            console.error('Error decoding token:', error);
            return null;
        }
    }
}

// Global API client instance
window.apiClient = new APIClient();

// Utility functions for backward compatibility
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        z-index: 10000;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    // Set background color based on type
    const colors = {
        success: '#059669',
        error: '#dc2626',
        warning: '#d97706',
        info: '#2563eb'
    };
    notification.style.backgroundColor = colors[type] || colors.info;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

// Initialize API client on page load
document.addEventListener('DOMContentLoaded', () => {
    // Check if user is authenticated
    if (apiClient.isAuthenticated()) {
        console.log('User is authenticated');
    } else {
        console.log('User is not authenticated');
    }
});
