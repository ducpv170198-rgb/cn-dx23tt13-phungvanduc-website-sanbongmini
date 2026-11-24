import test from 'node:test';
import assert from 'node:assert/strict';
import { addMinutesSql, canReserve, apiDateRange, money, todayVn } from '../../resources/js/vue/lib/utils.js';

test('cộng 30 phút giữ định dạng SQL', () => {
  assert.equal(addMinutesSql('2026-11-22 06:00:00', 30), '2026-11-22 06:30:00');
});
test('cộng 90 phút từ 19:30', () => {
  assert.equal(addMinutesSql('2026-11-22 19:30:00', 90), '2026-11-22 21:00:00');
});
test('từ chối giờ không thuộc cùng ngày', () => {
  assert.throws(() => addMinutesSql('2026-11-22 23:30:00', 60));
});
test('từ chối chuỗi ngày giờ không hợp lệ', () => {
  assert.throws(() => addMinutesSql('11/22/2026 06:00', 30));
});
test('kiểm tra 90 phút gồm ba ô liên tiếp', () => {
  const slots = [0, 30, 60].map(m => ({ pitch_id: 2, start_at: addMinutesSql('2026-11-22 18:00:00', m), available: true }));
  assert.equal(canReserve(slots, 2, '2026-11-22 18:00:00', 90), true);
  assert.equal(canReserve(slots, 2, '2026-11-22 18:00:00', 120), false);
});
test('chặn trùng lịch/ô không có giá và không khả dụng', () => {
  const slots = [{ pitch_id: 1, start_at: '2026-11-22 19:00:00', available: true }, { pitch_id: 1, start_at: '2026-11-22 19:30:00', available: false }];
  assert.equal(canReserve(slots, 1, '2026-11-22 19:00:00', 60), false);
});
test('không nhầm lẫn sân khác', () => {
  const slots = [{ pitch_id: 1, start_at: '2026-11-22 19:00:00', available: true }, { pitch_id: 2, start_at: '2026-11-22 19:30:00', available: true }];
  assert.equal(canReserve(slots, 1, '2026-11-22 19:00:00', 60), false);
});
test('kiểm tra số ngày tháng nhuận cho báo cáo', () => {
  assert.deepEqual(apiDateRange('2024-02'), {from:'2024-02-01',to:'2024-02-29'});
  assert.deepEqual(apiDateRange('2026-11'), {from:'2026-11-01',to:'2026-11-30'});
});
test('định dạng tiền Việt Nam', () => {
  assert.match(money(500000), /500[.,]000/);
});
test('ngày theo múi giờ Việt Nam', () => {
  assert.match(todayVn(), /^\d{4}-\d{2}-\d{2}$/);
});
