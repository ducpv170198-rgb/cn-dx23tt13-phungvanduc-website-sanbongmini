<script setup>
import { computed, onMounted, ref } from 'vue';
import { api, errorText, rowsOf } from '../lib/http.js';
import { money, readableDate } from '../lib/utils.js';

const emptyForm = () => ({ name: '', type: 'drink', unit: 'chai', price: 10000,
  stock: 0, unlimited: false, low_stock_threshold: 10, is_active: true });
const types = { drink: 'Đồ uống', jersey: 'Áo đấu', equipment: 'Dụng cụ', other: 'Khác' };
const actionNames = { opening: 'Tồn đầu', in: 'Nhập kho', out: 'Xuất kho',
  set: 'Kiểm kê', booking: 'Đơn đặt sân', booking_cancel: 'Hoàn khi hủy đơn' };
const filters = ref({ search: '', type: '', status: 'all' });
const services = ref([]), summary = ref({}), page = ref(1), lastPage = ref(1), total = ref(0);
const editingId = ref(null), form = ref(emptyForm());
const working = ref(false), loading = ref(false), problem = ref(''), notice = ref('');
const selectedStock = ref(null), stockForm = ref({ operation: 'in', quantity: 1, reason: '' });
const movements = ref([]), historyService = ref(null), historyPage = ref(1), historyLast = ref(1);
const visibleCount = computed(() => services.value.length);
const stockLabel = s => s.stock === null ? 'Không giới hạn' : Number(s.stock).toLocaleString('vi-VN');
const statusLabel = s => !s.is_active ? 'Ngừng bán' : ({ out: 'Hết hàng', low: 'Sắp hết', ok: 'Còn hàng', unlimited: 'Không giới hạn' }[s.stock_status] || '—');
const statusClass = s => !s.is_active ? 'svc-off' : `svc-${s.stock_status}`;

async function load(pageToLoad = 1) {
  loading.value = true; problem.value = '';
  try {
    const { data } = await api.get('/admin/services', { params: { ...filters.value, page: pageToLoad } });
    services.value = rowsOf(data); summary.value = data.summary || {};
    page.value = data.current_page || pageToLoad;
    lastPage.value = data.last_page || 1;
    total.value = data.total || 0;
  } catch (e) { problem.value = errorText(e); }
  finally { loading.value = false; }
}
function resetForm() { editingId.value = null; form.value = emptyForm(); }
function editService(service) {
  editingId.value = service.id;
  form.value = {
    name: service.name, type: service.type, unit: service.unit, price: Number(service.price),
    stock: service.stock === null ? 0 : Number(service.stock), unlimited: service.stock === null,
    low_stock_threshold: Number(service.low_stock_threshold ?? 10), is_active: Boolean(service.is_active),
  };
  notice.value = ''; problem.value = '';
}
async function saveService() {
  working.value = true; problem.value = ''; notice.value = '';
  try {
    const body = {
      name: form.value.name.trim(), type: form.value.type, unit: form.value.unit.trim(),
      price: Number(form.value.price), low_stock_threshold: Number(form.value.low_stock_threshold),
      is_active: Boolean(form.value.is_active),
    };
    if (editingId.value) {
      await api.put(`/admin/services/${editingId.value}`, body);
      notice.value = 'Đã cập nhật thông tin và trạng thái dịch vụ.';
    } else {
      await api.post('/admin/services', { ...body, stock: form.value.unlimited ? null : Number(form.value.stock) });
      notice.value = 'Đã thêm dịch vụ vào danh mục và ghi nhận tồn đầu.';
    }
    resetForm(); await load(page.value);
  } catch (e) { problem.value = errorText(e); }
  finally { working.value = false; }
}
function openStock(service) {
  selectedStock.value = service;
  stockForm.value = { operation: service.stock === null ? 'set' : 'in', quantity: service.stock === null ? 0 : 1, reason: '' };
  problem.value = ''; notice.value = '';
}
async function saveStock() {
  if (!selectedStock.value) return;
  working.value = true; problem.value = ''; notice.value = '';
  try {
    const id = selectedStock.value.id;
    await api.post(`/admin/services/${id}/stock`, {
      operation: stockForm.value.operation,
      quantity: Number(stockForm.value.quantity),
      reason: stockForm.value.reason.trim(),
    });
    notice.value = 'Đã ghi nhận thay đổi tồn kho và lưu lịch sử thao tác.';
    selectedStock.value = null;
    await load(page.value);
    if (historyService.value?.id === id) await loadMovements(id, 1);
  } catch (e) { problem.value = errorText(e); }
  finally { working.value = false; }
}
async function loadMovements(id, nextPage = 1) {
  problem.value = '';
  try {
    const { data } = await api.get(`/admin/services/${id}/stock-movements`, { params: { page: nextPage } });
    movements.value = rowsOf(data);
    historyPage.value = data.current_page || nextPage;
    historyLast.value = data.last_page || 1;
  } catch (e) { problem.value = errorText(e); }
}
async function showMovements(service) { historyService.value = service; await loadMovements(service.id, 1); }
onMounted(load);
</script>

