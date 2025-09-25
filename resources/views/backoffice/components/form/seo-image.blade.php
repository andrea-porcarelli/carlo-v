<div class="col-xs-12">
    <div class="row">
        <div class="col-xs-6 m-t-sm">
            @include('backoffice.components.form.upload', ['name' => 'seo[' . $lang . '][og_image]', 'path' => 'categories', 'class' => 'upload-og-image', 'col' => 6, 'value' => isset($seo->og_image) ? 'categories/' . $seo->og_image : ''])
        </div>
        <div class="col-xs-6 m-t-sm">
            @include('backoffice.components.form.upload', ['name' => 'seo[' . $lang . '][twitter_image]', 'path' => 'categories', 'class' => 'upload-twitter-image', 'col' => 6, 'value' => isset($seo->twitter_image) ? 'categories/' . $seo->twitter_image : ''])
        </div>
    </div>
</div>
