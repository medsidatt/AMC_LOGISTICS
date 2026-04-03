@php
    $folders = [
        [
            'index' => 1,
            'slug' => 'personal',
            'title' => __('employee.information'),
        ],
        [
            'index' => 2,
            'slug' => 'contract',
            'title' => __('employee.contract'),
        ],
        [
            'index' => 3,
            'slug' => 'salary',
            'title' => __('employee.salary'),
        ],
        [
            'index' => 4,
            'slug' => 'leave',
            'title' => __('employee.leave'),
        ],
        [
            'index' => 5,
            'slug' => 'document',
            'title' => __('employee.document'),
        ],
        [
            'index' => 6,
            'slug' => 'explanation-requests',
            'title' => __('employee.explanation_request'),
        ],
    ];
@endphp

<x-layouts.main
    :title="__('My Profile')"
>
    <section>
        {{--<div class="card">
            <div class="card-header">
                <h4 class="card-title">{{ __('Explanation Requests') }}</h4>
            </div>
            <div class="card-content">
                <div class="card-body">
                    <table class="table table-striped table-bordered w-100"
                           data-column="title,date,status,response_status,action"
                           data-url="{{ route('explanation-requests.index', auth()->user()->employee->id) }}"
                    >
                        <thead>
                        <tr>
                            <th>{{ __('employee.explanation_request') }}</th>
                            <th>{{ __('employee.date') }}</th>
                            <th>{{ __('system.status') }}</th>
                            <th>{{ __('system.response_status') }}</th>
                            <th>{{ __('system.actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>--}}
        <div id="accordionWrap" role="tablist" aria-multiselectable="true">
            <div class="card accordion collapse-icon accordion-icon-rotate">
                {{--<a id="heading11" class="card-header info" data-toggle="collapse" href="#accordion11" aria-expanded="true" aria-controls="accordion11">
                    <div class="card-title lead">Accordion Group Item #1</div>
                </a>
                <div id="accordion11" role="tabpanel" data-parent="#accordionWrap1" aria-labelledby="heading11" class="collapse show">
                    <div class="card-content">
                        <div class="card-body">
                            Caramels dessert chocolate cake pastry jujubes bonbon. Jelly wafer jelly beans. Caramels chocolate cake
                            liquorice cake wafer jelly beans croissant apple pie. Oat cake brownie pudding jelly beans. Wafer liquorice
                            chocolate bar chocolate bar liquorice. Tootsie roll gingerbread gingerbread chocolate bar tart chupa chups
                            sugar plum toffee. Carrot cake macaroon sweet danish. Cupcake soufflé toffee marzipan candy canes pie jelly-o.
                            Cotton candy bonbon powder topping carrot cake cookie caramels lemon drops liquorice. Dessert cookie ice cream
                            toffee apple pie.
                        </div>
                    </div>
                </div>
                <a id="heading12" class="card-header info" data-toggle="collapse" href="#accordion12" aria-expanded="false" aria-controls="accordion12">
                    <div class="card-title lead collapsed">Accordion Group Item #2</div>
                </a>
                <div id="accordion12" role="tabpanel" data-parent="#accordionWrap1" aria-labelledby="heading12" class="collapse" aria-expanded="false">
                    <div class="card-content">
                        <div class="card-body">
                            Sugar plum bear claw oat cake chocolate jelly tiramisu dessert pie. Tiramisu macaroon muffin jelly marshmallow
                            cake. Pastry oat cake chupa chups. Caramels marshmallow carrot cake topping donut sesame snaps toffee tootsie
                            roll. Lollipop sweet jelly beans oat cake biscuit pastry chocolate cake. Cupcake chocolate biscuit lemon drops
                            cotton candy marshmallow oat cake donut. Croissant chocolate cake oat cake brownie topping carrot cake jelly
                            beans. Dessert gingerbread marshmallow pudding donut lemon drops cake. Cake topping gummi bears cake.
                        </div>
                    </div>
                </div>
                <a id="heading13" class="card-header info" data-toggle="collapse" href="#accordion13" aria-expanded="false" aria-controls="accordion13">
                    <div class="card-title lead collapsed">Accordion Group Item #3</div>
                </a>
                <div id="accordion13" role="tabpanel" data-parent="#accordionWrap1" aria-labelledby="heading13" class="collapse" aria-expanded="false">
                    <div class="card-content">
                        <div class="card-body">
                            Candy cupcake sugar plum oat cake wafer marzipan jujubes lollipop macaroon. Cake dragée jujubes donut chocolate
                            bar chocolate cake cupcake chocolate topping. Dessert jelly beans toffee muffin tiramisu sesame snaps brownie.
                            Cake halvah pastry soufflé oat cake candy candy canes. Lemon drops gummies gingerbread toffee. Tart jelly candy
                            pastry. Pastry cake jelly beans carrot cake marzipan lollipop muffin. Soufflé jujubes cupcake. Powder danish
                            candy carrot cake pastry. Tart marshmallow caramels cake macaroon gummies lollipop.
                        </div>
                    </div>
                </div>
                --}}

                @foreach($folders as $key => $folder)
                    <a id="heading-{{ $key }}" class="card-header info" data-toggle="collapse" href="#accordion-{{ $key }}" aria-expanded="false" aria-controls="accordion-{{ $key }}">
                        <div class="card-title lead collapsed">
                            {{ $folder['title'] }}
                        </div>
                    </a>
                    <div id="accordion-{{ $key }}" role="tabpanel" data-parent="#accordionWrap" aria-labelledby="heading-{{ $key }}"
                         class="collapse {{ $key == 0 ? 'show' : '' }}" aria-expanded="false">
                        <div class="card-content">
                            <div class="card-body">
                                @includeIf("pages.employees.{$folder['slug']}.index")
                            </div>
                        </div>
                    </div>
                @endforeach

            </div>
        </div>
    </section>
</x-layouts.main>
