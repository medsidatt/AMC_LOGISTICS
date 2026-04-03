<x-layouts.main
    :title="__('Projects')"
    :actions="$actions"
>
    <div class="card">
        <div class="card-header">
           <x-forms.select
               label="{{ __('Entité') }}"
               id="entity_id"
               class="col-md-4 col-12"
               :options="$entities"
                data-filter="entity"
               placeholder="{{ __('global.all') }}"
              />
        </div>
        <div class="card-body">
            <table
                id="project-table"
                class="table table-striped table-bordered w-100"
                data-url="{{ route('projects.index') }}"
                data-column='logo,name,entity_id,start_date,end_date,address,phone,email,actions'
            >
                <thead>
                <tr>
                    <th>#{{ __('Logo') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Entite') }}</th>
                    <th>{{ __('Start Date') }}</th>
                    <th>{{ __('End Date') }}</th>
                    <th>{{ __('Address') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>

                </tbody>
            </table>
        </div>
    </div>
</x-layouts.main>
