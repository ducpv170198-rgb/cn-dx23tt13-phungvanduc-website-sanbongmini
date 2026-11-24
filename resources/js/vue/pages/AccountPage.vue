<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { api, errorText } from '../lib/http.js';
import { session, applyLogin, logout } from '../lib/session.js';

const router = useRouter(), route = useRoute();
const mode = ref('login'), busy = ref(false), problem = ref(''), notice = ref('');
const credentials = ref({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
async function submitAuth() {
  busy.value = true; problem.value = ''; notice.value = '';
  try {
    const { data } = await api.post(mode.value === 'register' ? '/auth/register' : '/auth/login', credentials.value);
    applyLogin(data); credentials.value.password = ''; credentials.value.password_confirmation = '';
    notice.value = data.message || 'Đăng nhập thành công.';
    if (route.query.next) return router.push(String(route.query.next));
  } catch (error) { problem.value = errorText(error); }
  finally { busy.value = false; }
}
async function signOut() { await logout(); notice.value = 'Đã đăng xuất.'; }
</script>
<template>
  <div class="page-title"><div><h1>Tài khoản khách hàng</h1><p>Đăng nhập để theo dõi đơn đặt sân và quản lý lịch cá nhân.</p></div></div>
  <p v-if="problem" class="message error" role="alert">{{ problem }}</p><p v-if="notice" class="message">{{ notice }}</p>
  <div v-if="!session.user" class="card" style="max-width:560px;margin:auto">
    <div class="section-tabs"><button type="button" :class="{active:mode==='login'}" @click="mode='login'">Đăng nhập</button><button type="button" :class="{active:mode==='register'}" @click="mode='register'">Đăng ký</button></div>
    <form class="grid" @submit.prevent="submitAuth">
      <div v-if="mode==='register'" class="field"><label>Họ và tên</label><input v-model.trim="credentials.name" required maxlength="120"></div>
      <div class="field"><label>Email</label><input v-model.trim="credentials.email" type="email" required></div>
      <div v-if="mode==='register'" class="field"><label>Số điện thoại</label><input v-model.trim="credentials.phone" type="tel"></div>
      <div class="field"><label>Mật khẩu</label><input v-model="credentials.password" type="password" :minlength="mode==='register'?8:1" required></div>
      <div v-if="mode==='register'" class="field"><label>Nhập lại mật khẩu</label><input v-model="credentials.password_confirmation" type="password" minlength="8" required></div>
      <button class="btn" :disabled="busy">{{ busy ? 'Đang xử lý...' : mode==='login' ? 'Đăng nhập' : 'Tạo tài khoản' }}</button>
    </form>
    <p class="hint mt">Tài khoản quản trị dùng chung endpoint đăng nhập; quyền truy cập trang quản trị do Laravel kiểm soát.</p>
  </div>
  <template v-else><section class="card"><div class="page-title"><div><h2>Xin chào, {{ session.user.name }}</h2><p>Email: {{ session.user.email }} · Vai trò: {{ session.user.role }}</p></div><div class="actions"><router-link to="/giao-huu" class="btn btn-outline">Quản lý lời mời giao hữu</router-link><router-link v-if="['admin','staff'].includes(session.user.role)" to="/quan-tri" class="btn">Đến quản trị</router-link><button class="btn btn-outline" @click="signOut">Đăng xuất</button></div></div></section>
    <section class="card"><div class="page-title"><div><h2>Quản lý đơn đặt sân</h2><p>Xem ngày sử dụng, trạng thái, hóa đơn, tiền cọc và các đơn đã đặt.</p></div><router-link class="btn" to="/don-cua-toi">Mở Đơn của tôi</router-link></div></section>
  </template>
</template>
