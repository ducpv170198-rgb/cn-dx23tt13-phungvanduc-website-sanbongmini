<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api, errorText, rowsOf } from '../lib/http.js';
import { session } from '../lib/session.js';
import { money, statusLabel } from '../lib/utils.js';

const router = useRouter();
const orders = ref([]);
const filter = ref({ status: '', search: '' });
const page = ref(1), lastPage = ref(1), total = ref(0);
const loading = ref(false), detailBusy = ref(false), actionBusy = ref(false), qrBusy = ref(false);
const error = ref(''), notice = ref(''), detailError = ref(''), qrError = ref('');
const selected = ref(null), lines = ref([]), payments = ref([]), qr = ref(null);

const paymentLabels = {
  unpaid: 'Chưa thanh toán', deposit_paid: 'Đã cọc', paid: 'Đã thanh toán', refunded: 'Đã hoàn tiền',
};
const paymentMethods = { cash: 'Tiền mặt', vietqr: 'VietQR', bank_transfer: 'Chuyển khoản ngân hàng', other: 'Khác' };
function paymentLabel(value) { return paymentLabels[value] || 'Chưa thanh toán'; }
function methodLabel(value) { return paymentMethods[value] || value || '—'; }
function displayDate(value) {
  if (!value) return '—';
  const text = String(value).replace('T', ' ');
  const match = text.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}:\d{2})/);
  return match ? `${match[3]}/${match[2]}/${match[1]} ${match[4]}` : text;
}
function displayDay(value) { return displayDate(value).split(' ')[0]; }
function displayHour(value) { return displayDate(value).split(' ')[1] || ''; }
const canViewQr = computed(() => selected.value && selected.value.invoice_id
  && ['pending', 'confirmed'].includes(selected.value.status)
  && Number(selected.value.remaining_amount) > 0);

async function loadOrders(next = 1) {
  if (!session.user) return;
  loading.value = true; error.value = '';
  try {
    const { data } = await api.get('/my-bookings', {
      params: { page: next, per_page: 10, status: filter.value.status || undefined, search: filter.value.search.trim() || undefined },
    });
    orders.value = rowsOf(data);
    page.value = data.current_page || next;
    lastPage.value = data.last_page || 1;
    total.value = data.total || 0;
  } catch (e) { error.value = errorText(e); }
  finally { loading.value = false; }
}
async function viewOrder(orderId) {
  selected.value = null; lines.value = []; payments.value = []; qr.value = null;
  detailError.value = ''; qrError.value = ''; detailBusy.value = true;
  try {
    const { data } = await api.get(`/my-bookings/${orderId}`);
    selected.value = data.data;
    lines.value = data.items || [];
    payments.value = data.payments || [];
  } catch (e) { detailError.value = errorText(e); }
  finally { detailBusy.value = false; }
}
async function showQr() {
  if (!selected.value) return;
  qr.value = null; qrError.value = ''; qrBusy.value = true;
  try {
    const { data } = await api.get(`/bookings/${selected.value.id}/vietqr`);
    qr.value = data;
  } catch (e) { qrError.value = errorText(e); }
  finally { qrBusy.value = false; }
}
async function cancelOrder(item) {
  if (!item.can_cancel || actionBusy.value) return;
  if (!confirm(`Bạn muốn hủy đơn ${item.booking_code}? Đơn đang chờ xác nhận và chưa phát sinh thanh toán mới có thể tự hủy.`)) return;
  actionBusy.value = true; error.value = ''; detailError.value = ''; notice.value = '';
  try {
    await api.patch(`/my-bookings/${item.id}/cancel`, { reason: 'Thành viên hủy trong Đơn của tôi' });
    notice.value = `Đã hủy đơn ${item.booking_code}.`;
    selected.value = null; qr.value = null;
    await loadOrders(page.value);
  } catch (e) { error.value = errorText(e); }
  finally { actionBusy.value = false; }
}
function resetAndSearch() { selected.value = null; qr.value = null; notice.value = ''; loadOrders(1); }
function closeDetail() { selected.value = null; lines.value = []; payments.value = []; qr.value = null; detailError.value = ''; }

