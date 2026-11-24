import test from 'node:test';
import assert from 'node:assert/strict';
import { bookingLifecycle } from '../../resources/js/vue/lib/utils.js';
const b={status:'confirmed',start_at:'2026-11-24 17:00:00',end_at:'2026-11-24 18:00:00',payment_status:'unpaid'};
const start=Date.parse('2026-11-24T17:00:00+07:00'),end=start+3600000;
for(const [name,changes,time,expected] of [
 ['before',{},start-1,'confirmed'],['start',{},start,'in_use'],['during paid',{payment_status:'paid'},end-1,'in_use'],['end',{},end,'awaiting_payment'],['deposit',{payment_status:'deposit_paid'},end,'awaiting_payment'],['paid',{payment_status:'paid'},end,'completed'],['cancelled',{status:'cancelled'},end,'cancelled'],['pending',{status:'pending'},end,'pending'],['missing invoice',{payment_status:null},end,'payment_unknown'],['refund',{status:'completed',payment_status:'refunded'},end,'awaiting_payment'],['invalid time',{end_at:null},end,'confirmed']
]) test('lifecycle '+name,()=>assert.equal(bookingLifecycle({...b,...changes},time),expected));
