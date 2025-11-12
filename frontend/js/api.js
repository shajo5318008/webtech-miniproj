// API Configuration
const API_BASE_URL = 'http://localhost:8000';


// Utility function for making API calls
async function makeAPICall(endpoint, method = 'GET', data = null) {
    const config = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };

    if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
        config.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(`${API_BASE_URL}/${endpoint}`, config);
        const text = await response.text();
        let result = null;
        try { result = text ? JSON.parse(text) : null; } catch (err) { result = text; }

        if (!response.ok) {
            const msg = (result && result.message) ? result.message : `API call failed (${response.status})`;
            throw new Error(msg);
        }

        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// =====================
// Authentication API
// =====================
const AuthAPI = {
    async register(userData) {
        return await makeAPICall('auth.php?action=register', 'POST', userData);
    },

    async login(credentials) {
        const result = await makeAPICall('auth.php?action=login', 'POST', credentials);
        if (result && result.user) {
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

// =====================
// Trips API (existing)
// =====================
const TripsAPI = {
    async logTrip(tripData) {
        return await makeAPICall('trips.php', 'POST', tripData);
    },

    async getAnalytics(userId) {
        return await makeAPICall(`trips.php?action=analytics&user_id=${encodeURIComponent(userId)}`);
    },

    async getTripHistory(userId) {
        return await makeAPICall(`trips.php?action=history&user_id=${encodeURIComponent(userId)}`);
    }
};

// =====================
// Carpool API (existing)
// =====================
const CarpoolAPI = {
    async createCarpool(carpoolData) {
        return await makeAPICall('carpool.php?action=create', 'POST', carpoolData);
    },

    async searchCarpools(from = '', to = '', date = '') {
        let query = 'carpool.php?action=search';
        const params = [];

        if (from) params.push(`from=${encodeURIComponent(from)}`);
        if (to) params.push(`to=${encodeURIComponent(to)}`);
        if (date) params.push(`date=${encodeURIComponent(date)}`);

        if (params.length > 0) query += '&' + params.join('&');
        return await makeAPICall(query);
    },

    async getUserCarpools(userId) {
        return await makeAPICall(`carpool.php?action=user_carpools&user_id=${encodeURIComponent(userId)}`);
    }
};

// =====================
// Rides API (NEW)
// =====================
const RidesAPI = {
    /**
     * Create a ride (driver posts a trip)
     * rideData: { driver_id, start_location, end_location, departure_time (YYYY-MM-DD HH:MM:SS), available_seats, fare?, vehicle_id? }
     */
    async createRide(rideData) {
        return await makeAPICall('rides.php?action=create', 'POST', rideData);
    },

    async search(from = '', to = '', date = '', min_seats = 1) {
        let q = `rides.php?action=search&min_seats=${encodeURIComponent(min_seats)}`;
        if (from) q += `&from=${encodeURIComponent(from)}`;
        if (to) q += `&to=${encodeURIComponent(to)}`;
        if (date) q += `&date=${encodeURIComponent(date)}`;
        return await makeAPICall(q);
    },

    async getDriverRides(driverId) {
        return await makeAPICall(`rides.php?action=driver_rides&driver_id=${encodeURIComponent(driverId)}`);
    }
};

// =====================
// Form handlers & UI
// =====================

// Register form
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
            await AuthAPI.register(userData);
            alert('Registration successful! Please login.');
            window.location.href = 'index.html';
        } catch (error) {
            alert('Registration failed: ' + error.message);
        }
    });
}

// Login form
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
            await AuthAPI.login(credentials);
            alert('Login successful!');
            window.location.href = 'dashboard.html';
        } catch (error) {
            alert('Login failed: ' + error.message);
        }
    });
}

// Trip form (existing "log trip")
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

// Carpool form (existing)
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
            await CarpoolAPI.createCarpool(carpoolData);
            alert('Carpool created successfully!');
            form.reset();
            loadCarpoolData(); // Refresh carpool data
        } catch (error) {
            alert('Failed to create carpool: ' + error.message);
        }
    });
}

