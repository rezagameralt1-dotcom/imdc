<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;

class HomeController extends Controller
{
    public function index()
    {
        // هر کدوم را دوست داری فعال کن:
        // return view('welcome');      // صفحه ساده شروع
        return view('ui.index');        // دموی UI که الان داری
        // یا اگر صفحه‌ی اصلی اختصاصی داری:
        // return view('site.pages.show', ['slug' => 'home']);
    }
}
