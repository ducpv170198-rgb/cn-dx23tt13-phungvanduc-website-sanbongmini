import axios from 'axios';

export const api = axios.create({
    baseURL: '/api',
    timeout: 15000,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('sanbong_token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

export function errorText(error) {
    const data = error?.response?.data;
    if (data?.errors) return Object.values(data.errors).flat().join(' ');
    return data?.message || (error?.response?.status === 401
        ? 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.'
        : error?.message || 'Không thể kết nối API Laravel.');
}

export const rowsOf = (payload) => Array.isArray(payload) ? payload : (payload?.data || []);
