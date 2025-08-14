<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;
use App\Models\Page;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Asset;

class DigitalCityImport extends Command
{
    protected $signature = 'digitalcity:import {zip : Path to export zip file} {--no-assets : Skip importing files/assets}';
    protected $description = 'Import content (pages, posts, categories, tags, assets) from an export ZIP created by digitalcity:export';

    public function handle(): int
    {
        $zipPath = $this->argument('zip');

        if (!file_exists($zipPath)) {
            $this->error("ZIP not found: {$zipPath}");
            return self::FAILURE;
        }

        $tmpDir = storage_path('app/imports/tmp_' . date('Ymd_His') . '_' . Str::random(6));
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        // Extract ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->error("Cannot open ZIP: {$zipPath}");
            return self::FAILURE;
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        $dataDir = $tmpDir . DIRECTORY_SEPARATOR . 'data';
        $filesDir = $tmpDir . DIRECTORY_SEPARATOR . 'files';

        $readJson = function (string $file) use ($dataDir) {
            $path = $dataDir . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($path)) return [];
            $json = file_get_contents($path);
            $data = json_decode($json, true);
            return is_array($data) ? $data : [];
        };

        $this->info('Importing content...');

        DB::beginTransaction();
        try {
            // Categories
            $catMap = []; // slug => id
            foreach ($readJson('categories.json') as $c) {
                $slug = $c['slug'] ?? Str::slug($c['name'] ?? 'cat');
                $cat = Category::query()->firstOrCreate(['slug' => $slug], [
                    'name' => $c['name'] ?? $slug,
                    'description' => $c['description'] ?? null,
                ]);
                $catMap[$slug] = $cat->id;
            }

            // Tags
            $tagMap = []; // slug => id
            foreach ($readJson('tags.json') as $t) {
                $slug = $t['slug'] ?? Str::slug($t['name'] ?? 'tag');
                $tag = Tag::query()->firstOrCreate(['slug' => $slug], [
                    'name' => $t['name'] ?? $slug,
                    'description' => $t['description'] ?? null,
                ]);
                $tagMap[$slug] = $tag->id;
            }

            // Pages
            $pagesCount = 0;
            foreach ($readJson('pages.json') as $p) {
                $slug = $p['slug'] ?? Str::slug($p['title'] ?? 'page');
                Page::query()->updateOrCreate(['slug' => $slug], [
                    'title'        => $p['title'] ?? $slug,
                    'body'         => $p['body'] ?? '',
                    'excerpt'      => $p['excerpt'] ?? null,
                    'status'       => $p['status'] ?? 'published',
                    'published_at' => $p['published_at'] ?? now(),
                ]);
                $pagesCount++;
            }

            // Posts
            $postsCount = 0;
            foreach ($readJson('posts.json') as $p) {
                $slug  = $p['slug'] ?? Str::slug($p['title'] ?? 'post');
                $post  = Post::query()->updateOrCreate(['slug' => $slug], [
                    'title'        => $p['title'] ?? $slug,
                    'body'         => $p['body'] ?? '',
                    'excerpt'      => $p['excerpt'] ?? null,
                    'status'       => $p['status'] ?? 'published',
                    'published_at' => $p['published_at'] ?? now(),
                ]);

                // Relations
                $pcats = $p['categories'] ?? [];
                $ptags = $p['tags'] ?? [];
                $post->categories()->sync(array_values(array_filter(array_map(fn($s) => $catMap[$s] ?? null, $pcats))));
                $post->tags()->sync(array_values(array_filter(array_map(fn($s) => $tagMap[$s] ?? null, $ptags))));

                $postsCount++;
            }

            DB::commit();
            $this->info("Imported: {$pagesCount} pages, {$postsCount} posts, " . count($catMap) . " categories, " . count($tagMap) . " tags.");

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('DB import failed: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        // Assets (optional)
        if (!$this->option('no-assets')) {
            $manifest = $dataDir . DIRECTORY_SEPARATOR . 'assets_manifest.json';
            if (file_exists($manifest)) {
                $list = json_decode(file_get_contents($manifest), true) ?: [];
                $stored = 0;
                foreach ($list as $row) {
                    $rel = $row['relative_path'] ?? null;
                    if (!$rel) continue;
                    $src = $filesDir . DIRECTORY_SEPARATOR . $rel;
                    if (!file_exists($src)) continue;

                    $destDir  = 'uploads/' . ltrim(Str::beforeLast($rel, '/'), '/');
                    $destName = basename($rel);

                    // Ensure directory exists in public disk
                    $contents = file_get_contents($src);
                    Storage::disk('public')->put($destDir . '/' . $destName, $contents);

                    $webPath = $destDir . '/' . $destName;
                    Asset::firstOrCreate(['path' => $webPath], [
                        'original_name' => $row['original_name'] ?? $destName,
                        'mime'          => $row['mime'] ?? null,
                        'size'          => $row['size'] ?? strlen($contents),
                        'user_id'       => $row['user_id'] ?? null,
                    ]);
                    $stored++;
                }
                $this->info("Imported assets: {$stored} files into storage/app/public.");
            } else {
                $this->warn('assets_manifest.json not found – skipped assets.');
            }
        }

        // Cleanup
        try {
            @unlink($tmpDir . '/.DS_Store');
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
            @rmdir($tmpDir);
        } catch (\Throwable $e) {
            // ignore
        }

        $this->info('Import completed.');
        return self::SUCCESS;
    }
}

