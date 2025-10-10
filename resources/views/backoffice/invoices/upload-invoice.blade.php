<div class="row">
    <div class="col-xs-12">
        <p>
            Seleziona la fattura dal tuo computer, o dal tuo dispositivo ( solo formato XML, Sistema di interscambio)<br />
            Il fornitore verrà identificato automaticamente dal sistema in base alla partita IVA ( se non presente verrà creato
        </p>
        <x-upload title="Seleziona la fattura dal tuo PC ( XML )" file_path="/private/invoices" file_type="xml" :entity_type="$entity" class_upload="upload-invoice" />
    </div>
    <div class="col-xs-12">
        <ul class="invoices-imported">

        </ul>
    </div>
</div>
