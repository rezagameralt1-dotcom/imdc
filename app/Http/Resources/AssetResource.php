<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'disk' => $this->disk,
            'path' => $this->path,
            'url' => $this->url,
            'mime' => $this->mime,
            'size' => $this->size,
            'original_name' => $this->original_name,
            'uuid' => $this->uuid,
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}