watch(() => session.user?.id, (id) => {
  if (id) loadOrders(1);
  else { orders.value = []; closeDetail(); }
});
onMounted(() => { if (session.user) loadOrders(1); else router.replace({ path: '/tai-khoan', query: { next: '/don-cua-toi' } }); });
</script>

<template>
  <div class="page-title">
    <div><h1>Đơn đặt sân của tôi</h1><p>Xem lịch sử đặt sân, hóa đơn, tiền cọc và trạng thái thanh toán theo tài khoản thành viên.</p></div>
    <router-link to="/dat-san" class="btn">+ Đặt sân mới</router-link>
  </div>

  <section class="card">
    <form class="toolbar" @submit.prevent="resetAndSearch">
      <div class="field"><label for="member-order-search">Mã đơn hoặc tên sân</label><input id="member-order-search" v-model="filter.search" maxlength="60" placeholder="VD: FD261122..."></div>
      <div class="field"><label for="member-order-status">Trạng thái đơn</label><select id="member-order-status" v-model="filter.status"><option value="">Tất cả</option><option value="pending">Chờ xác nhận</option><option value="confirmed">Đã xác nhận</option><option value="completed">Đã hoàn tất</option><option value="cancelled">Đã hủy</option></select></div>
      <button type="submit" class="btn" :disabled="loading">{{ loading ? 'Đang tải...' : 'Lọc đơn' }}</button>
      <button type="button" class="btn btn-outline" :disabled="loading" @click="loadOrders(page)">Làm mới</button>
    </form>
    <p class="hint">Chỉ hiển thị những đơn được tạo khi bạn đã đăng nhập tài khoản này. Đơn đặt với tư cách khách chưa tự động liên kết với tài khoản.</p>
    <p v-if="error" class="message error" role="alert">{{ error }}</p>
    <p v-if="notice" class="message" role="status">{{ notice }}</p>
    <div class="table-scroll">
      <table class="data-table" style="min-width:1000px">
        <thead><tr><th>Mã đơn</th><th>Sân</th><th>Ngày sử dụng</th><th>Tổng tiền</th><th>Đã thanh toán</th><th>Còn lại</th><th>Đặt sân</th><th>Thanh toán</th><th>Thao tác</th></tr></thead>
        <tbody>
          <tr v-for="item in orders" :key="item.id">
            <td><strong>{{ item.booking_code }}</strong></td>
            <td>{{ item.pitch_name || '—' }}</td>
            <td>{{ displayDay(item.start_at) }}<div class="tiny muted">{{ displayHour(item.start_at) }} – {{ displayHour(item.end_at) }}</div></td>
            <td>{{ money(item.total_amount) }}</td>
            <td>{{ money(item.paid_amount) }}</td>
            <td>{{ item.status === 'cancelled' ? '—' : money(item.remaining_amount) }}</td>
            <td><span class="pill" :class="item.status">{{ statusLabel(item.status) }}</span></td>
            <td><span class="pill" :class="item.payment_status">{{ paymentLabel(item.payment_status) }}</span></td>
            <td><div class="actions"><button type="button" class="btn small btn-outline" :disabled="detailBusy" @click="viewOrder(item.id)">Chi tiết</button><button v-if="item.can_cancel" type="button" class="btn small btn-danger" :disabled="actionBusy" @click="cancelOrder(item)">Hủy</button></div></td>
          </tr>
          <tr v-if="!orders.length"><td colspan="9" class="empty">{{ loading ? 'Đang tải đơn đã đặt...' : 'Không có đơn phù hợp. Đăng nhập trước khi đặt sân để đơn được lưu vào tài khoản.' }}</td></tr>
        </tbody>
      </table>
    </div>
    <div class="form-actions"><span class="muted">{{ total }} đơn</span><button type="button" class="btn btn-outline small" :disabled="page<=1||loading" @click="loadOrders(page-1)">Trang trước</button><span>{{ page }}/{{ lastPage }}</span><button type="button" class="btn btn-outline small" :disabled="page>=lastPage||loading" @click="loadOrders(page+1)">Trang sau</button></div>
  </section>

  <p v-if="detailError" class="message error" role="alert">{{ detailError }}</p>
  <p v-if="detailBusy" class="message info">Đang tải chi tiết hóa đơn...</p>
  <section v-if="selected" class="card" id="member-order-detail">
    <div class="page-title"><div><h2>Chi tiết đơn {{ selected.booking_code }}</h2><p>{{ selected.pitch_name }} · {{ displayDate(selected.start_at) }} – {{ displayHour(selected.end_at) }}</p></div><button type="button" class="btn btn-outline" @click="closeDetail">Đóng</button></div>
    <div class="grid grid-3 mb">
      <div class="summary"><div class="muted">Trạng thái đặt sân</div><p><span class="pill" :class="selected.status">{{ statusLabel(selected.status) }}</span></p><div class="tiny">Ngày sử dụng: {{ displayDay(selected.start_at) }}</div></div>
      <div class="summary"><div class="muted">Thanh toán</div><p><span class="pill" :class="selected.payment_status">{{ paymentLabel(selected.payment_status) }}</span></p><div>Đã thu: <b>{{ money(selected.paid_amount) }}</b></div></div>
      <div class="summary"><div class="muted">Còn phải thanh toán</div><p><strong>{{ selected.status === 'cancelled' ? 'Không còn hiệu lực' : money(selected.remaining_amount) }}</strong></p><div class="tiny">Tổng hóa đơn: {{ money(selected.total_amount) }}</div></div>
    </div>
    <div class="grid grid-2">
      <div>
        <h3>Chi tiết hóa đơn</h3>
        <div class="table-scroll"><table class="data-table"><thead><tr><th>Nội dung</th><th>Số lượng</th><th>Thành tiền</th></tr></thead><tbody><tr v-for="(line, index) in lines" :key="index"><td>{{ line.description }}</td><td>{{ line.quantity }}</td><td>{{ money(line.line_total) }}</td></tr><tr v-if="!lines.length"><td colspan="3">Không có dòng hóa đơn.</td></tr></tbody></table></div>
        <p class="mt"><b>Tổng tiền sân:</b> {{ money(selected.pitch_price) }} · <b>Dịch vụ:</b> {{ money(selected.service_total) }}</p>
      </div>
      <div>
        <h3>Lịch sử thanh toán</h3>
        <div v-if="!payments.length" class="hint">Chưa có giao dịch được quản trị viên ghi nhận.</div>
        <div v-for="(payment, index) in payments" :key="index" class="list-card">
          <b>{{ payment.kind === 'refund' ? 'Hoàn tiền' : 'Đã thu' }} {{ money(payment.amount) }}</b>
          <p>{{ methodLabel(payment.method) }} · {{ displayDate(payment.created_at) }}</p>
        </div>
        <div class="actions mt">
          <button v-if="canViewQr" type="button" class="btn btn-outline" :disabled="qrBusy" @click="showQr">{{ qrBusy ? 'Đang tải...' : 'Xem mã VietQR đặt cọc' }}</button>
          <button v-if="selected.can_cancel" type="button" class="btn btn-danger" :disabled="actionBusy" @click="cancelOrder(selected)">Hủy đơn chưa xác nhận</button>
        </div>
        <p v-if="qrError" class="message error" role="alert">{{ qrError }}</p>
        <div v-if="qr" class="summary mt">
          <template v-if="qr.amount_vnd > 0 && qr.qr_image_url">
            <p><b>Số tiền cọc cần chuyển: {{ money(qr.amount_vnd) }}</b></p>
            <p>Chủ tài khoản: {{ qr.account_name }}<br>Số tài khoản: <b>{{ qr.account_number }}</b><br>Nội dung: <b>{{ qr.transfer_reference }}</b></p>
            <img class="qr" :src="qr.qr_image_url" alt="Mã VietQR hướng dẫn đặt cọc">
            <p class="hint">Chỉ chuyển khoản sau khi xác nhận tài khoản nhận tiền của chủ sân. Mã QR không có nghĩa là đã thanh toán.</p>
          </template>
          <p v-else>Đơn đã đủ cọc, đã thanh toán hoặc không thuộc diện yêu cầu đặt cọc.</p>
        </div>
      </div>
    </div>
  </section>
</template>
