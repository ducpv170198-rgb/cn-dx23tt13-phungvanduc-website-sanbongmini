<script setup>
import { computed, ref, watch } from 'vue';
import { api, errorText, rowsOf } from '../lib/http.js';
import { session } from '../lib/session.js';
import { todayVn } from '../lib/utils.js';

const matches = ref([]);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const filters = ref({ date: '', pitch_type: '', level: '' });
const matchForm = ref({
  team_name: '', contact_name: '', contact_phone: '',
  match_date: todayVn(), match_time: '19:00', pitch_type: '5',
  level: 'trung_binh', location_note: '', note: '',
});
const invite = ref({ id: null, team_name: '', contact_name: '', contact_phone: '', message: '' });
const ownMatches = ref([]);
const ownPage = ref(1);
const ownLastPage = ref(1);
const selectedOwnId = ref(null);
const ownerData = ref(null);
const sentInvites = ref([]);
const sentPage = ref(1);
const sentLastPage = ref(1);
const personalBusy = ref(false);
const saving = ref(false);

const levelName = { moi_choi: 'Mới chơi', trung_binh: 'Trung bình', kha: 'Khá', tu_do: 'Tự do' };
const statusNames = {
  open: 'Đang tìm đối', matched: 'Đã tìm được đối', cancelled: 'Đã hủy',
  pending: 'Chờ phản hồi', accepted: 'Đã chấp nhận', rejected: 'Đã từ chối',
  withdrawn: 'Đã rút lời mời',
};
const ownIds = computed(() => new Set(ownMatches.value.map(item => Number(item.id))));
const sentIds = computed(() => new Set(sentInvites.value.map(item => Number(item.friendly_match_id))));
const canManage = computed(() => !!session.user && ['customer', 'admin', 'staff'].includes(session.user.role));
const loginTarget = { path: '/tai-khoan', query: { next: '/giao-huu' } };

function friendlyStatus(value) { return statusNames[value] || value; }
function ownPost(match) { return Boolean(match.is_owner) || ownIds.value.has(Number(match.id)); }
function clearFeedback() { error.value = ''; notice.value = ''; }
function openInvite(match) {
  clearFeedback();
  if (!canManage.value || ownPost(match) || sentIds.value.has(Number(match.id))) return;
  invite.value = {
    id: Number(match.id),
    team_name: '',
    contact_name: session.user?.name || '',
    contact_phone: session.user?.phone || '',
    message: '',
  };
}

