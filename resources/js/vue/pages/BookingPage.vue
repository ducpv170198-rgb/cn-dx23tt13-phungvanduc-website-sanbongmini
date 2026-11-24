<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { api, errorText } from '../lib/http.js';
import { session } from '../lib/session.js';
import { todayVn, money, shortTime, statusLabel, addMinutesSql, canReserve } from '../lib/utils.js';

const date = ref(todayVn());
const type = ref('5');
const pitches = ref([]), slots = ref([]), services = ref([]);
const selected = ref(null), duration = ref(60), quote = ref(null);
const loading = ref(false), quoting = ref(false), booking = ref(false);
const notice = ref(''), problem = ref(''), receipt = ref(null), lookup = ref(null);
const depositProblem = ref(''), fetchingDeposit = ref(false);
const form = ref({ customer_name: '', customer_phone: '', customer_email: '', note: '' });
const quantities = ref({});
const serviceProblem = ref(''), loadingServices = ref(false);
const times = computed(() => Array.from(new Set(slots.value.map(s => shortTime(s.start_at)))).sort());
const choiceSlots = computed(() => selected.value ? slots.value.filter(s => Number(s.pitch_id) === Number(selected.value.pitch_id)) : []);
const endAt = computed(() => { if (!selected.value) return ''; try { return addMinutesSql(selected.value.start_at, Number(duration.value)); } catch { return ''; } });
const selectionValid = computed(() => !!selected.value && canReserve(choiceSlots.value, selected.value.pitch_id, selected.value.start_at, Number(duration.value)));
const pickedServices = computed(() => services.value.filter(s => Number(quantities.value[s.id]) > 0).map(s => ({ service_id: s.id, quantity: Number(quantities.value[s.id]) })));

watch([selected, duration, quantities], () => { quote.value = null; }, { deep: true });
watch(() => session.user, (user) => { if (user) { form.value.customer_name = user.name || ''; form.value.customer_email = user.email || ''; } }, { immediate: true });

async function loadAvailability() {
    problem.value = ''; notice.value = ''; depositProblem.value = ''; selected.value = null; quote.value = null; receipt.value = null; lookup.value = null;
    loading.value = true;
    try {
        const { data } = await api.get('/availability', { params: { date: date.value, type: type.value } });
        pitches.value = data.pitches || [];
        slots.value = data.slots || [];
    } catch (e) { problem.value = errorText(e); pitches.value = []; slots.value = []; }
    finally { loading.value = false; }
}
function slotAt(pitch, time) { return slots.value.find(s => Number(s.pitch_id) === Number(pitch.id) && shortTime(s.start_at) === time); }
function choose(slot) { selected.value = { pitch_id: slot.pitch_id, start_at: slot.start_at }; notice.value = ''; problem.value = ''; }
async function loadQuote() {
    if (!selectionValid.value) { problem.value = 'Khung giờ đã chọn không đủ thời lượng hoặc không còn trống.'; return; }
    quoting.value = true; problem.value = '';
    try {
        // Axios tự tạo query string kiểu services[0][service_id] theo chuẩn Laravel.
        const { data } = await api.get('/pricing/quote', { params: { pitch_id: selected.value.pitch_id, start_at: selected.value.start_at, end_at: endAt.value, services: pickedServices.value } });
        quote.value = data;
    } catch (e) { problem.value = errorText(e); quote.value = null; }
    finally { quoting.value = false; }
}
async function submitBooking() {
    if (!selectionValid.value) return;
    booking.value = true; problem.value = ''; depositProblem.value = '';
    try {
        // Báo giá chỉ để tham khảo; backend kiểm tra lại trùng lịch / giá trong transaction.
        const { data } = await api.post('/bookings', { ...form.value, pitch_id: selected.value.pitch_id, start_at: selected.value.start_at, end_at: endAt.value, services: pickedServices.value });
        receipt.value = data; notice.value = data.message;
        await loadAvailabilityOnly();
    } catch (e) { problem.value = errorText(e); }
    finally { booking.value = false; }
}
async function loadAvailabilityOnly() {
    try {
        const { data } = await api.get('/availability', { params: { date: date.value, type: type.value } });
        pitches.value = data.pitches || []; slots.value = data.slots || [];
    } catch { /* Giữ biên nhận đã được tạo thành công. */ }
}
async function checkStatus() {
    if (!receipt.value?.data?.booking_code) return;
    problem.value = '';
    try {
        const { data } = await api.post('/bookings/status', { booking_code: receipt.value.data.booking_code, customer_phone: form.value.customer_phone });
        lookup.value = data.data;
        if (lookup.value?.status === 'confirmed') receipt.value.deposit = null;
    } catch (e) { problem.value = errorText(e); }
}
// Cho phép tài khoản đã đăng nhập lấy lại mã cọc cho đơn còn chờ xác nhận.
// Endpoint Laravel đã kiểm tra quyền sở hữu đơn hoặc quyền admin/staff.
async function fetchExistingDeposit() {
    const bookingId = receipt.value?.data?.id;
    if (!bookingId || !session.user || fetchingDeposit.value) return;
    fetchingDeposit.value = true;
    depositProblem.value = '';
    try {
        const { data } = await api.get(`/bookings/${bookingId}/vietqr`);
        if (Number(data.amount_vnd) > 0 && data.qr_image_url) {
            receipt.value.deposit = {
                amount_vnd: data.amount_vnd,
                account_number: data.account_number,
                account_name: data.account_name,
                transfer_reference: data.transfer_reference,
                qr_image_url: data.qr_image_url,
            };
            receipt.value.deposit_notice = null;
        } else {
            depositProblem.value = 'Đơn không còn số tiền cọc cần thanh toán. Hãy kiểm tra trạng thái thanh toán với quản lý.';
        }
    } catch (e) { depositProblem.value = errorText(e); }
    finally { fetchingDeposit.value = false; }
}
async function loadServices() {
    loadingServices.value = true;
    serviceProblem.value = '';
    try {
        const { data } = await api.get('/services');
        services.value = Array.isArray(data) ? data : [];
    } catch (e) {
        services.value = [];
        serviceProblem.value = 'Không tải được dịch vụ: ' + errorText(e);
    } finally { loadingServices.value = false; }
}
onMounted(async () => { await Promise.all([loadServices(), loadAvailability()]); });
</script>

