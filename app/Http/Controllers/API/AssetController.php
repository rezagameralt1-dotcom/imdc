<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\AssetResource;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssetController extends ApiController
{
    public function index()
    {
        return AssetResource::collection(Asset::latest()->paginate(50));
    }

    public function show(Asset $asset)
    {
        return new AssetResource($asset);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:51200', // 50MB
        ]);
        $file = $validated['file'];
        $path = $file->store('uploads/'.date('Y/m/d'), 'public');
        $asset = Asset::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uuid' => (string) Str::uuid(),
        ]);

        return new AssetResource($asset);
    }
}
