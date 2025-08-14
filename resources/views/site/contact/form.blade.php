@extends('site.layouts.site')

@section('title', 'Contact')

@section('content')
<div class="max-w-2xl mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Contact Us</h1>

    @include('partials.flash')

    <form action="{{ route('site.contact.send') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block font-medium">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" class="w-full border rounded p-2" required>
            @error('name') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block font-medium">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="w-full border rounded p-2" required>
            @error('email') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block font-medium">Message</label>
            <textarea name="message" rows="6" class="w-full border rounded p-2" required>{{ old('message') }}</textarea>
            @error('message') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>
        <button class="bg-blue-600 text-white px-4 py-2 rounded">Send</button>
    </form>
</div>
@endsection

