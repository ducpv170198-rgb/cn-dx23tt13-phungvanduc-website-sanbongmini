export const money = (amount) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(amount || 0));
export const todayVn = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
export const readableDate = (value) => value ? String(value).replace('T', ' ').slice(0, 16) : '—';
// Ngày/giờ lịch đặt sân được trả về theo định dạng SQL của ứng dụng.
// Tách trực tiếp chuỗi để không làm lệch ngày vì múi giờ của máy quản trị.
export function bookingDateVN(value) {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T]|$)/);
    return match ? `${match[3]}/${match[2]}/${match[1]}` : '—';
}
export function bookingTimeRange(startAt, endAt) {
    const time = value => String(value || '').match(/[ T](\d{2}:\d{2})(?::\d{2})?/)?.[1] || '';
    const from = time(startAt), to = time(endAt);
    return from && to ? `${from}–${to}` : '—';
}
export const shortTime = (value) => String(value || '').slice(11, 16);
export const statusLabel = (value) => ({ in_use: 'Đang sử dụng', awaiting_payment: 'Chờ thanh toán', payment_unknown: 'Cần kiểm tra hóa đơn', pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', completed: 'Đã hoàn tất', cancelled: 'Đã hủy', active: 'Hoạt động', maintenance: 'Bảo trì', inactive: 'Ngừng hoạt động' })[value] || value || '—';

// Chỉ làm việc với ngày giờ dạng SQL và thời lượng theo phút; không phụ thuộc múi giờ máy khách.
export function addMinutesSql(sql, minutes) {
    if (!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(sql)) throw new Error('Ngày giờ SQL không hợp lệ');
    const [date, time] = sql.split(' ');
    const [hh, mm] = time.split(':').map(Number);
    const total = hh * 60 + mm + Number(minutes);
    if (!Number.isInteger(total) || total > 24 * 60 || total < 0) throw new Error('Giờ đặt vượt phạm vi ngày');
    const h = Math.floor(total / 60), m = total % 60;
    if (total === 24 * 60) throw new Error('Không hỗ trợ đặt sân qua ngày hôm sau');
    return `${date} ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:00`;
}

export function canReserve(slots, pitchId, startAt, duration) {
    if (![30, 60, 90, 120].includes(Number(duration))) return false;
    const lookup = new Map(slots.filter(s => Number(s.pitch_id) === Number(pitchId)).map(s => [s.start_at, s]));
    for (let offset = 0; offset < duration; offset += 30) {
        let candidate;
        try { candidate = addMinutesSql(startAt, offset); } catch { return false; }
        if (!lookup.get(candidate)?.available) return false;
    }
    return true;
}

export function apiDateRange(month) {
    const [y, m] = month.split('-').map(Number);
    const endDay = new Date(Date.UTC(y, m, 0)).getUTCDate();
    return { from: `${month}-01`, to: `${month}-${String(endDay).padStart(2, '0')}` };
}

export function uniqueRequestKey() {
    return (globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random().toString(36).slice(2)}`);
}

// SQL DATETIME được lưu theo giờ Việt Nam (UTC+7).
export function canCancelBooking(booking, nowMs = Date.now()) {
    if (!['pending', 'confirmed'].includes(booking?.status)) return false;
    const raw = String(booking.start_at || '');
    if (!/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/.test(raw)) return false;
    const start = Date.parse(raw.replace(' ', 'T') + '+07:00');
    return Number.isFinite(start) && start > nowMs;
}

// Trạng thái hiển thị, không thay đổi enum nghiệp vụ trong MySQL.
export function bookingLifecycle(booking, nowMs = Date.now()) {
    if (!['confirmed', 'completed'].includes(booking?.status)) return booking?.status;
    const parse = raw => /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/.test(String(raw || ''))
        ? Date.parse(raw.replace(' ', 'T') + '+07:00') : NaN;
    const start = parse(booking.start_at), end = parse(booking.end_at);
    if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return booking.status;
    if (nowMs < start) return booking.status;
    if (nowMs < end) return 'in_use';
    if (!booking.payment_status) return 'payment_unknown';
    return booking.payment_status === 'paid' ? 'completed' : 'awaiting_payment';
}