<template>
  <section class="svc-heading">
    <div><h2>Quản lý dịch vụ & tồn kho</h2><p class="muted">Thêm đồ uống, cho thuê trang phục, nhập – xuất – kiểm kê kho và theo dõi lịch sử.</p></div>
    <button class="btn btn-outline" type="button" :disabled="loading" @click="load(page)">↻ Làm mới</button>
  </section>

  <p v-if="problem" class="message error" role="alert">{{ problem }}</p>
  <p v-if="notice" class="message" role="status">{{ notice }}</p>
  <div class="grid grid-4 mb">
    <div class="kpi"><small>Tổng dịch vụ</small><strong>{{ summary.total_services ?? '—' }}</strong></div>
    <div class="kpi"><small>Đang kinh doanh</small><strong>{{ summary.active_services ?? '—' }}</strong></div>
    <div class="kpi"><small>Sắp hết hàng</small><strong class="svc-warning-text">{{ summary.low_stock ?? '—' }}</strong></div>
    <div class="kpi"><small>Hết hàng</small><strong class="svc-danger-text">{{ summary.out_of_stock ?? '—' }}</strong></div>
  </div>

  <div class="grid grid-2 svc-content">
    <section class="card">
      <h3>{{ editingId ? `Sửa dịch vụ #${editingId}` : 'Thêm dịch vụ mới' }}</h3>
      <p class="hint">Không thay đổi số lượng tồn bằng biểu mẫu này. Hãy dùng chức năng “Nhập / xuất kho” để có lịch sử rõ ràng.</p>
      <form class="grid grid-2" @submit.prevent="saveService">
        <div class="field wide"><label>Tên dịch vụ *</label><input v-model.trim="form.name" maxlength="120" placeholder="Ví dụ: Nước suối 500ml" required /></div>
        <div class="field"><label>Loại *</label><select v-model="form.type" required><option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option></select></div>
        <div class="field"><label>Đơn vị *</label><input v-model.trim="form.unit" maxlength="30" placeholder="chai / lon / bộ" required /></div>
        <div class="field"><label>Đơn giá (VND) *</label><input v-model.number="form.price" type="number" min="0" max="999999999" step="1" required /></div>
        <div class="field"><label>Cảnh báo khi còn ≤ *</label><input v-model.number="form.low_stock_threshold" type="number" min="0" max="100000000" step="1" required /></div>
        <template v-if="!editingId">
          <label class="wide svc-checkbox"><input v-model="form.unlimited" type="checkbox" /> Không theo dõi tồn kho (không giới hạn)</label>
          <div v-if="!form.unlimited" class="field wide"><label>Số lượng ban đầu *</label><input v-model.number="form.stock" type="number" min="0" max="100000000" step="1" required /></div>
        </template>
        <label class="wide svc-checkbox"><input v-model="form.is_active" type="checkbox" /> Đang kinh doanh (hiển thị khi khách đặt sân)</label>
        <div class="form-actions wide"><button class="btn" type="submit" :disabled="working">{{ working ? 'Đang lưu...' : editingId ? 'Lưu thay đổi' : 'Thêm dịch vụ' }}</button><button class="btn btn-outline" type="button" @click="resetForm">Làm mới biểu mẫu</button></div>
      </form>
    </section>
    <section class="card">
      <h3>Hướng dẫn quản lý kho</h3>
      <p>1. Tạo dịch vụ và nhập số lượng ban đầu; dịch vụ phải được bật <b>Đang kinh doanh</b> mới xuất hiện trên trang khách.</p>
      <p>2. Chọn <b>Nhập / xuất</b> để nhập thêm hàng, ghi giảm hàng hỏng hoặc kiểm kê lại tồn kho. Mỗi thao tác đều yêu cầu lý do.</p>
      <p>3. Khi khách đặt sân, hệ thống tự trừ tồn và khi hủy đơn đủ điều kiện sẽ tự hoàn kho.</p>
      <p>4. Chọn <b>Lịch sử kho</b> để truy vết người thao tác, số lượng trước/sau và nguồn phát sinh.</p>
      <div class="summary mt"><small>Giá trị tồn kho (giá bán tham khảo)</small><strong>{{ money(summary.inventory_value || 0) }}</strong><p class="tiny muted">Không phải giá vốn kế toán; chỉ tính dịch vụ có theo dõi số lượng.</p></div>
    </section>
  </div>

  <section class="card svc-list">
    <h3>Danh sách dịch vụ</h3>
    <form class="toolbar" @submit.prevent="load(1)">
      <div class="field"><label>Tìm theo tên</label><input v-model.trim="filters.search" placeholder="Tên dịch vụ..."></div>
      <div class="field"><label>Loại dịch vụ</label><select v-model="filters.type"><option value="">Tất cả</option><option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option></select></div>
      <div class="field"><label>Trạng thái kho</label><select v-model="filters.status"><option value="all">Tất cả</option><option value="active">Đang kinh doanh</option><option value="inactive">Đã ngừng bán</option><option value="low">Sắp hết</option><option value="out">Hết hàng</option></select></div>
      <button class="btn" type="submit" :disabled="loading">Lọc danh sách</button>
    </form>
    <p class="tiny muted">{{ loading ? 'Đang tải dữ liệu...' : `Hiển thị ${visibleCount} / ${total} dịch vụ` }}</p>
    <div class="table-scroll"><table class="data-table svc-table"><thead><tr><th>Dịch vụ</th><th>Giá bán</th><th>Tồn kho</th><th>Cảnh báo</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
      <tbody>
        <tr v-for="s in services" :key="s.id">
          <td><strong>{{ s.name }}</strong><div class="tiny muted">{{ types[s.type] || s.type }} · {{ s.unit }}</div></td>
          <td>{{ money(s.price) }}</td>
          <td><b>{{ stockLabel(s) }}</b></td>
          <td>{{ s.stock === null ? '—' : s.low_stock_threshold }}</td>
          <td><span class="svc-state" :class="statusClass(s)">{{ statusLabel(s) }}</span></td>
          <td><div class="actions"><button type="button" class="btn btn-outline small" @click="editService(s)">Sửa</button><button type="button" class="btn btn-outline small" @click="openStock(s)">Nhập / xuất</button><button type="button" class="btn btn-outline small" @click="showMovements(s)">Lịch sử</button></div></td>
        </tr>
        <tr v-if="!loading && !services.length"><td colspan="6" class="empty">Chưa có dịch vụ phù hợp. Bạn có thể tạo dịch vụ mới ở biểu mẫu phía trên.</td></tr>
      </tbody>
    </table></div>
    <div class="form-actions"><button class="btn btn-outline small" :disabled="loading || page <= 1" @click="load(page-1)">Trang trước</button><span>{{ page }} / {{ lastPage }}</span><button class="btn btn-outline small" :disabled="loading || page >= lastPage" @click="load(page+1)">Trang sau</button></div>
  </section>

  <section v-if="selectedStock" class="card svc-stock-editor">
    <div class="page-title"><div><h3>Nhập / xuất kho: {{ selectedStock.name }}</h3><p>Hiện còn: <b>{{ stockLabel(selectedStock) }}</b> {{ selectedStock.unit }}</p></div><button class="btn btn-outline small" type="button" @click="selectedStock=null">Đóng</button></div>
    <p v-if="selectedStock.stock===null" class="hint">Dịch vụ chưa theo dõi số lượng. Dùng “Kiểm kê tồn thực tế” để bắt đầu quản lý kho.</p>
    <form class="grid grid-3" @submit.prevent="saveStock">
      <div class="field"><label>Thao tác</label><select v-model="stockForm.operation" :disabled="selectedStock.stock===null"><option v-if="selectedStock.stock!==null" value="in">Nhập thêm hàng</option><option v-if="selectedStock.stock!==null" value="out">Xuất / hao hụt</option><option value="set">Kiểm kê tồn thực tế</option></select></div>
      <div class="field"><label>{{stockForm.operation==='set'?'Tồn kho sau kiểm kê':'Số lượng'}} *</label><input v-model.number="stockForm.quantity" type="number" :min="stockForm.operation==='set'?0:1" max="100000000" step="1" required /></div>
      <div class="field"><label>Lý do (bắt buộc) *</label><input v-model.trim="stockForm.reason" minlength="3" maxlength="300" placeholder="Nhập từ nhà cung cấp..." required /></div>
      <div class="form-actions wide"><button class="btn" type="submit" :disabled="working">{{ working ? 'Đang ghi nhận...' : 'Xác nhận cập nhật kho' }}</button></div>
    </form>
  </section>

  <section v-if="historyService" class="card svc-history">
    <div class="page-title"><h3>Lịch sử tồn kho: {{ historyService.name }}</h3><button class="btn btn-outline small" type="button" @click="historyService=null">Đóng</button></div>
    <div class="table-scroll"><table class="data-table"><thead><tr><th>Thời gian</th><th>Nghiệp vụ</th><th>Thay đổi</th><th>Trước → Sau</th><th>Người thực hiện</th><th>Ghi chú</th></tr></thead>
      <tbody><tr v-for="m in movements" :key="m.id"><td>{{ readableDate(m.created_at) }}</td><td>{{ actionNames[m.action] || m.action }}</td><td :class="Number(m.quantity_change)>=0?'svc-plus':'svc-minus'">{{ Number(m.quantity_change)>0?'+':'' }}{{ m.quantity_change }}</td><td>{{ m.stock_before ?? '—' }} → {{ m.stock_after }}</td><td>{{ m.actor_name || (m.booking_id ? 'Đặt sân tự động' : 'Hệ thống') }}</td><td>{{ m.note || '—' }}</td></tr>
      <tr v-if="!movements.length"><td colspan="6" class="empty">Chưa có giao dịch tồn kho kể từ khi bật tính năng ghi lịch sử.</td></tr></tbody></table></div>
    <div class="form-actions"><button class="btn btn-outline small" :disabled="historyPage<=1" @click="loadMovements(historyService.id,historyPage-1)">Trang trước</button><span>{{historyPage}} / {{historyLast}}</span><button class="btn btn-outline small" :disabled="historyPage>=historyLast" @click="loadMovements(historyService.id,historyPage+1)">Trang sau</button></div>
  </section>
</template>
