<div class="row">
    <form class="create-default-entity">
        @include('backoffice.components.form.input',['type' => 'hidden', 'name' => 'type', 'field' => true, 'value' => $type])
        @if ($type == 'delivery-note')
            @include('backoffice.components.form.input',['type' => 'text', 'name' => 'delivery_code', 'label' => 'Codice Bolla di consegna', 'col' => 6])
            @include('backoffice.components.form.input',['type' => 'text', 'name' => 'invoice_code', 'label' => 'Fattura', 'col' => 6])
            @include('backoffice.components.form.input',['type' => 'text', 'name' => 'pieces', 'label' => 'Pezzi totali inviati', 'col' => 6])
            @include('backoffice.components.form.input',['type' => 'text', 'name' => 'delivery_at', 'label' => 'Data di consegna', 'col' => 6, 'readonly' => true])
            @include('backoffice.components.form.input',['type' => 'number', 'name' => 'discount', 'label' => 'Sconto in fattura / bolla', 'col' => 6 ])
            @include('backoffice.components.form.input',['type' => 'number', 'name' => 'shipping', 'label' => 'Costo di spedizione', 'col' => 6])
            @include('backoffice.components.form.input',['type' => 'hidden', 'name' => 'supplier_id', 'field' => true, 'value' => $supplier_id])
            @include('backoffice.components.form.input',['type' => 'hidden', 'name' => 'brand_id', 'field' => true, 'value' => $brand_id])
            @include('backoffice.components.form.input',['type' => 'hidden', 'name' => 'supplier_order_id', 'field' => true, 'value' => $supplier_order_id])
        @else
            @if ($type == 'sub-category')
                @include('backoffice.components.form.select',['col' => '12', 'label' => 'Sotto categoria di ', 'name' => 'category_id', 'options' => Utils::map_collection($categories)])
            @endif
            @include('backoffice.components.form.input',['type' => 'text', 'name' => 'label', 'label' => 'Dai un nome a questo nuovo elemento', 'col' => 12])

            @if ($type == 'color')
                @include('backoffice.components.form.input',['name' => 'color_code', 'label' => 'Seleziona il colore', 'col' => 12])
                @include('backoffice.components.form.input',['type' => 'hidden', 'name' => 'row_id', 'field' => true, 'value' => $row_id])
            @endif
        @endif
        <div class="col-xs-12 m-t-lg">
            @include('backoffice.components.form.button', ['type' => 'button', 'field' => true, 'col' => 12, 'class' => 'btn-store-default-entity', 'label' => 'Crea'])
        </div>
    </form>
</div>
