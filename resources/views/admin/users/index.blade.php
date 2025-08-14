@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-3">مدیریت کاربران</h1>

    @if(session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
    @endif

    <form method="get" class="mb-3" style="display:flex; gap:.5rem;">
        <input type="text" name="q" value="{{ $q }}" placeholder="جست‌وجوی نام/ایمیل" class="form-control" />
        <button class="btn btn-primary" type="submit">جست‌وجو</button>
        @if($q)
            <a href="{{ route('admin.users.index') }}" class="btn">حذف فیلتر</a>
        @endif
    </form>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>نام</th>
                        <th>ایمیل</th>
                        <th>ادمین</th>
                        <th>فعال</th>
                        <th>ایجاد</th>
                        <th>اقدامات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>{{ $u->id }}</td>
                        <td>{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>{{ $u->is_admin ? 'بله' : 'خیر' }}</td>
                        <td>{{ (isset($u->is_active) ? ($u->is_active ? 'بله' : 'خیر') : '—') }}</td>
                        <td>{{ $u->created_at?->format('Y-m-d H:i') }}</td>
                        <td style="display:flex; gap:.5rem;">
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-primary">ویرایش</a>
                            <form action="{{ route('admin.users.destroy', $u) }}" method="post" onsubmit="return confirm('حذف کاربر؟')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">کاربری یافت نشد.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $users->links() }}
    </div>
</div>
@endsection

