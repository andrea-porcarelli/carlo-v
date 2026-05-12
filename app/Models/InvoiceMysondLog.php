<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceMysondLog extends Model
{
    public const OUTCOME_SUCCESS   = 'success';
    public const OUTCOME_ERROR     = 'error';
    public const OUTCOME_EXCEPTION = 'exception';

    protected $fillable = [
        'table_order_invoice_id',
        'operation',
        'outcome',
        'esito',
        'codice',
        'descrizione',
        'request_xml',
        'response_xml',
        'exception_class',
        'exception_message',
        'exception_trace',
        'duration_ms',
    ];

    protected $casts = [
        'esito'       => 'integer',
        'duration_ms' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TableOrderInvoice::class, 'table_order_invoice_id');
    }

    public static function logCreateInvoice(int $invoiceId, array|string|null $result, ?\Throwable $exception = null): self
    {
        if ($exception !== null) {
            return self::create([
                'table_order_invoice_id' => $invoiceId,
                'operation'              => 'createInvoice',
                'outcome'                => self::OUTCOME_EXCEPTION,
                'exception_class'        => get_class($exception),
                'exception_message'      => $exception->getMessage(),
                'exception_trace'        => $exception->getTraceAsString(),
            ]);
        }

        $arr = is_array($result) ? $result : ['response' => 'error', 'message' => (string) $result];
        $isSuccess = ($arr['response'] ?? '') === 'success';

        return self::create([
            'table_order_invoice_id' => $invoiceId,
            'operation'              => 'createInvoice',
            'outcome'                => $isSuccess ? self::OUTCOME_SUCCESS : self::OUTCOME_ERROR,
            'descrizione'            => $isSuccess
                ? 'XML generato (' . ($arr['path'] ?? 'n/a') . ')'
                : ($arr['message'] ?? 'errore sconosciuto'),
        ]);
    }
}
