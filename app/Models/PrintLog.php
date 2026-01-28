<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintLog extends Model
{
    protected $fillable = [
        'table_order_id',
        'printer_id',
        'user_id',
        'print_type',
        'operation',
        'pdf_path',
        'print_content',
        'print_data',
        'success',
        'error_message',
    ];

    protected $casts = [
        'print_data' => 'array',
        'success' => 'boolean',
    ];

    /**
     * Get the table order
     */
    public function tableOrder(): BelongsTo
    {
        return $this->belongsTo(TableOrder::class);
    }

    /**
     * Get the printer
     */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    /**
     * Get the user who triggered the print
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get print type label
     */
    public function getPrintTypeLabel(): string
    {
        return match ($this->print_type) {
            'order' => 'Ordine',
            'marcia' => 'Marcia Tavolo',
            'preconto' => 'PreConto',
            'comunica' => 'Comunicazione',
            default => ucfirst($this->print_type),
        };
    }

    /**
     * Get operation label
     */
    public function getOperationLabel(): string
    {
        if (!$this->operation) return '';

        return match ($this->operation) {
            'add' => 'Aggiunta',
            'update' => 'Modifica',
            'remove' => 'Rimozione',
            default => ucfirst($this->operation),
        };
    }

    /**
     * Get full PDF path
     */
    public function getFullPdfPath(): ?string
    {
        if (!$this->pdf_path) return null;
        return storage_path('app/' . $this->pdf_path);
    }

    /**
     * Check if PDF exists
     */
    public function hasPdf(): bool
    {
        $path = $this->getFullPdfPath();
        return $path && file_exists($path);
    }
}
