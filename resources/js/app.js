import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './vue/App.vue';
import BookingPage from './vue/pages/BookingPage.vue';
import AccountPage from './vue/pages/AccountPage.vue';
import MyBookingsPage from './vue/pages/MyBookingsPage.vue';
import FriendlyPage from './vue/pages/FriendlyPage.vue';
import AdminPage from './vue/pages/AdminPage.vue';
import { session, restoreSession } from './vue/lib/session.js';
import '../css/app.css';

const routes = [
    { path: '/', redirect: '/dat-san' },
    { path: '/dat-san', component: BookingPage, meta: { title: 'Đặt sân' } },
    { path: '/tai-khoan', component: AccountPage, meta: { title: 'Tài khoản' } },
    { path: '/don-cua-toi', component: MyBookingsPage, meta: { title: 'Đơn đặt sân của tôi', requiresAuth: true } },
    { path: '/giao-huu', component: FriendlyPage, meta: { title: 'Tìm đối giao hữu' } },
    { path: '/quan-tri', component: AdminPage, meta: { title: 'Quản trị', requiresStaff: true } },
    { path: '/:pathMatch(.*)*', redirect: '/dat-san' },
];
const router = createRouter({ history: createWebHistory('/app/'), routes });
router.beforeEach(async (to) => {
    if (!session.ready) await restoreSession();
    if (to.meta.requiresAuth && !session.user) {
        return { path: '/tai-khoan', query: { next: to.path } };
    }
    if (to.meta.requiresStaff && !['admin', 'staff'].includes(session.user?.role)) {
        return { path: '/tai-khoan', query: { next: '/quan-tri' } };
    }
    document.title = `${to.meta.title || 'Sân bóng mini'} | Sân bóng mini`;
});

createApp(App).use(router).mount('#app');