// =====================
// Plan-a-Trip UI Handler (NEW)
// Requires HTML elements:
//  - #openPlanTrip (button), #planTripModal (modal wrapper, aria-hidden),
//  - #closePlanTrip, #cancelPlan, #planTripForm, #planTripMsg
// And a table body with id="recentTripsBody" to prepend new rides
// =====================
function handlePlanTripUI() {
    const openBtn = document.getElementById('openPlanTrip');
    if (!openBtn) return; // if UI doesn't exist, skip

    const modal = document.getElementById('planTripModal');
    const closeBtn = document.getElementById('closePlanTrip');
    const cancelBtn = document.getElementById('cancelPlan');
    const form = document.getElementById('planTripForm');
    const msg = document.getElementById('planTripMsg');

    function showModal() { if (modal) modal.setAttribute('aria-hidden', 'false'); }
    function hideModal() {
        if (modal) modal.setAttribute('aria-hidden', 'true');
        if (msg) { msg.textContent = ''; msg.style.color = ''; }
    }

    openBtn.addEventListener('click', () => {
        const user = AuthAPI.getCurrentUser();
        if (!user) { alert('Please login first'); window.location.href = 'index.html'; return; }
        if (user.role !== 'driver') { alert('Only drivers can create rides.'); return; }
        showModal();
    });

    if (closeBtn) closeBtn.addEventListener('click', hideModal);
    if (cancelBtn) cancelBtn.addEventListener('click', hideModal);
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) hideModal(); });

    // helper to build timestamp
    function makeTimestamp(dateVal, timeVal) {
        if (!dateVal) return null;
        const hhmm = timeVal || '00:00';
        return `${dateVal} ${hhmm}:00`;
    }

    if (!form) return;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!msg) return;

        msg.style.color = '#fff';
        msg.textContent = 'Creating ride...';

        const user = AuthAPI.getCurrentUser();
        if (!user || !user.id) { alert('Login required'); window.location.href = 'index.html'; return; }
        if (user.role !== 'driver') { msg.style.color = 'salmon'; msg.textContent = 'Only drivers can create rides'; return; }

        const start = document.getElementById('start_location')?.value.trim();
        const end = document.getElementById('end_location')?.value.trim();
        const date = document.getElementById('date')?.value;
        const time = document.getElementById('time')?.value;
        const seats = parseInt(document.getElementById('available_seats')?.value || '0', 10);
        const fareVal = document.getElementById('fare')?.value;

        if (!start || !end || !date || !time || !seats || seats <= 0) {
            msg.style.color = 'salmon';
            msg.textContent = 'Please fill all required fields.';
            return;
        }

        const payload = {
            driver_id: user.id,
            start_location: start,
            end_location: end,
            departure_time: makeTimestamp(date, time),
            available_seats: seats,
            fare: fareVal ? parseFloat(fareVal) : null
        };

        try {
            const result = await RidesAPI.createRide(payload);
            // result may be { message, ride } or array or object depending on backend wrapper
            msg.style.color = '#9fffcf';
            msg.textContent = (result.message || 'Ride created');

            // update recent trips table if present
            try {
                let newRide = null;
                if (result.ride) newRide = result.ride;
                else if (Array.isArray(result) && result.length) newRide = result[0];
                else if (result && result.id) newRide = result;

                if (newRide) {
                    const tbody = document.getElementById('recentTripsBody');
                    if (tbody) {
                        const dt = new Date(newRide.departure_time);
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${dt.toLocaleDateString(undefined, { day: '2-digit', month: 'short' })}</td>
                            <td>${newRide.start_location} → ${newRide.end_location}</td>
                            <td>-</td>
                            <td>${newRide.fare ?? '-'}</td>
                            <td>${newRide.available_seats}</td>
                        `;
                        tbody.prepend(tr);
                    }
                }
            } catch (uiErr) {
                console.warn('UI update failed', uiErr);
            }

            setTimeout(() => { hideModal(); form.reset(); }, 800);
        } catch (err) {
            console.error('Create ride error', err);
            msg.style.color = 'salmon';
            msg.textContent = err.message || 'Failed to create ride';
        }
    });
}

// =====================
// Dashboard data loading & UI updates
// =====================
async function loadDashboardData() {
    const user = AuthAPI.getCurrentUser();
    if (!user) {
        window.location.href = 'index.html';
        return;
    }

    try {
        // Load user analytics (if trips.php supports it)
        const analytics = await TripsAPI.getAnalytics(user.id);
        updateDashboardUI(analytics);
    } catch (error) {
        console.error('Failed to load dashboard data:', error);
    }
}

function updateDashboardUI(analytics) {
    // Update eco score
    const ecoScoreElement = document.getElementById('ecoScore');
    if (ecoScoreElement && analytics && analytics.total_stats) {
        ecoScoreElement.textContent = analytics.total_stats.eco_score || 0;
    }

    // Update CO2 saved
    const co2SavedElement = document.getElementById('co2Saved');
    if (co2SavedElement && analytics && analytics.total_stats) {
        co2SavedElement.textContent = (analytics.total_stats.total_co2_saved || 0).toFixed(2);
    }

    // Update total trips
    const totalTripsElement = document.getElementById('totalTrips');
    if (totalTripsElement && analytics && analytics.total_stats) {
        totalTripsElement.textContent = analytics.total_stats.total_trips || 0;
    }
}

// =====================
// Carpool loading helpers (existing)
// =====================
async function loadCarpoolData() {
    try {
        const result = await CarpoolAPI.searchCarpools();
        displayCarpools(result.carpools || []);
    } catch (error) {
        console.error('Failed to load carpools:', error);
    }
}

function displayCarpools(carpools) {
    const container = document.getElementById('carpoolList');
    if (!container) return;

    container.innerHTML = (carpools || []).map(carpool => `
        <div class="carpool-item">
            <h3>${carpool.from_location} → ${carpool.to_location}</h3>
            <p>Driver: ${carpool.driver_name}</p>
            <p>Date: ${carpool.departure_date} at ${carpool.departure_time}</p>
            <p>Available Seats: ${carpool.available_seats}</p>
        </div>
    `).join('');
}

// =====================
// Initialize per-page functionality
// =====================
function initializePage() {
    const currentPage = window.location.pathname.split('/').pop();

    switch (currentPage) {
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
            handlePlanTripUI();   // initialize Plan-a-Trip UI on dashboard
            break;
        case 'carpool.html':
            handleCarpoolForm();
            loadCarpoolData();
            break;
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initializePage);
