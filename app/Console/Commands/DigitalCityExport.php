<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use App\Models\Post;
use App\Models\Page;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Setting;

class DigitalCityExport extends Command
{
    protected $signature = 'digitalcity:export';
    protected $description = 'Export content (pages, posts, categories, tags, settings) + public assets as a ZIP';

    public function handle(): int
    {
        $ts = now()->format('Ymd-His');
        $baseDir = storage_path('app/exports');
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }
        $zipPath = $baseDir . "/digitalcity_export_{$ts}.zip";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->error("Cannot create zip: $zipPath");
            return 1;
        }

        // JSON data
        $data = [
            'meta' => [
                'exported_at' => now()->toAtomString(),
                'app_name' => config('app.name'),
                'app_env' => app()->environment(),
            ],
            'settings' => Setting::query()->get()->toArray(),
            'pages' => Page::query()->orderBy('id')->get()->toArray(),
            'categories' => Category::query()->orderBy('id')->get()->toArray(),
            'tags' => Tag::query()->orderBy('id')->get()->toArray(),
            'posts' => Post::with(['categories:id','tags:id'])->orderBy('id')->get()->toArray(),
        ];
        $zip->addFromString('data/content.json', json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

        // public/storage assets
        $disk = Storage::disk('public');
        foreach ($disk->allFiles() as $file) {
            $zip->addFromString('public_storage/'.$file, $disk->get($file));
        }

        // README
        $readme = "DigitalCity Export\n\nContains content JSON and public storage files.\nImport is manual (seeders/commands not included).\nCreated at: {$ts}\n";
        $zip->addFromString('README.txt', $readme);

        $zip->close();

        $this->info("Exported to: $zipPath");
        return 0;
    }
}

