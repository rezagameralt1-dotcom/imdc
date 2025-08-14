@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Site Settings</h1>

    @include('partials.flash')

    <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-6 max-w-2xl">
        @csrf
        @method('PUT')

        <div>
            <label class="block font-medium">Site Title</label>
            <input type="text" name="site_title" value="{{ old('site_title', $settings['site_title'] ?? '') }}" class="w-full border rounded p-2" />
            @error('site_title') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-medium">Tagline</label>
            <input type="text" name="site_tagline" value="{{ old('site_tagline', $settings['site_tagline'] ?? '') }}" class="w-full border rounded p-2" />
            @error('site_tagline') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-medium">Twitter Handle (without @)</label>
            <input type="text" name="twitter_handle" value="{{ old('twitter_handle', $settings['twitter_handle'] ?? '') }}" class="w-full border rounded p-2" />
            @error('twitter_handle') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-medium">Default Meta Image URL</label>
            <input type="url" name="meta_image_url" value="{{ old('meta_image_url', $settings['meta_image_url'] ?? '') }}" class="w-full border rounded p-2" />
            @error('meta_image_url') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block font-medium">Contact Form Receiver Email</label>
            <input type="email" name="contact_to" value="{{ old('contact_to', $settings['contact_to'] ?? config('mail.from.address')) }}" class="w-full border rounded p-2" />
            @error('contact_to') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
            <p class="text-gray-500 text-sm">If empty, falls back to MAIL_FROM_ADDRESS</p>
        </div>

        <div class="flex items-center space-x-2">
            <input type="checkbox" id="maintenance" name="maintenance" value="1" {{ (old('maintenance', $settings['maintenance'] ?? '0') == '1') ? 'checked' : '' }} class="h-4 w-4">
            <label for="maintenance">Enable Maintenance Mode (non-admin users will see 503 page)</label>
        </div>

        <button class="bg-blue-600 text-white px-4 py-2 rounded">Save</button>
    </form>
</div>
@endsection

