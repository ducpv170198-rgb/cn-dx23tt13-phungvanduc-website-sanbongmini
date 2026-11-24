import { reactive } from 'vue';
import { api } from './http.js';

export const session = reactive({ user: null, ready: false });

export async function restoreSession() {
    const token = localStorage.getItem('sanbong_token');
    if (token) {
        try {
            const { data } = await api.get('/auth/me');
            session.user = data.user;
        } catch {
            localStorage.removeItem('sanbong_token');
            session.user = null;
        }
    }
    session.ready = true;
}

export function applyLogin(data) {
    localStorage.setItem('sanbong_token', data.token);
    session.user = data.user;
    session.ready = true;
}

export async function logout() {
    try { await api.post('/auth/logout'); } catch { /* Xóa phiên cục bộ ngay cả khi API lỗi. */ }
    localStorage.removeItem('sanbong_token');
    session.user = null;
}