async function load() {
  loading.value = true;
  try {
    const params = Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value));
    const { data } = await api.get('/friendly-matches', { params });
    matches.value = rowsOf(data);
  } catch (e) {
    error.value = errorText(e);
  } finally {
    loading.value = false;
  }
}
async function loadMyMatches(page = 1) {
  if (!canManage.value) return;
  try {
    const { data } = await api.get('/friendly-matches/mine', { params: { page } });
    ownMatches.value = rowsOf(data);
    ownPage.value = data.current_page || page;
    ownLastPage.value = data.last_page || 1;
  } catch (e) { error.value = errorText(e); }
}
async function loadSent(page = 1) {
  if (!canManage.value) return;
  try {
    const { data } = await api.get('/friendly-match-requests/sent', { params: { page } });
    sentInvites.value = rowsOf(data);
    sentPage.value = data.current_page || page;
    sentLastPage.value = data.last_page || 1;
  } catch (e) { error.value = errorText(e); }
}
async function loadPersonal() {
  if (!canManage.value) return;
  personalBusy.value = true;
  try { await Promise.all([loadMyMatches(), loadSent()]); }
  finally { personalBusy.value = false; }
}
async function createMatch() {
  clearFeedback(); saving.value = true;
  try {
    const { data } = await api.post('/friendly-matches', matchForm.value);
    notice.value = data.message;
    await Promise.all([load(), loadMyMatches()]);
    await inspectOwner(Number(data.id));
  } catch (e) { error.value = errorText(e); }
  finally { saving.value = false; }
}
async function sendInvite() {
  if (!canManage.value || !invite.value.id) return;
  clearFeedback(); saving.value = true;
  try {
    const { id, ...payload } = invite.value;
    const { data } = await api.post(`/friendly-matches/${id}/requests`, payload);
    notice.value = data.message;
    invite.value.id = null;
    await loadSent();
  } catch (e) { error.value = errorText(e); }
  finally { saving.value = false; }
}
async function inspectOwner(matchId) {
  selectedOwnId.value = Number(matchId);
  ownerData.value = null;
  error.value = '';
  try {
    const { data } = await api.post(`/friendly-matches/${selectedOwnId.value}/owner`);
    ownerData.value = data;
  } catch (e) { error.value = errorText(e); }
}
async function decide(requestId, action) {
  clearFeedback(); saving.value = true;
  try {
    const { data } = await api.patch(`/friendly-matches/${selectedOwnId.value}/requests/${requestId}`, { action });
    notice.value = data.message;
    await Promise.all([inspectOwner(selectedOwnId.value), loadMyMatches(ownPage.value), load()]);
  } catch (e) { error.value = errorText(e); }
  finally { saving.value = false; }
}
async function cancelOwn() {
  if (!selectedOwnId.value || !confirm('Hủy tin tìm đối này?')) return;
  clearFeedback(); saving.value = true;
  try {
    const { data } = await api.patch(`/friendly-matches/${selectedOwnId.value}/cancel`);
    notice.value = data.message;
    await Promise.all([inspectOwner(selectedOwnId.value), loadMyMatches(ownPage.value), load()]);
  } catch (e) { error.value = errorText(e); }
  finally { saving.value = false; }
}
async function withdraw(requestId) {
  if (!confirm('Rút lời mời này?')) return;
  clearFeedback(); saving.value = true;
  try {
    const { data } = await api.patch(`/friendly-match-requests/${requestId}/withdraw`);
    notice.value = data.message;
    await loadSent(sentPage.value);
  } catch (e) { error.value = errorText(e); }
  finally { saving.value = false; }
}

// Khi đăng nhập/đăng xuất, tải lại quyền sở hữu và danh sách lời mời theo tài khoản.
watch(() => session.user?.id, async (userId) => {
  ownMatches.value = []; sentInvites.value = [];
  ownerData.value = null; selectedOwnId.value = null; invite.value.id = null;
  await load();
  if (userId && canManage.value) await loadPersonal();
}, { immediate: true });
</script>

