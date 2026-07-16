// assets/js/api.js
// Funciones para consumir la API REST

const API_URL = '/MINERIA/api';

// Función genérica para peticiones fetch
async function apiRequest(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${localStorage.getItem('token') || ''}`
        }
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(`${API_URL}${endpoint}`, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Error en la petición');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Login
async function apiLogin(username, password) {
    const result = await apiRequest('/auth/login', 'POST', { username, password });
    if (result.token) {
        localStorage.setItem('token', result.token);
        localStorage.setItem('user', JSON.stringify(result.user));
    }
    return result;
}

// Logout
function apiLogout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
}

// Obtener todas las posiciones GPS
async function apiGetAllGPS() {
    return await apiRequest('/gps/all');
}

// Obtener última posición de un vehículo
async function apiGetLastGPS(vehiculoId) {
    return await apiRequest(`/gps/last/${vehiculoId}`);
}

// Verificar si el usuario está autenticado
function isAuthenticated() {
    return !!localStorage.getItem('token');
}

// Obtener usuario actual
function getCurrentUserAPI() {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
}