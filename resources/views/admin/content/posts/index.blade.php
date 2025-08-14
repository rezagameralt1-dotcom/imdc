@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-3">پست‌ها</h1>

    @if(session('ok'))
        <div class="alert alert-success">{{ session('ok') }}</div>
    @endif

    <form method="get" class="mb-3" style="display:flex; gap:.5rem; flex-wrap:wrap;">
        <input type="text" name="q" value="{{ $q }}" placeholder="جست‌وجوی عنوان/خلاصه" class="form-control" />
        <select name="status" class="form-select">
            <option value="">همه وضعیت‌ها</option>
            @foreach(['draft'=>'پیش‌نویس','published'=>'منتشرشده','archived'=>'بایگانی'] as $k=>$v)
                <option value="{{ $k }}" @selected($status===$k)>{{ $v }}</option>
            @endforeach
        </select>
        <a href="{{ route('admin.content.posts.create') }}" class="btn btn-primary">+ پست جدید</a>
        <button class="btn" type="submit">اعمال فیلتر</button>
        @if($q || $status)
            <a href="{{ route('admin.content.posts.index') }}" class="btn">حذف فیلتر</a>
        @endif
    </form>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>عنوان</th>
                        <th>نویسنده</th>
                        <th>وضعیت</th>
                        <th>انتشار</th>
                        <th>اقدامات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($posts as $p)
                    <tr>
                        <td>{{ $p->id }}</td>
                        <td>{{ $p->title }}</td>
                        <td>{{ $p->author?->name }}</td>
                        <td>{{ ['draft'=>'پیش‌نویس','published'=>'منتشر','archived'=>'بایگانی'][$p->status] ?? $p->status }}</td>
                        <td>{{ $p->published_at?->format('Y-m-d H:i') }}</td>
                        <td style="display:flex; gap:.5rem;">
                            <a href="{{ route('admin.content.posts.edit', $p) }}" class="btn btn-sm btn-primary">ویرایش</a>
                            <form action="{{ route('admin.content.posts.destroy', $p) }}" method="post" onsubmit="return confirm('حذف پست؟')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">پستی یافت نشد.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $posts->links() }}
    </div>
</div>
@endsection

