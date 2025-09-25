<div class="col-xs-12">
    <div class="row">
        <div class="col-xs-12 m-t-sm">
            <h4>-- GESTIONE SEO --</h4>
        </div>
        @include('backoffice.components.form.input', ['name' => 'seo[' . $lang . '][h1]', 'label' => 'H1', 'col' => 6, 'value' => $seo->h1 ?? ''])
        @include('backoffice.components.form.input', ['name' => 'seo[' . $lang . '][h2]', 'label' => 'H2', 'col' => 6, 'value' => $seo->h2 ?? ''])
        @include('backoffice.components.form.textarea', ['name' => 'seo[' . $lang . '][h1_description]', 'label' => 'Description H1', 'col' => 6, 'value' => $seo->h1_description ?? '', 'class' => 'summernote'])
        @include('backoffice.components.form.textarea', ['name' => 'seo[' . $lang . '][h2_description]', 'label' => 'Description H2', 'col' => 6, 'value' => $seo->h2_description ?? '', 'class' => 'summernote'])
        @include('backoffice.components.form.input', ['name' => 'seo[' . $lang . '][meta_title]', 'label' => 'Meta Title', 'col' => 6, 'value' => $seo->meta_title ?? ''])
        @include('backoffice.components.form.input', ['name' => 'seo[' . $lang . '][og_title]', 'label' => 'Og Title', 'col' => 6, 'value' => $seo->og_title ?? ''])
        @include('backoffice.components.form.textarea', ['name' => 'seo[' . $lang . '][meta_description]', 'label' => 'Meta description', 'col' => 12, 'value' => $seo->meta_description ?? ''])
        @include('backoffice.components.form.input', ['name' => 'seo[' . $lang . '][og_description]', 'label' => 'Og description', 'col' => 12, 'value' => $seo->og_description ?? ''])
        @include('backoffice.components.form.input', ['name' => 'seo[' . $lang . '][meta_keywords]', 'label' => 'Meta Keywords', 'col' => 12, 'value' => $seo->meta_keywords ?? ''])
        @include('backoffice.components.form.input', ['name' => 'seo[' . $lang . '][alt_image]', 'label' => 'Alt Image', 'col' => 12, 'value' => $seo->alt_image ?? ''])
    </div>
</div>
