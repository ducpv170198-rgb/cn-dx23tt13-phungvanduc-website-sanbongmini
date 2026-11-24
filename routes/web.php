<?php

use Illuminate\Support\Facades\Route;

// Vue SPA và API cùng origin. Chuyển tiếp các URL giao diện cũ.
Route::redirect('/', '/app');
Route::redirect('/account.html', '/app/tai-khoan');
Route::redirect('/admin-connected.html', '/app/quan-tri');
Route::redirect('/customer-connected.html', '/app/dat-san');
Route::redirect('/tim-doi.html', '/app/giao-huu');
Route::view('/app/{path?}', 'vue')->where('path', '.*');
