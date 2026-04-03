<div id="import-transport_tracking-form">
    <form action="{{ route('transport_tracking.import') }}" method="post">
        @csrf
        <div class="row">
            <x-forms.input
                class="col-lg-12 col-md-12"
                name="stock_file"
                type="file"
                :label="__('Stock File')"
                required
            />

        </div>


        <x-buttons.save
            container="import-transport_tracking-form"
            onclick="saveForm({ element: this, modal: 'dynamic'})"
        />
    </form>
</div>