<template>
  <div class="hero"><h1>Đặt sân bóng nhanh chóng</h1><p>Xem lịch sân trống theo ngày, lựa chọn sân 5 hoặc sân 7, báo giá chính xác và gửi yêu cầu đặt sân ngay trên website.</p></div>
  <section class="card">
    <h2>1. Chọn sân và ngày thi đấu</h2>
    <form class="toolbar" @submit.prevent="loadAvailability">
      <div class="field"><label for="date">Ngày đặt sân</label><input id="date" v-model="date" type="date" :min="todayVn()" required></div>
      <div class="field"><label for="type">Loại sân</label><select id="type" v-model="type"><option value="5">Sân 5 người</option><option value="7">Sân 7 người</option></select></div>
      <button class="btn" type="submit" :disabled="loading">{{ loading ? 'Đang tải...' : 'Xem lịch trống' }}</button>
    </form>
    <p class="hint">Màu xanh: còn trống · Màu xám: đã đặt, đã qua hoặc chưa có bảng giá. Lịch từ 06:00 đến 22:00, bước 30 phút.</p>
    <div v-if="loading" class="message info">Đang lấy lịch sân từ Laravel API...</div>
    <div v-else-if="!pitches.length" class="empty">Chưa có sân phù hợp hoặc không thể tải lịch sân.</div>
    <div v-else class="schedule-scroll"><table class="schedule-table"><thead><tr><th>Sân</th><th v-for="time in times" :key="time">{{ time }}</th></tr></thead><tbody>
      <tr v-for="pitch in pitches" :key="pitch.id"><td>{{ pitch.name }}<div class="tiny muted">{{ pitch.type }} người</div></td><td v-for="time in times" :key="time">
        <button class="slot" type="button" :disabled="!slotAt(pitch,time)?.available" :class="{ selected: selected?.pitch_id===pitch.id && shortTime(selected?.start_at)===time }" :title="slotAt(pitch,time)?.available ? money(slotAt(pitch,time)?.price) + ' / 30 phút' : 'Không khả dụng'" @click="choose(slotAt(pitch,time))">{{ slotAt(pitch,time)?.available ? 'Trống' : '—' }}</button>
      </td></tr>
    </tbody></table></div>
  </section>
  <div class="grid grid-2">
    <section class="card"><h2>2. Thông tin đơn đặt sân</h2>
      <div v-if="!selected" class="empty">Chọn một ô màu xanh trên lịch để bắt đầu.</div>
      <template v-else>
        <div class="summary mb">Bắt đầu: <b>{{ selected.start_at }}</b><div class="mt">Kết thúc: <b>{{ endAt }}</b></div></div>
        <div class="field mb"><label for="duration">Thời lượng thuê</label><select id="duration" v-model.number="duration"><option :value="30">30 phút</option><option :value="60">60 phút</option><option :value="90">90 phút</option><option :value="120">120 phút</option></select></div>
        <p v-if="!selectionValid" class="message error">Không đủ các ô trống liên tiếp cho thời lượng đã chọn.</p>
        <h3>Dịch vụ đi kèm</h3>
        <div v-if="serviceProblem" class="message error" role="alert">{{ serviceProblem }} <button type="button" class="btn btn-outline small" @click="loadServices">Tải lại</button></div>
        <div v-else-if="loadingServices" class="muted mb">Đang tải dịch vụ...</div>
        <div v-else-if="!services.length" class="muted mb">Chưa có dịch vụ đang kinh doanh. Quản trị viên có thể thêm trong Admin → Dịch vụ & tồn kho.</div>
        <div v-for="service in services" :key="service.id" class="toolbar"><div style="flex:1">{{ service.name }} <span class="muted tiny">{{ money(service.price) }} / {{ service.unit || 'đơn vị' }} · {{ service.stock === null ? 'không giới hạn' : Number(service.stock)===0 ? 'hết hàng' : 'tồn ' + service.stock }}</span></div><div class="field" style="min-width:86px;width:92px"><input :disabled="service.stock !== null && Number(service.stock)===0" v-model.number="quantities[service.id]" :aria-label="'Số lượng ' + service.name" type="number" min="0" :max="service.stock ?? 100" step="1"></div></div>
        <button class="btn btn-outline" type="button" :disabled="!selectionValid || quoting" @click="loadQuote">{{ quoting ? 'Đang tính...' : 'Tính giá tạm tính' }}</button>
        <div v-if="quote" class="summary mt">Tiền sân: {{ money(quote.pitch_price) }}<div>Dịch vụ: {{ money(quote.service_total) }}</div><div class="divider"></div><strong>Tổng cộng: {{ money(quote.total_amount) }}</strong><p class="muted tiny">Giá được kiểm tra lại lúc đặt sân.</p></div>
      </template>
    </section>
    <section class="card"><h2>3. Thông tin người đặt</h2>
      <form class="grid" @submit.prevent="submitBooking">
        <div class="field"><label for="customer_name">Họ và tên *</label><input id="customer_name" v-model.trim="form.customer_name" required maxlength="120"></div>
        <div class="field"><label for="customer_phone">Số điện thoại *</label><input id="customer_phone" v-model.trim="form.customer_phone" required maxlength="30" type="tel"></div>
        <div class="field"><label for="customer_email">Email</label><input id="customer_email" v-model.trim="form.customer_email" type="email"></div>
        <div class="field"><label for="note">Ghi chú</label><textarea id="note" v-model.trim="form.note" maxlength="1000"></textarea></div>
        <button class="btn" :disabled="!selectionValid || booking" type="submit">{{ booking ? 'Đang tạo đơn...' : 'Gửi yêu cầu đặt sân' }}</button>
      </form>
    </section>
  </div>
  <p v-if="problem" class="message error" role="alert">{{ problem }}</p><p v-if="notice" class="message" role="status">{{ notice }}</p>
  <section v-if="receipt" class="card" id="booking-receipt"><h2>Thông tin đơn đã tạo</h2>
    <p>Mã đặt sân: <b>{{ receipt.data?.booking_code }}</b> · Trạng thái: <span class="pill" :class="lookup?.status || receipt.data?.status">{{ statusLabel(lookup?.status || receipt.data?.status) }}</span></p>
    <p>Giá trị đơn: <b>{{ money(receipt.data?.total_amount) }}</b></p>
    <div v-if="receipt.deposit && lookup?.status !== 'confirmed'" class="grid grid-2"><div><p>Tiền cọc cần chuyển: <b>{{ money(receipt.deposit.amount_vnd) }}</b></p><p>Ngân hàng: {{ receipt.deposit.account_name }}<br>Số tài khoản: <b>{{ receipt.deposit.account_number }}</b><br>Nội dung: <b>{{ receipt.deposit.transfer_reference }}</b></p><p class="hint">Chuyển khoản không tự động xác nhận. Quản lý kiểm tra và duyệt đơn sau khi nhận tiền.</p></div><div><img class="qr" :src="receipt.deposit.qr_image_url" alt="Mã VietQR đặt cọc do API cung cấp"></div></div>
    <p v-else-if="lookup?.status === 'confirmed'" class="message">Lịch đặt sân đã được xác nhận.</p>
    <p v-else-if="receipt.deposit_notice" class="message error" role="alert">{{ receipt.deposit_notice }}</p>
    <p v-if="depositProblem" class="message error" role="alert">{{ depositProblem }}</p>
    <div class="actions">
      <button v-if="!receipt.deposit && session.user && receipt.data?.id && (lookup?.status || receipt.data?.status) === 'pending'"
        class="btn btn-outline" type="button" :disabled="fetchingDeposit" @click="fetchExistingDeposit">
        {{ fetchingDeposit ? 'Đang lấy VietQR...' : 'Lấy lại mã VietQR' }}
      </button>
      <button class="btn btn-outline" type="button" @click="checkStatus">Kiểm tra trạng thái đơn</button>
    </div>
  </section>
</template>
