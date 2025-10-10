<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Http\Controllers\Backoffice\Requests\UploadRequest;
use App\Interfaces\MediaInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class UploadController extends BaseController
{
    protected MediaInterface $media;

    public function __construct(MediaInterface $media)
    {
        $this->media = $media;
    }

    public function start(UploadRequest $request) : JsonResponse {
        try {
            $response = [];
            $file = $request->file('file');
            $path = $request->get('path');
            $media_type = $request->get('media_type');
            $entity_id = $request->get('entity_id');
            $entity_type = $request->get('entity_type');
            if (isset($media_type)) {
                $this->clean_media($media_type);
            }
            try {
                if (!is_array($file)) {
                    $response[] = $this->upload($file, $path, $media_type);
                } else {
                    foreach ($file as $item) {
                        $response[] = $this->upload($item, $path, $media_type);
                    }
                }
            } catch (\Exception $e) {
                return $this->exception($e, $request);
            }
            if (isset($entity_id) && isset($entity_type)) {
                foreach ($response as $item) {
                    $this->media->store([
                        'entity_id' => $entity_id,
                        'entity_type' => $entity_type,
                        'media_type' => $item['media_type'],
                        'filename' => $item['basename'],
                        'folder' => $item['folder'],
                        'extension' => $item['extension'],
                        'mime_type' => $item['mime_type'],
                        'size' => $item['size'],
                    ]);
                }

            }
            return response()->json($response);
        } catch (\Exception $e) {
            return $this->exception($e, $request);
        }
    }

    public function delete(int $id)
    {
        try {
            $media = $this->media->find($id);
            if (isset($media->id)) {
                $media->delete();
                return $this->success();
            }
        } catch (\Exception $e) {
            return $this->exception($e);
        }
    }

    private function upload($file, $path, $media_type = null) : array {
        $fileInfo = $file->getClientOriginalName();
        $extension = pathinfo($fileInfo, PATHINFO_EXTENSION);
        if (str_contains($path, 'private')){
            $upload_in = storage_path() . '/app/private/' . str_replace('/private', '', $path);
        } else {
            $upload_in = storage_path() . '/app/public/' . $path;
        }
        $original_name = Str::slug(str_replace('.'. $extension, '', $file->getClientOriginalName())) . '.' . $extension;
        if (str_contains($path, 'private')){
            $url = null;
            $file_name = $original_name;
        } else {
            $file_name = self::get_file_name();
            $url = asset('/storage/' . $path . '/' . $file_name . '.' . $extension);
        }
        $moved = $file->move($upload_in, $file_name . '.' . $extension);
        return [
            'url' => $url,
            'name' => $original_name,
            'file_name' => $file_name,
            'extension' => $extension,
            'mime_type' => $file->getClientMimeType(),
            'size' => $moved->getSize(),
            'basename' => $file_name . '.' . $extension,
            'folder' => $path,
            'media_type' => $media_type
        ];
    }

    private function get_file_name() : string {

        return substr(time(), 0, 5) . '_' . strtolower(substr(md5(rand(0, 9999999)), 0, 5));
    }

    private function clean_media($media_type) : void
    {
        $orphans = $this->media->builder()
            ->where('media_type', $media_type)
            ->whereDoesntHave('entity')
            ->get();
        if ($orphans->count() > 0) {
            foreach ($orphans as $orphan) {
                unlink('storage/' . $orphan->folder . '/' . $orphan->filename);
                $orphan->delete();
            }
        }
    }
}
