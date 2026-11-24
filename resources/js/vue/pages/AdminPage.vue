<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { api, errorText, rowsOf } from '../lib/http.js';
import ServiceManagement from './ServiceManagement.vue';
import { todayVn, money, readableDate, bookingDateVN, bookingTimeRange, statusLabel, apiDateRange, uniqueRequestKey, canCancelBooking, bookingLifecycle } from '../lib/utils.js';

// Nhãn tiếng Việt chỉ dùng trên giao diện; không đổi mã trạng thái lưu trong MySQL.
function invoiceStatusLabel(status) {
  const labels = {
    unpaid: 'Chưa thanh toán',
    deposit_paid: 'Đã cọc',
    paid: 'Đã thanh toán',
    refunded: 'Đã hoàn tiền',
  };
  return labels[status] || status || '—';
}

// Chỉ Tiền mặt/VietQR được chọn khi ghi nhận mới. Giữ nhãn cũ cho lịch sử giao dịch.
const paymentMethodLabels = Object.freeze({
  cash: 'Tiền mặt',
  bank_transfer: 'Chuyển khoản ngân hàng',
  vietqr: 'VietQR',
  other: 'Khác',
});
function paymentMethodLabel(method) {
  const key = String(method ?? '').trim().toLowerCase();
  return paymentMethodLabels[key] ?? (key ? String(method) : '—');
}

const tabs = [
  ['overview','Tổng quan'], ['bookings','Lịch đặt'], ['pitches','Quản lý sân'],
  ['pricing','Bảng giá'], ['services','Dịch vụ & tồn kho'], ['invoices','Hóa đơn'], ['reports','Báo cáo'],
  ['recurring','Đặt cố định'], ['customers','Khách hàng'],
];
const section = ref('overview'), busy = ref(false), problem = ref(''), notice = ref('');
const dashDate = ref(todayVn()), dashboard = ref(null), recent = ref([]);
const bookingFilters = ref({ date: '', status: '' }), adminBookings = ref([]), bookingPage = ref(1), bookingLast = ref(1), depositMethod = ref('vietqr');
const bookingQr = ref(null);
const pitches = ref([]), pitchEditId = ref(null), pitchForm = ref({ name:'',type:'5',surface:'',description:'',status:'active' });
const priceRules = ref([]), ruleEditId = ref(null), priceForm = ref({ pitch_id:'', day_type:0, start_time:'06:00', end_time:'07:00', price_per_hour:200000, effective_from:'', effective_to:'' });
const invoices = ref([]), invoiceSearch = ref(''), includeCancelled = ref(false), invoicePage = ref(1), invoiceLast = ref(1);
const detail = ref(null), payment = ref({kind:'receipt',method:'cash',amount:0,reference:'',idempotency_key:uniqueRequestKey()});
const reportMonth = ref(todayVn().slice(0,7)), monthly = ref(null), revenueRows = ref([]), cashRows = ref([]);
const recurringList = ref([]), recurringForm = ref({ pitch_id:'',customer_name:'',customer_phone:'',customer_email:'',first_date:todayVn(),start_time:'19:00',end_time:'20:00',weeks:4 });
const customers = ref([]), customerSearch = ref(''), customerDetails = ref([]), selectedCustomer = ref('');
const activePitches = computed(() => pitches.value.filter(p => p.status==='active'));
const revenueMax = computed(() => Math.max(1,...revenueRows.value.map(r => Number(r.revenue||0))));

