<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $logs = AdminActivityLog::when($q !== '', function ($qr) use ($q) {
            $qr->where('action', 'like', "%$q%");
        })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.logs.index', compact('logs', 'q'));
    }
}
