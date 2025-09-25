<?php

namespace App\Traits;

use App\Facades\Utils;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait Translatable
{
    public function store_languages($element, $languages, array $custom_fields = [], bool $slug = false) : array
    {
        try {
            $fields = array_merge($custom_fields, ['label', 'short_description', 'content']);
            foreach (Utils::languages() as $language) {
                $title = null;
                $store = [];
                foreach ($fields as $field) {
                    if ($field === 'title') {
                        $title = $languages[$language->iso_code][$field] ?? '';
                    }
                    $store[$field] = $languages[$language->iso_code][$field] ?? '';
                }
                if ($slug && $title) {
                    $title_slug = Str::slug($title);
                    if ($element->languages()->where('slug', $title_slug)->exists()) {
                        $title_slug = $title_slug . '-' . ($element->languages()->where('slug', $title_slug)->count() + 1);
                    }
                    $store['slug'] = $title_slug;
                }
                if (strlen($store['label']) > 2) {
                    $element->languages()->updateOrCreate([
                        'language_id' => $language->id
                    ], array_merge($store, [
                        'language_id' => $language->id,
                    ]));
                }
            }
            return [];
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return ['error' => $e->getMessage()];
        }

    }

    public function translations($element) : array
    {
        $translations = [];
        foreach (Utils::languages() as $language) {
            $translation = $element->languages()
                ->where('language_id', $language->id)
                ->first();
            if (isset($translation->id) && strlen($translation->label) > 2) {
                $translations[] = "<span class='fi fi-" . $translation->language->iso_code . "'></span>";
            }
        }
        return $translations;
    }


}
