import test from 'node:test';
import assert from 'node:assert/strict';
import { bookingDateVN, bookingTimeRange } from '../../resources/js/vue/lib/utils.js';

test('ngày sử dụng sân hiển thị dd/mm/yyyy từ ngày giờ MySQL', () => {
  assert.equal(bookingDateVN('2026-11-23 20:00:00'), '23/11/2026');
});
test('hỗ trợ datetime ISO của API', () => {
  assert.equal(bookingDateVN('2026-11-24T07:30:00'), '24/11/2026');
});
test('không phụ thuộc vào múi giờ của trình duyệt', () => {
  assert.equal(bookingDateVN('2026-12-31 23:30:00'), '31/12/2026');
});
test('khung giờ hiển thị từ giờ bắt đầu và kết thúc', () => {
  assert.equal(bookingTimeRange('2026-11-23 20:00:00', '2026-11-23 21:00:00'), '20:00–21:00');
});
test('không có dữ liệu thì hiển thị ký hiệu rỗng', () => {
  assert.equal(bookingDateVN(null), '—');
  assert.equal(bookingTimeRange(null, ''), '—');
});
