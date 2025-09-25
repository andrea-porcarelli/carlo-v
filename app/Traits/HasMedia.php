<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMedia
{
    public function images() : MorphMany
    {
        return $this->morphMany(Media::class, 'entity')
            ->where('media_type', 'images');
    }

    public function preview() : string
    {
        $first = $this->morphMany(Media::class, 'entity')
            ->where('media_type', 'images')
            ->orderBy('position')
            ->first();
        if (isset($first->filename)) {
            return $first->url;
        }
        return asset('storage/not-found.png');
    }

    public function store_images($images, $element) : void
    {
        if (isset($images)) {
            foreach ($images as $image) {
                $_media = Media::find($image);
                if (isset($_media)) {
                    $_media->update(['entity_id' => $element->id]);
                }
            }
        }
    }
}
