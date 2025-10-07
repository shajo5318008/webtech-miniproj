// API Configuration
const API_BASE_URL = 'http://localhost/ecotransit/backend/api';

// Utility function for making API calls
async function makeAPICall(endpoint, method = 'GET', data = null) {
    const config = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        config.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/${endpoint}`, config);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'API call failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Authentication API calls
const AuthAPI = {
    async register(userData) {
        return await makeAPICall('auth.php?action=register', 'POST', userData);
    },
    
    async login(credentials) {
        const result = await makeAPICall('auth.php?action=login', 'POST', credentials);
        if (result.user) {
            localStorage.setItem('currentUser', JSON.stringify(result.user));
        }
        return result;
    },
    
    logout() {
        localStorage.removeItem('currentUser');
        window.location.href = 'index.html';
    },
    
    getCurrentUser() {
        const user = localStorage.getItem('currentUser');
        return user ? JSON.parse(user) : null;
    }
};

// Trips API calls
const TripsAPI = {
    async logTrip(tripData) {
        return await makeAPICall('trips.php', 'POST', tripData);
    },
    
    async getAnalytics(userId) {
        return await makeAPICall(`trips.php?action=analytics&user_id=${userId}`);
    },
    
    async getTripHistory(userId) {
        return await makeAPICall(`trips.php?action=history&user_id=${userId}`);
    }
};

// Carpool API calls
const CarpoolAPI = {
    async createCarpool(carpoolData) {
        return await makeAPICall('carpool.php?action=create', 'POST', carpoolData);
    },
    
    async searchCarpools(from = '', to = '', date = '') {
        let query = 'carpool.php?action=search';
        const params = [];
        
        if (from) params.push(`from=${encodeURIComponent(from)}`);
        if (to) params.push(`to=${encodeURIComponent(to)}`);
        if (date) params.push(`date=${date}`);
        
        if (params.length > 0) {
            query += '&' + params.join('&');
        }
        
        return await makeAPICall(query);
    },
    
    async getUserCarpools(userId) {
        return await makeAPICall(`carpool.php?action=user_carpools&user_id=${userId}`);
    }
};

// Form handlers
function handleRegisterForm() {
    const form = document.getElementById('registerForm');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        const userData = {
            username: formData.get('username'),
            email: formData.get('email'),
            password: formData.get('password'),
            full_name: formData.get('full_name')
        };
        
        try {
            const result = await AuthAPI.register(userData);
            alert('Registration successful! Please login.');
            window.location.href = 'index.html';
        } catch (error) {
            alert('Registration failed: ' + error.message);
        }
    });
}

function handleLoginForm() {
    const form = document.getElementById('loginForm');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(form);
        const credentials = {
            username: formData.get('username'),
            password: formData.get('password')
        };
        
        try {
            const result = await AuthAPI.login(credentials);
            alert('Login successful!');
            window.location.href = 'dashboard.html';
        } catch (error) {
            alert('Login failed: ' + error.message);
        }
    });
}

function handleTripForm() {
    const form = document.getElementById('tripForm');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const user = AuthAPI.getCurrentUser();
        if (!user) {
            alert('Please login first');
            return;
        }
        
        const formData = new FormData(form);
        const tripData = {
            user_id: user.id,
            transport_type: formData.get('transport_type'),
            distance: parseFloat(formData.get('distance')),
            trip_date: formData.get('trip_date') || new Date().toISOString().split('T')[0]
        };
        
        try {
            const result = await TripsAPI.logTrip(tripData);
            alert(`Trip logged! CO2 saved: ${result.co2_saved}kg, Eco points earned: ${result.eco_points}`);
            form.reset();
            loadDashboardData(); // Refresh dashboard data
        } catch (error) {
            alert('Failed to log trip: ' + error.message);
        }
    });
}

function handleCarpoolForm() {
    const form = document.getElementById('carpoolForm');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const user = AuthAPI.getCurrentUser();
        if (!user) {
            alert('Please login first');
            return;
        }
        
        const formData = new FormData(form);
        const carpoolData = {
            driver_id: user.id,
            from_location: formData.get('from_location'),
            to_location: formData.get('to_location'),
            departure_date: formData.get('departure_date'),
            departure_time: formData.get('departure_time'),
            available_seats: parseInt(formData.get('available_seats'))
        };
        
        try {
            const result = await CarpoolAPI.createCarpool(carpoolData);
            alert('Carpool created successfully!');
            form.reset();
            loadCarpoolData(); // Refresh carpool data
        } catch (error) {
            alert('Failed to create carpool: ' + error.message);
        }
    });
}

// Dashboard data loading
async function loadDashboardData() {
    const user = AuthAPI.getCurrentUser();
    if (!user) {
        window.location.href = 'index.html';
        return;
    }
    
    try {
        // Load user analytics
        const analytics = await TripsAPI.getAnalytics(user.id);
        updateDashboardUI(analytics);
    } catch (error) {
        console.error('Failed to load dashboard data:', error);
    }
}

function updateDashboardUI(analytics) {
    // Update eco score
    const ecoScoreElement = document.getElementById('ecoScore');
    if (ecoScoreElement && analytics.total_stats) {
        ecoScoreElement.textContent = analytics.total_stats.eco_score || 0;
    }
    
    // Update CO2 saved
    const co2SavedElement = document.getElementById('co2Saved');
    if (co2SavedElement && analytics.total_stats) {
        co2SavedElement.textContent = (analytics.total_stats.total_co2_saved || 0).toFixed(2);
    }
    
    // Update total trips
    const totalTripsElement = document.getElementById('totalTrips');
    if (totalTripsElement && analytics.total_stats) {
        totalTripsElement.textContent = analytics.total_stats.total_trips || 0;
    }
}

// Initialize page-specific functionality
function initializePage() {
    const currentPage = window.location.pathname.split('/').pop();
    
    switch(currentPage) {
        case 'register.html':
            handleRegisterForm();
            break;
        case 'index.html':
        case '':
            handleLoginForm();
            break;
        case 'dashboard.html':
            loadDashboardData();
            handleTripForm();
            break;
        case 'carpool.html':
            handleCarpoolForm();
            loadCarpoolData();
            break;
    }
}

// Load carpool data
async function loadCarpoolData() {
    try {
        const result = await CarpoolAPI.searchCarpools();
        displayCarpools(result.carpools);
    } catch (error) {
        console.error('Failed to load carpools:', error);
    }
}

function displayCarpools(carpools) {
    const container = document.getElementById('carpoolList');
    if (!container) return;
    
    container.innerHTML = carpools.map(carpool => `
        <div class="carpool-item">
            <h3>${carpool.from_location} → ${carpool.to_location}</h3>
            <p>Driver: ${carpool.driver_name}</p>
            <p>Date: ${carpool.departure_date} at ${carpool.departure_time}</p>
            <p>Available Seats: ${carpool.available_seats}</p>
        </div>
    `).join('');
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initializePage);