<template>
  <div class="hero">
    <h1>Tìm đối giao hữu</h1>
    <p>Đăng tin tìm đối và quản lý lời mời theo từng tài khoản thành viên.</p>
  </div>

  <section class="card">
    <h2>Tìm đội đang cần đối</h2>
    <form class="toolbar" @submit.prevent="load">
      <div class="field"><label>Ngày</label><input v-model="filters.date" type="date" :min="todayVn()"></div>
      <div class="field"><label>Loại sân</label><select v-model="filters.pitch_type"><option value="">Tất cả</option><option value="5">Sân 5</option><option value="7">Sân 7</option></select></div>
      <div class="field"><label>Trình độ</label><select v-model="filters.level"><option value="">Tất cả</option><option v-for="(label, key) in levelName" :key="key" :value="key">{{ label }}</option></select></div>
      <button class="btn" :disabled="loading">Tìm đội</button>
    </form>
    <p v-if="loading" class="message info">Đang tải tin giao hữu...</p>
    <p v-else-if="!matches.length" class="empty">Chưa có tin phù hợp.</p>
    <div v-else class="grid grid-2">
      <div v-for="m in matches" :key="m.id" class="list-card">
        <h3>{{ m.team_name }}</h3>
        <p>📅 {{ m.match_date }} · {{ String(m.match_time).slice(0, 5) }} · Sân {{ m.pitch_type }} · {{ levelName[m.level] }}</p>
        <p>{{ m.location_note || 'Liên hệ đội đăng tin để thống nhất sân' }}</p>
        <p v-if="m.note">{{ m.note }}</p>
        <span v-if="ownPost(m)" class="ownership-label">Tin của bạn · Không thể tự gửi lời mời</span>
        <span v-else-if="sentIds.has(Number(m.id))" class="ownership-label">Bạn đã gửi lời mời</span>
        <router-link v-else-if="!canManage" class="btn btn-outline small" :to="loginTarget">Đăng nhập để gửi lời mời</router-link>
        <button v-else class="btn btn-outline small" type="button" @click="openInvite(m)">Gửi lời mời</button>
      </div>
    </div>
  </section>

  <p v-if="error" class="message error" role="alert">{{ error }}</p>
  <p v-if="notice" class="message" role="status">{{ notice }}</p>

  <section v-if="invite.id && canManage" class="card">
    <h2>Gửi lời mời cho tin #{{ invite.id }}</h2>
    <form class="grid grid-2" @submit.prevent="sendInvite">
      <div class="field"><label>Tên đội</label><input v-model.trim="invite.team_name" maxlength="120" required></div>
      <div class="field"><label>Người liên hệ</label><input v-model.trim="invite.contact_name" maxlength="120" required></div>
      <div class="field"><label>Số điện thoại</label><input v-model.trim="invite.contact_phone" type="tel" maxlength="30" required></div>
      <div class="field"><label>Lời nhắn</label><input v-model.trim="invite.message" maxlength="1000"></div>
      <div class="actions">
        <button class="btn" :disabled="saving">{{ saving ? 'Đang gửi...' : 'Gửi yêu cầu' }}</button>
        <button class="btn btn-outline" type="button" @click="invite.id=null">Đóng</button>
      </div>
    </form>
  </section>

  <template v-if="canManage">
    <div class="grid grid-2">
      <section class="card">
        <h2>Đăng tin tìm đối</h2>
        <form class="grid" @submit.prevent="createMatch">
          <div class="field"><label>Tên đội</label><input v-model.trim="matchForm.team_name" required maxlength="120"></div>
          <div class="grid grid-2"><div class="field"><label>Người liên hệ</label><input v-model.trim="matchForm.contact_name" required maxlength="120"></div><div class="field"><label>Điện thoại</label><input v-model.trim="matchForm.contact_phone" type="tel" maxlength="30" required></div></div>
          <div class="grid grid-2"><div class="field"><label>Ngày đá</label><input v-model="matchForm.match_date" type="date" :min="todayVn()" required></div><div class="field"><label>Giờ đá</label><input v-model="matchForm.match_time" type="time" required></div></div>
          <div class="grid grid-2"><div class="field"><label>Loại sân</label><select v-model="matchForm.pitch_type"><option value="5">5 người</option><option value="7">7 người</option></select></div><div class="field"><label>Trình độ</label><select v-model="matchForm.level"><option v-for="(label, key) in levelName" :key="key" :value="key">{{ label }}</option></select></div></div>
          <div class="field"><label>Địa điểm</label><input v-model.trim="matchForm.location_note" maxlength="180"></div>
          <div class="field"><label>Ghi chú</label><textarea v-model.trim="matchForm.note" maxlength="1000"></textarea></div>
          <button class="btn" :disabled="saving">Đăng tin</button>
        </form>
      </section>

      <section class="card">
        <div class="page-title"><h2>Tin của tôi và lời mời nhận được</h2><button class="btn btn-outline small" type="button" :disabled="personalBusy" @click="loadMyMatches(1)">Làm mới</button></div>
        <p class="hint">Mỗi tài khoản chỉ xem và xử lý lời mời gửi đến tin mình đăng. Không cần nhập ID thủ công.</p>
        <p v-if="!ownMatches.length" class="empty">Bạn chưa đăng tin tìm đối.</p>
        <div v-for="m in ownMatches" :key="m.id" class="list-card">
          <div class="row-title"><b>{{ m.team_name }}</b><span class="hint">{{ friendlyStatus(m.status) }}</span></div>
          <p>{{ m.match_date }} · {{ String(m.match_time).slice(0, 5) }} · Sân {{ m.pitch_type }}</p>
          <div class="actions">
            <button class="btn btn-outline small" type="button" @click="inspectOwner(m.id)">Xem lời mời ({{ m.pending_count }})</button>
          </div>
        </div>
        <div v-if="ownLastPage > 1" class="pagination-row">
          <button class="btn btn-outline small" :disabled="ownPage<=1" @click="loadMyMatches(ownPage-1)">Trước</button>
          <span>{{ ownPage }}/{{ ownLastPage }}</span>
          <button class="btn btn-outline small" :disabled="ownPage>=ownLastPage" @click="loadMyMatches(ownPage+1)">Sau</button>
        </div>
        <div v-if="ownerData" class="invitations-panel">
          <div class="page-title"><h3>Lời mời cho {{ ownerData.match?.team_name }}</h3><button type="button" class="btn btn-outline small" @click="ownerData=null">Đóng</button></div>
          <p>Trạng thái: <strong>{{ friendlyStatus(ownerData.match?.status) }}</strong></p>
          <button v-if="ownerData.match?.status === 'open'" class="btn btn-danger small mb" :disabled="saving" @click="cancelOwn">Hủy tin</button>
          <p v-if="!ownerData.requests?.length" class="empty">Chưa nhận lời mời nào.</p>
          <div v-for="r in ownerData.requests" :key="r.id" class="list-card">
            <b>{{ r.team_name }}</b>
            <p>Liên hệ: {{ r.contact_name }} · {{ r.contact_phone }}</p>
            <p v-if="r.message">{{ r.message }}</p>
            <p>Trạng thái: {{ friendlyStatus(r.status) }}</p>
            <div v-if="r.status === 'pending' && ownerData.match?.status === 'open'" class="actions">
              <button class="btn small" :disabled="saving" @click="decide(r.id, 'accept')">Chấp nhận</button>
              <button class="btn btn-outline small" :disabled="saving" @click="decide(r.id, 'reject')">Từ chối</button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section class="card">
      <div class="page-title"><div><h2>Lời mời tôi đã gửi</h2><p>Theo dõi kết quả và rút lời mời còn chờ phản hồi.</p></div><button class="btn btn-outline small" :disabled="personalBusy" @click="loadSent(1)">Làm mới</button></div>
      <p v-if="!sentInvites.length" class="empty">Bạn chưa gửi lời mời nào.</p>
      <div v-else class="grid grid-2">
        <div v-for="r in sentInvites" :key="r.id" class="list-card">
          <h3>{{ r.host_team }}</h3>
          <p>{{ r.match_date }} · {{ String(r.match_time).slice(0, 5) }} · Đội bạn: {{ r.team_name }}</p>
          <p><strong>{{ friendlyStatus(r.status) }}</strong></p>
          <p v-if="r.status === 'accepted'">Đã ghép đối. Liên hệ chủ tin: {{ r.host_contact_name }} · {{ r.host_contact_phone }}</p>
          <button v-if="r.status === 'pending'" class="btn btn-outline small" type="button" :disabled="saving" @click="withdraw(r.id)">Rút lời mời</button>
        </div>
      </div>
      <div v-if="sentLastPage > 1" class="pagination-row">
        <button class="btn btn-outline small" :disabled="sentPage<=1" @click="loadSent(sentPage-1)">Trước</button>
        <span>{{ sentPage }}/{{ sentLastPage }}</span>
        <button class="btn btn-outline small" :disabled="sentPage>=sentLastPage" @click="loadSent(sentPage+1)">Sau</button>
      </div>
    </section>
  </template>
  <section v-else class="card">
    <p>Đăng nhập thành viên để đăng tin, gửi lời mời và quản lý các lời mời của riêng tài khoản bạn.</p>
    <router-link class="btn" :to="loginTarget">Đăng nhập / Đăng ký</router-link>
  </section>
</template>

<style scoped>
.ownership-label { display: inline-block; margin-top: 0.3rem; font-size: 0.88rem; font-weight: 600; color: #38546c; }
.invitations-panel { border-top: 1px solid #dce6ef; margin-top: 1rem; padding-top: 1rem; }
.invitations-panel h3 { margin: 0; }
.row-title { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.pagination-row { display: flex; gap: 0.75rem; align-items: center; margin-top: 1rem; }
</style>
