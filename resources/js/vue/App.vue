<script setup>
import { session, logout } from './lib/session.js';
import { useRouter } from 'vue-router';
const router = useRouter();
async function signOut() { await logout(); router.push('/tai-khoan'); }
</script>

<template>
  <div class="app-shell">
    <header class="topbar">
      <div class="container nav-layout">
        <router-link class="brand" to="/dat-san"><span class="brand-mark">⚽</span><span>SÂN BÓNG <b>MINI</b><small>Đặt sân nhanh · Quản lý dễ</small></span></router-link>
        <nav aria-label="Menu chính">
          <router-link to="/dat-san">Đặt sân</router-link>
          <router-link to="/giao-huu">Tìm đối</router-link>
          <router-link to="/don-cua-toi">Đơn của tôi</router-link>
          <router-link v-if="['admin', 'staff'].includes(session.user?.role)" to="/quan-tri">Quản trị</router-link>
          <router-link to="/tai-khoan">{{ session.user ? session.user.name : 'Tài khoản' }}</router-link>
          <button v-if="session.user" type="button" class="btn btn-outline small" @click="signOut">Đăng xuất</button>
        </nav>
      </div>
    </header>
    <main class="container main-container"><router-view /></main>
    <footer class="footer">Sân bóng mini · Vue 3 / Laravel 12 · Dữ liệu từ API thực tế</footer>
  </div>
</template>
