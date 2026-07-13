<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set(
            'ditron_tender_contanti',
            1,
            'integer',
            'Codice tender Ditron (T=N) usato per la chiusura scontrino quando il metodo di pagamento è "contanti". Default 1 = Contanti sul setup RT standard.'
        );

        Setting::set(
            'ditron_tender_pos',
            5,
            'integer',
            'Codice tender Ditron (T=N) usato per la chiusura scontrino quando il metodo di pagamento è "pos" (elettronico). Default 5 = Pagamento elettronico sul setup RT standard.'
        );
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'ditron_tender_contanti',
            'ditron_tender_pos',
        ])->delete();
    }
};
