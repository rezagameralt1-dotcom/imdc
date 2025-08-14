@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="mb-3">Activity Logs</h1>
    <form method="get" class="mb-3" style="display:flex; gap:.5rem;">
        <input type="text" name="q" value="{{ $q }}" placeholder="جست‌وجوی action" class="form-control" />
        <button class="btn" type="submit">جست‌وجو</button>
    </form>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead><tr><th>#</th><th>User</th><th>Action</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                    @foreach($logs as $l)
                        <tr>
                            <td>{{ $l->id }}</td>
                            <td>{{ $l->user_id }}</td>
                            <td>{{ $l->action }}</td>
                            <td>{{ $l->ip }}</td>
                            <td>{{ $l->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">
        {{ $logs->links() }}
    </div>
</div>
@endsection