async function run(operation, message = '') {
  busy.value = true;problem.value = '';notice.value = '';
  try { await operation(); if (message) notice.value = message; }
  catch(error) { problem.value = errorText(error); }
  finally { busy.value = false; }
}
async function load() {
  const loaders = {overview:loadOverview,bookings:loadBookings,pitches:loadPitches,pricing:loadPricing,services:async()=>{},invoices:loadInvoices,reports:loadReports,recurring:loadRecurring,customers:loadCustomers};
  await run(loaders[section.value]);
}
watch(section, () => { detail.value=null; bookingQr.value=null; load(); });
const clock = ref(Date.now());
let clockTimer;
onMounted(() => { clockTimer = setInterval(() => { clock.value = Date.now(); }, 1000); load(); });
onUnmounted(() => clearInterval(clockTimer));
async function loadOverview(){
  const [d,b] = await Promise.all([api.get('/admin/dashboard',{params:{date:dashDate.value}}),api.get('/admin/bookings',{params:{date:dashDate.value}})]);
  dashboard.value = d.data; recent.value=rowsOf(b.data).slice(0,8);
}
async function loadBookings(page=1) {
  const params = Object.fromEntries(Object.entries(bookingFilters.value).filter(([,value])=>value));
  const{data}=await api.get('/admin/bookings',{params:{...params,page}});
  adminBookings.value=rowsOf(data);bookingPage.value=data.current_page||page;bookingLast.value=data.last_page||1;
}
async function showBookingQr(b) {
  bookingQr.value = null;
  await run(async () => {
    const { data } = await api.get(`/bookings/${b.id}/vietqr`);
    if (Number(data.amount_vnd) > 0 && data.qr_image_url) {
      bookingQr.value = { ...data, booking_code: b.booking_code };
    } else {
      notice.value = 'Đơn này không còn khoản đặt cọc phải thanh toán.';
    }
  });
}
async function confirmBooking(b){
  if(!confirm(`Xác nhận đơn ${b.booking_code}? Chỉ thao tác khi đã kiểm tra tiền cọc thực tế.`))return;
  await run(async()=>{await api.patch(`/admin/bookings/${b.id}/confirm`,{deposit_method:depositMethod.value});await loadBookings(bookingPage.value);},'Đã xác nhận đơn đặt sân và ghi nhận tiền cọc theo nghiệp vụ backend.');
}
async function cancelBooking(b){
  if (!canCancelBooking(b)) { problem.value = "Không thể hủy khi giờ đá đã bắt đầu."; return; }
  const reason=prompt(`Lý do hủy đơn ${b.booking_code}?`,'Khách yêu cầu hủy');if(reason===null)return;
  await run(async()=>{await api.patch(`/admin/bookings/${b.id}/cancel`,{reason});await loadBookings(bookingPage.value);},'Đã xử lý hủy đơn. Kiểm tra các khoản hoàn tiền trong hóa đơn.');
}
async function loadPitches(){const{data}=await api.get('/admin/pitches');pitches.value=rowsOf(data);}
function editPitch(p){pitchEditId.value=p.id;pitchForm.value={name:p.name,type:String(p.type),surface:p.surface||'',description:p.description||'',status:p.status};}
function resetPitch(){pitchEditId.value=null;pitchForm.value={name:'',type:'5',surface:'',description:'',status:'active'};}
async function savePitch(){
  await run(async()=>{
    const body={...pitchForm.value,type:String(pitchForm.value.type)};
    if(pitchEditId.value)await api.put(`/admin/pitches/${pitchEditId.value}`,body);else await api.post('/admin/pitches',body);
    resetPitch();await loadPitches();
  },'Đã lưu thông tin sân.');
}
async function loadPricing(){
  const [pr,pi]=await Promise.all([api.get('/admin/price-rules'),api.get('/admin/pitches')]);
  priceRules.value=rowsOf(pr.data);pitches.value=rowsOf(pi.data);
  if(!priceForm.value.pitch_id&&pitches.value.length)priceForm.value.pitch_id=String(pitches.value[0].id);
}
function editPrice(r){ruleEditId.value=r.id;priceForm.value={pitch_id:String(r.pitch_id),day_type:Number(r.day_type),start_time:String(r.start_time).slice(0,5),end_time:String(r.end_time).slice(0,5),price_per_hour:Number(r.price_per_hour),effective_from:r.effective_from||'',effective_to:r.effective_to||''};}
function resetPrice(){ruleEditId.value=null;priceForm.value={pitch_id:pitches.value.length?String(pitches.value[0].id):'',day_type:0,start_time:'06:00',end_time:'07:00',price_per_hour:200000,effective_from:'',effective_to:''};}
async function savePrice(){await run(async()=>{
  const body={...priceForm.value,pitch_id:Number(priceForm.value.pitch_id),day_type:Number(priceForm.value.day_type),price_per_hour:Number(priceForm.value.price_per_hour),effective_from:priceForm.value.effective_from||null,effective_to:priceForm.value.effective_to||null};
  if(ruleEditId.value)await api.put(`/admin/price-rules/${ruleEditId.value}`,body);else await api.post('/admin/price-rules',body);
  resetPrice();await loadPricing();
},'Đã lưu quy tắc giá.');}
async function deletePrice(r){if(!confirm(`Xóa quy tắc giá #${r.id}?`))return;await run(async()=>{await api.delete(`/admin/price-rules/${r.id}`);await loadPricing();},'Đã xóa quy tắc giá.');}
async function loadInvoices(page=1){const{data}=await api.get('/admin/invoices',{params:{search:invoiceSearch.value,include_cancelled:includeCancelled.value?1:0,page}});invoices.value=rowsOf(data);invoicePage.value=data.current_page||page;invoiceLast.value=data.last_page||1;}
async function showInvoice(row){await run(async()=>{const{data}=await api.get(`/admin/invoices/${row.id}`);detail.value=data;payment.value={kind:'receipt',method:'cash',amount:Number(data.remaining||0),reference:'',idempotency_key:uniqueRequestKey()};});}
async function recordPayment(){if(!detail.value)return;
  await run(async()=>{
    const id=detail.value.invoice.id;
    await api.post(`/admin/invoices/${id}/payments`,{...payment.value,amount:Number(payment.value.amount)});
    const{data}=await api.get(`/admin/invoices/${id}`);detail.value=data;payment.value.idempotency_key=uniqueRequestKey();payment.value.amount=Number(data.remaining||0);await loadInvoices(invoicePage.value);
  },'Đã ghi nhận giao dịch. Chỉ nhập tiền thực tế đã nhận/hoàn.');
}
async function settleBalance(){if(!detail.value||!confirm('Bạn đã thực nhận chính xác số tiền còn lại?'))return;
  await run(async()=>{
    const id=detail.value.invoice.id;
    await api.post(`/admin/invoices/${id}/settle-after-match`,{idempotency_key:payment.value.idempotency_key,method:payment.value.method,amount:Number(detail.value.remaining)});
    const{data}=await api.get(`/admin/invoices/${id}`);detail.value=data;payment.value.idempotency_key=uniqueRequestKey();await loadInvoices(invoicePage.value);
  },'Đã tất toán hóa đơn.');
}
async function loadReports(){
  const{from,to}=apiDateRange(reportMonth.value);
  const [m,r,c]=await Promise.all([api.get('/admin/reports/monthly',{params:{month:reportMonth.value}}),api.get('/admin/reports/revenue',{params:{from,to}}),api.get('/admin/reports/cashflow',{params:{from,to}})]);
  monthly.value=m.data;revenueRows.value=r.data.rows||[];cashRows.value=c.data.rows||[];
}
async function loadRecurring(){const [rec,pi]=await Promise.all([api.get('/admin/recurring-bookings'),api.get('/admin/pitches')]);recurringList.value=rowsOf(rec.data);pitches.value=rowsOf(pi.data);if(!recurringForm.value.pitch_id&&pitches.value.length)recurringForm.value.pitch_id=String(pitches.value[0].id);}
async function createRecurring(){await run(async()=>{await api.post('/admin/recurring-bookings',{...recurringForm.value,pitch_id:Number(recurringForm.value.pitch_id),weeks:Number(recurringForm.value.weeks)});await loadRecurring();},'Đã tạo chuỗi lịch đặt cố định (các đơn chờ xác nhận).');}
async function cancelRecurring(r){if(!confirm(`Hủy các buổi tương lai chưa thanh toán của chuỗi #${r.id}?`))return;await run(async()=>{await api.patch(`/admin/recurring-bookings/${r.id}/cancel-future`,{reason:'Quản lý hủy chuỗi trên giao diện Vue'});await loadRecurring();},'Đã xử lý hủy các buổi tương lai có thể hủy.');}
async function loadCustomers(){const{data}=await api.get('/admin/customers',{params:{search:customerSearch.value}});customers.value=rowsOf(data);}
async function showCustomer(c){await run(async()=>{selectedCustomer.value=c.customer_phone;const{data}=await api.get('/admin/customers/bookings',{params:{phone:c.customer_phone}});customerDetails.value=rowsOf(data);});}
</script>

