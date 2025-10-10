<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends LogsModel
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    public $fillable = [
        'entity_id',
        'entity_type',
        'filename',
        'extension',
        'mime_type',
        'media_type',
        'folder',
        'size',
    ];

    public function getUrlAttribute() : string
    {
        return asset('storage/' . $this->folder . '/' . $this->filename);
    }

    public function entity() : MorphTo
    {
        return $this->morphTo();
    }

    public function media_types() : array {
        return [
            'fiscal-code' => 'Codice fiscale',
            'identity-card' => "Carta d'identità",
            'documents' => "Documenti",
            'certificate' => "Visura camerale",
            'contract' => "Contratto cartaceo firmato",
        ];
    }

    public function getTypeLabelAttribute() : string {
        return $this->media_types()[$this->media_type] ?? $this->media_type;
    }
}
