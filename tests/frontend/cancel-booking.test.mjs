import test from 'node:test';
import assert from 'node:assert/strict';
import { canCancelBooking } from '../../resources/js/vue/lib/utils.js';
const start = Date.parse('2026-11-24T20:00:00+07:00');
const b = {status:'confirmed',start_at:'2026-11-24 20:00:00'};
for (const [name,booking,now,expected] of [
 ['before',b,start-1,true],['exact',b,start,false],['after',b,start+1,false],
 ['pending',{...b,status:'pending'},start-1,true],['completed',{...b,status:'completed'},start-1,false],
 ['cancelled',{...b,status:'cancelled'},start-1,false],['missing',{...b,start_at:null},start-1,false]
]) test(name,()=>assert.equal(canCancelBooking(booking,now),expected));
