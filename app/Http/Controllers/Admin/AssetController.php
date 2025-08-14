<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $assets = Asset::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('original_name', 'like', "%$q%")
                      ->orWhere('mime', 'like', "%$q%");
                });
            })
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        return view('admin.assets.index', compact('assets', 'q'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'files.*' => ['required','file','mimes:jpg,jpeg,png,webp,pdf','max:5120'],
        ]);

        $saved = 0;
        foreach ((array) $request->file('files', []) as $file) {
            $dir = 'uploads/'.date('Y/m');
            $path = $file->store($dir, 'public');

            $mime = $file->getClientMimeType();
            $size = $file->getSize();
            $w = null; $h = null;
            if (str_starts_with((string) $mime, 'image/')) {
                try {
                    [$w, $h] = getimagesize($file->getRealPath());
                } catch (\Throwable $e) { /* ignore */ }
            }

            Asset::create([
                'user_id' => $request->user()->id ?? null,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $mime,
                'size' => $size,
                'width' => $w,
                'height' => $h,
                'alt' => null,
            ]);
            $saved++;
        }

        return redirect()->route('admin.assets.index')->with('ok', $saved.' فایل ذخیره شد.');
    }

    public function destroy(Asset $asset)
    {
        if ($asset->disk && $asset->path) {
            try { Storage::disk($asset->disk)->delete($asset->path); } catch (\Throwable $e) {}
        }
        $asset->delete();
        return back()->with('ok','فایل حذف شد.');
    }
}