<template>
  <div class="page-title"><div><h1>Hệ thống quản trị sân bóng</h1><p>Quản lý trực tiếp bằng các REST API của Laravel 12 / Sanctum.</p></div><span class="pill confirmed">Đã đăng nhập quản trị</span></div>
  <div class="section-tabs"><button v-for="[key,label] in tabs" :key="key" :class="{active:section===key}" @click="section=key">{{ label }}</button></div>
  <p v-if="problem" class="message error" role="alert">{{ problem }}</p><p v-if="notice" class="message" role="status">{{ notice }}</p><p v-if="busy" class="message info">Đang làm việc với API...</p>

  <template v-if="section==='overview'"><div class="card"><form class="toolbar" @submit.prevent="run(loadOverview)"><div class="field"><label>Ngày thống kê</label><input v-model="dashDate" type="date"></div><button class="btn">Tải tổng quan</button></form></div>
    <div v-if="dashboard" class="grid grid-4 mb"><div class="kpi"><small>Tổng lịch đặt trong ngày</small><strong>{{ dashboard.bookings_total }}</strong></div><div class="kpi"><small>Đã xác nhận/hoàn tất</small><strong>{{ dashboard.bookings_confirmed_or_completed }}</strong></div><div class="kpi"><small>Doanh thu tiền mặt ghi nhận</small><strong>{{ money(dashboard.revenue_recorded) }}</strong></div><div class="kpi"><small>Tỷ lệ lấp đầy</small><strong>{{ dashboard.occupancy?.occupancy_percent }}%</strong></div></div>
    <div class="grid grid-2"><section class="card"><h2>Công suất theo sân</h2><p class="hint">Giờ hoạt động 06:00–22:00; chỉ tính đơn đã xác nhận/hoàn tất.</p><div v-for="pitch in dashboard?.occupancy?.pitches||[]" :key="pitch.pitch_id" class="mb"><div class="page-title" style="margin-bottom:7px"><b>{{ pitch.pitch_name }}</b><span>{{ pitch.occupancy_percent }}%</span></div><div class="progress"><div :style="{width:Math.min(100,Number(pitch.occupancy_percent))+'%'}"></div></div></div></section>
      <section class="card"><h2>Đơn đặt sân trong ngày</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>Mã</th><th>Sân</th><th>Giờ</th><th>Trạng thái</th></tr></thead><tbody><tr v-for="b in recent" :key="b.id"><td>{{ b.booking_code }}</td><td>{{ b.pitch_name }}</td><td>{{ readableDate(b.start_at) }}</td><td><span class="pill" :class="bookingLifecycle(b, clock)">{{ statusLabel(bookingLifecycle(b, clock)) }}</span></td></tr><tr v-if="!recent.length"><td colspan="4" class="empty">Không có lịch đặt.</td></tr></tbody></table></div></section></div>
  </template>

  <section v-if="section==='bookings'" class="card"><h2>Quản lý lịch đặt sân</h2><form class="toolbar" @submit.prevent="run(()=>loadBookings(1))"><div class="field"><label>Ngày</label><input v-model="bookingFilters.date" type="date"></div><div class="field"><label>Trạng thái đơn (lưu trữ)</label><select v-model="bookingFilters.status"><option value="">Tất cả</option><option value="pending">Chờ</option><option value="confirmed">Đã xác nhận</option><option value="completed">Hoàn tất</option><option value="cancelled">Đã hủy</option></select></div><button class="btn">Lọc lịch đặt</button><router-link class="btn btn-outline" to="/dat-san">Tạo đơn</router-link></form>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Mã đặt sân</th><th>Khách hàng</th><th>Sân</th><th>Thời gian thuê</th><th>Giá trị</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><tr v-for="b in adminBookings" :key="b.id"><td>{{ b.booking_code }}</td><td>{{ b.customer_name }}<div class="tiny muted">{{ b.customer_phone }}</div></td><td>{{ b.pitch_name }}</td><td>{{ bookingDateVN(b.start_at) }}<div class="tiny muted">{{ bookingTimeRange(b.start_at,b.end_at) }}</div></td><td>{{ money(b.total_amount) }}</td><td><span class="pill" :class="bookingLifecycle(b, clock)">{{ statusLabel(bookingLifecycle(b, clock)) }}</span></td><td><div class="actions"><button v-if="b.status==='pending'" class="btn btn-outline small" @click="showBookingQr(b)">Mã cọc</button><button v-if="b.status==='pending'" class="btn small" @click="confirmBooking(b)">Xác nhận</button><button v-if="['pending','confirmed'].includes(b.status) && bookingLifecycle(b, clock)!=='completed'" class="btn btn-danger small" :disabled="busy || !canCancelBooking(b, clock)" :title="!canCancelBooking(b, clock) ? 'Đã đến hoặc qua giờ bắt đầu' : 'Hủy lịch đặt'" @click="cancelBooking(b)">Hủy</button></div></td></tr><tr v-if="!adminBookings.length"><td colspan="7" class="empty">Không có đơn đặt sân.</td></tr></tbody></table></div>
    <div class="form-actions"><button class="btn btn-outline small" :disabled="bookingPage<=1" @click="run(()=>loadBookings(bookingPage-1))">Trang trước</button><span>{{ bookingPage }}/{{ bookingLast }}</span><button class="btn btn-outline small" :disabled="bookingPage>=bookingLast" @click="run(()=>loadBookings(bookingPage+1))">Trang sau</button></div>
    <div v-if="bookingQr" class="summary mt">
      <div class="page-title"><h3>VietQR đặt cọc: {{ bookingQr.booking_code }}</h3><button type="button" class="btn btn-outline small" @click="bookingQr=null">Đóng</button></div>
      <div class="grid grid-2">
        <div><p>Tiền cọc: <b>{{ money(bookingQr.amount_vnd) }}</b></p><p>Chủ tài khoản: <b>{{ bookingQr.account_name }}</b></p><p>Số tài khoản: <b>{{ bookingQr.account_number }}</b></p><p>Nội dung chuyển khoản: <b>{{ bookingQr.transfer_reference }}</b></p><p class="hint">Mã QR chỉ hỗ trợ chuyển khoản. Không tự động xác nhận đã nhận tiền.</p></div>
        <div><img class="qr" :src="bookingQr.qr_image_url" alt="VietQR đặt cọc cho đơn đang chờ xác nhận"></div>
      </div>
    </div>
  </section>

  <template v-if="section==='pitches'"><section class="card"><h2>{{ pitchEditId ? 'Sửa thông tin sân' : 'Thêm sân mới' }}</h2><form class="grid grid-3" @submit.prevent="savePitch"><div class="field"><label>Tên sân</label><input v-model.trim="pitchForm.name" required></div><div class="field"><label>Loại sân</label><select v-model="pitchForm.type"><option value="5">5 người</option><option value="7">7 người</option></select></div><div class="field"><label>Trạng thái</label><select v-model="pitchForm.status"><option value="active">Hoạt động</option><option value="maintenance">Bảo trì</option><option value="inactive">Ngừng</option></select></div><div class="field"><label>Mặt sân</label><input v-model.trim="pitchForm.surface"></div><div class="field"><label>Mô tả</label><input v-model.trim="pitchForm.description"></div><div class="form-actions"><button class="btn" :disabled="busy">{{ pitchEditId ? 'Cập nhật' : 'Thêm sân' }}</button><button v-if="pitchEditId" class="btn btn-outline" type="button" @click="resetPitch">Bỏ chỉnh sửa</button></div></form></section>
    <section class="card"><h2>Danh sách sân</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>ID</th><th>Tên sân</th><th>Loại</th><th>Mặt sân</th><th>Trạng thái</th><th></th></tr></thead><tbody><tr v-for="p in pitches" :key="p.id"><td>{{ p.id }}</td><td>{{ p.name }}</td><td>{{ p.type }} người</td><td>{{ p.surface }}</td><td><span class="pill" :class="p.status">{{ statusLabel(p.status) }}</span></td><td><button class="btn btn-outline small" @click="editPitch(p)">Sửa</button></td></tr></tbody></table></div></section>
  </template>

  <template v-if="section==='pricing'"><section class="card"><h2>{{ ruleEditId ? 'Cập nhật quy tắc giá' : 'Thêm quy tắc giá' }}</h2><form class="grid grid-4" @submit.prevent="savePrice"><div class="field"><label>Sân</label><select v-model="priceForm.pitch_id" required><option v-for="p in pitches" :key="p.id" :value="String(p.id)">{{p.name}}</option></select></div><div class="field"><label>Ngày</label><select v-model.number="priceForm.day_type"><option :value="0">Ngày thường</option><option :value="1">Cuối tuần</option></select></div><div class="field"><label>Giờ bắt đầu</label><input v-model="priceForm.start_time" type="time" required></div><div class="field"><label>Giờ kết thúc</label><input v-model="priceForm.end_time" type="time" required></div><div class="field"><label>Giá / giờ (VND)</label><input v-model.number="priceForm.price_per_hour" type="number" min="0" required></div><div class="field"><label>Áp dụng từ ngày (tùy chọn)</label><input v-model="priceForm.effective_from" type="date"></div><div class="field"><label>Đến ngày (tùy chọn)</label><input v-model="priceForm.effective_to" type="date"></div><div class="form-actions"><button class="btn" :disabled="busy">{{ ruleEditId ? 'Lưu thay đổi' : 'Thêm giá' }}</button><button v-if="ruleEditId" class="btn btn-outline" type="button" @click="resetPrice">Bỏ sửa</button></div></form></section>
    <section class="card"><h2>Bảng giá theo khung giờ</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>Sân</th><th>Loại ngày</th><th>Khung giờ</th><th>Đơn giá / giờ</th><th>Hiệu lực</th><th>Thao tác</th></tr></thead><tbody><tr v-for="r in priceRules" :key="r.id"><td>{{pitches.find(p=>p.id===r.pitch_id)?.name || r.pitch_id}}</td><td>{{ Number(r.day_type)===1 ? 'Cuối tuần' : 'Ngày thường' }}</td><td>{{ String(r.start_time).slice(0,5) }}–{{ String(r.end_time).slice(0,5) }}</td><td>{{money(r.price_per_hour)}}</td><td>{{r.effective_from||'Không giới hạn'}} / {{r.effective_to||'Không giới hạn'}}</td><td><div class="actions"><button class="btn btn-outline small" @click="editPrice(r)">Sửa</button><button class="btn btn-danger small" @click="deletePrice(r)">Xóa</button></div></td></tr></tbody></table></div></section>
  </template>

  <ServiceManagement v-if="section==='services'" />

  <template v-if="section==='invoices'"><section class="card"><h2>Hóa đơn và thanh toán</h2><form class="toolbar" @submit.prevent="run(()=>loadInvoices(1))"><div class="field"><label>Tìm kiếm</label><input v-model.trim="invoiceSearch" placeholder="Mã đơn, tên khách, sân..."></div><label class="actions"><input v-model="includeCancelled" type="checkbox"> Hiện đơn đã hủy</label><button class="btn">Tìm</button></form><div class="table-scroll"><table class="data-table"><thead><tr><th>Mã hóa đơn</th><th>Khách</th><th>Sân</th><th>Ngày sử dụng sân</th><th>Tổng</th><th>Thực thu</th><th>Còn lại</th><th>Trạng thái</th><th></th></tr></thead><tbody><tr v-for="i in invoices" :key="i.id"><td>{{i.invoice_code}}</td><td>{{i.customer_name}}</td><td>{{i.pitch_name}}</td><td><strong>{{bookingDateVN(i.start_at)}}</strong><div class="muted tiny">{{bookingTimeRange(i.start_at, i.end_at)}}</div></td><td>{{money(i.total)}}</td><td>{{money(i.net_paid)}}</td><td>{{money(i.remaining)}}</td><td><span class="pill" :class="i.payment_status">{{invoiceStatusLabel(i.payment_status)}}</span></td><td><button class="btn btn-outline small" @click="showInvoice(i)">Chi tiết</button></td></tr></tbody></table></div><div class="form-actions"><button class="btn btn-outline small" :disabled="invoicePage<=1" @click="run(()=>loadInvoices(invoicePage-1))">Trang trước</button><span>{{invoicePage}}/{{invoiceLast}}</span><button class="btn btn-outline small" :disabled="invoicePage>=invoiceLast" @click="run(()=>loadInvoices(invoicePage+1))">Trang sau</button></div></section>
    <section v-if="detail" class="card"><div class="page-title"><h2>Chi tiết hóa đơn {{ detail.invoice.invoice_code }}</h2><button class="btn btn-outline small" @click="detail=null">Đóng</button></div><p class="mb">Sân: <strong>{{detail.invoice.pitch_name}}</strong> · Ngày sử dụng sân: <strong>{{bookingDateVN(detail.invoice.start_at)}}</strong> · Giờ: <strong>{{bookingTimeRange(detail.invoice.start_at, detail.invoice.end_at)}}</strong> · Trạng thái thanh toán: <span class="pill" :class="detail.invoice.payment_status">{{invoiceStatusLabel(detail.invoice.payment_status)}}</span></p><div class="grid grid-3 mb"><div class="kpi"><small>Tổng hóa đơn</small><strong>{{money(detail.invoice.total)}}</strong></div><div class="kpi"><small>Tiền đã thu ròng</small><strong>{{money(detail.net_paid)}}</strong></div><div class="kpi"><small>Còn thiếu</small><strong>{{money(detail.remaining)}}</strong></div></div><h3>Lịch sử giao dịch</h3><div class="table-scroll"><table class="data-table"><thead><tr><th>Loại</th><th>Hình thức</th><th>Số tiền</th><th>Thời gian</th><th>Tham chiếu</th></tr></thead><tbody><tr v-for="p in detail.payments" :key="p.id"><td>{{p.kind==='refund'?'Hoàn tiền':'Thu tiền'}}</td><td>{{paymentMethodLabel(p.method)}}</td><td>{{money(p.amount)}}</td><td>{{readableDate(p.created_at)}}</td><td>{{p.reference}}</td></tr><tr v-if="!detail.payments.length"><td colspan="5" class="empty">Chưa có giao dịch.</td></tr></tbody></table></div>
      <h3 class="mt">Ghi nhận giao dịch thực tế</h3><p class="hint">Chỉ ghi nhận khi đã thực nhận tiền hoặc đã hoàn tiền; VietQR hiện chưa tự động đối soát ngân hàng.</p><form class="grid grid-4" @submit.prevent="recordPayment"><div class="field"><label>Loại giao dịch</label><select v-model="payment.kind"><option value="receipt">Thu tiền</option><option value="refund">Hoàn tiền</option></select></div><div class="field"><label>Hình thức</label><select v-model="payment.method"><option value="cash">Tiền mặt</option><option value="vietqr">VietQR</option></select></div><div class="field"><label>Số tiền (VND)</label><input v-model.number="payment.amount" type="number" min="1" required></div><div class="field"><label>Mã tham chiếu (nếu có)</label><input v-model.trim="payment.reference"></div><div class="form-actions wide"><button class="btn" :disabled="busy || detail.invoice.booking_status==='cancelled'">Ghi nhận</button><button v-if="Number(detail.remaining)>0 && ['confirmed','completed'].includes(detail.invoice.booking_status) && payment.kind==='receipt'" class="btn btn-outline" type="button" @click="settleBalance">Tất toán {{money(detail.remaining)}}</button></div></form>
    </section>
  </template>

  <template v-if="section==='reports'"><section class="card"><h2>Thống kê doanh thu và tỷ lệ lấp đầy</h2><form class="toolbar" @submit.prevent="run(loadReports)"><div class="field"><label>Tháng báo cáo</label><input v-model="reportMonth" type="month" required></div><button class="btn">Xem thống kê</button></form><div v-if="monthly" class="grid grid-4"><div class="kpi"><small>Số đơn đã xác nhận/hoàn tất</small><strong>{{monthly.bookings_count}}</strong></div><div class="kpi"><small>Số giờ đặt</small><strong>{{monthly.booked_hours}} giờ</strong></div><div class="kpi"><small>Lấp đầy tháng</small><strong>{{monthly.occupancy_percent}}%</strong></div><div class="kpi"><small>Thực thu ròng tháng</small><strong>{{money(monthly.revenue_net)}}</strong></div></div><div v-if="monthly" class="grid grid-2 mt"><div class="summary">Doanh thu sân: <b>{{money(monthly.pitch_revenue)}}</b></div><div class="summary">Doanh thu dịch vụ: <b>{{money(monthly.service_revenue)}}</b></div></div><p v-if="monthly" class="hint mt">{{monthly.capacity_note}} {{monthly.revenue_note}}</p></section>
    <div class="grid grid-2"><section class="card"><h2>Doanh thu theo ngày</h2><div v-for="r in revenueRows" :key="r.date" class="mb"><div class="page-title" style="margin-bottom:6px"><span>{{r.date}} · {{r.transactions}} giao dịch</span><b>{{money(r.revenue)}}</b></div><div class="progress"><div :style="{width:Math.max(0,Number(r.revenue))/revenueMax*100+'%'}"></div></div></div><p v-if="!revenueRows.length" class="empty">Chưa có giao dịch.</p></section>
    <section class="card"><h2>Dòng tiền thu / hoàn</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>Ngày</th><th>Thu</th><th>Hoàn</th><th>Ròng</th></tr></thead><tbody><tr v-for="r in cashRows" :key="r.day"><td>{{r.day}}</td><td>{{money(r.receipts)}}</td><td>{{money(r.refunds)}}</td><td>{{money(r.net_cashflow)}}</td></tr><tr v-if="!cashRows.length"><td colspan="4" class="empty">Chưa có dữ liệu.</td></tr></tbody></table></div></section></div>
  </template>

  <template v-if="section==='recurring'"><section class="card"><h2>Tạo lịch đặt sân cố định hàng tuần</h2><p class="hint">Backend kiểm tra tất cả tuần và tạo toàn bộ hoặc không tạo lượt nào nếu có trùng lịch.</p><form class="grid grid-4" @submit.prevent="createRecurring"><div class="field"><label>Sân</label><select v-model="recurringForm.pitch_id" required><option v-for="p in activePitches" :key="p.id" :value="String(p.id)">{{p.name}}</option></select></div><div class="field"><label>Tên khách</label><input v-model.trim="recurringForm.customer_name" required></div><div class="field"><label>Điện thoại</label><input v-model.trim="recurringForm.customer_phone" required></div><div class="field"><label>Email</label><input v-model.trim="recurringForm.customer_email" type="email"></div><div class="field"><label>Ngày đầu tiên</label><input v-model="recurringForm.first_date" type="date" :min="todayVn()" required></div><div class="field"><label>Giờ bắt đầu</label><input v-model="recurringForm.start_time" type="time" required></div><div class="field"><label>Giờ kết thúc</label><input v-model="recurringForm.end_time" type="time" required></div><div class="field"><label>Số tuần (1–52)</label><input v-model.number="recurringForm.weeks" type="number" min="1" max="52" required></div><div class="form-actions"><button class="btn" :disabled="busy">Tạo lịch cố định</button></div></form></section><section class="card"><h2>Danh sách lịch cố định</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>ID</th><th>Khách</th><th>Sân</th><th>Ngày đầu</th><th>Số tuần</th><th>Giờ</th><th></th></tr></thead><tbody><tr v-for="r in recurringList" :key="r.id"><td>#{{r.id}}</td><td>{{r.customer_name}}</td><td>{{pitches.find(p=>p.id===r.pitch_id)?.name||r.pitch_id}}</td><td>{{r.first_date}}</td><td>{{r.weeks}}</td><td>{{r.start_time}}–{{r.end_time}}</td><td><button class="btn btn-danger small" @click="cancelRecurring(r)">Hủy buổi tương lai</button></td></tr></tbody></table></div></section></template>

  <template v-if="section==='customers'"><section class="card"><h2>Danh sách khách hàng đặt sân</h2><form class="toolbar" @submit.prevent="run(loadCustomers)"><div class="field"><label>Tìm tên / điện thoại</label><input v-model.trim="customerSearch"></div><button class="btn">Tìm khách</button></form><div class="table-scroll"><table class="data-table"><thead><tr><th>Khách</th><th>Điện thoại</th><th>Tổng đơn</th><th>Đã xác nhận</th><th>Tiền ròng</th><th></th></tr></thead><tbody><tr v-for="c in customers" :key="c.customer_phone"><td>{{c.customer_name}}</td><td>{{c.customer_phone}}</td><td>{{c.bookings_total}}</td><td>{{c.confirmed_total}}</td><td>{{money(c.net_paid)}}</td><td><button class="btn btn-outline small" @click="showCustomer(c)">Xem lịch</button></td></tr></tbody></table></div></section><section v-if="selectedCustomer" class="card"><h2>Đơn đặt của {{selectedCustomer}}</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>Mã</th><th>Sân</th><th>Ngày giờ</th><th>Trạng thái</th><th>Thực thu</th></tr></thead><tbody><tr v-for="b in customerDetails" :key="b.id"><td>{{b.booking_code}}</td><td>{{b.pitch_name}}</td><td>{{readableDate(b.start_at)}}</td><td>{{statusLabel(b.status)}}</td><td>{{money(b.net_paid)}}</td></tr></tbody></table></div></section></template>
</template>
