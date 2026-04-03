<x-layouts.main title="{{ __('Checklist quotidien') }}">

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="la la-check-circle mr-1"></i> {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="la la-exclamation-circle mr-1"></i> {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span></button>
    </div>
    @endif

    @php
        $tireOptions = \App\Models\DailyChecklist::TIRE_OPTIONS;
        $brakeOptions = \App\Models\DailyChecklist::BRAKE_OPTIONS;
        $lightOptions = \App\Models\DailyChecklist::LIGHT_OPTIONS;
        $fuelOptions = \App\Models\DailyChecklist::FUEL_LEVEL_OPTIONS;
        $oilOptions = \App\Models\DailyChecklist::OIL_LEVEL_OPTIONS;
        $generalOptions = \App\Models\DailyChecklist::GENERAL_CONDITION_OPTIONS;
    @endphp

    <div class="row">
        <div class="col-lg-8">

            @if(isset($todayChecklist) && $todayChecklist)
            <div class="alert alert-success d-flex align-items-center mb-3">
                <i class="la la-check-circle mr-2" style="font-size: 2rem;"></i>
                <div>
                    <strong>{{ __('Checklist soumis') }}</strong>
                    <p class="mb-0 small">{{ \Carbon\Carbon::parse($todayChecklist->created_at)->format('d/m/Y H:i') }}</p>
                </div>
            </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="la la-clipboard-check mr-1"></i> {{ __('Checklist du jour') }}</h5>
                    <span class="badge badge-primary">{{ now()->format('d/m/Y') }}</span>
                </div>
                <div class="card-body">

                    {{-- Driver / Truck info --}}
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="bg-light rounded p-2">
                                <small class="text-muted d-block">{{ __('Conducteur') }}</small>
                                <strong><i class="la la-user mr-1"></i>{{ $driver->name }}</strong>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded p-2">
                                <small class="text-muted d-block">{{ __('Camion') }}</small>
                                <strong><i class="la la-truck mr-1"></i>{{ $truck->matricule }}</strong>
                            </div>
                        </div>
                    </div>

                    @if(!$todayChecklist)
                    <form action="{{ route('drivers.checklist-submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="checklist_date" value="{{ now()->toDateString() }}">

                        {{-- ── Vehicle Condition (dropdowns) ── --}}
                        <h6 class="text-muted mb-2 mt-2"><i class="la la-car mr-1"></i>{{ __('Etat du vehicule') }}</h6>
                        <hr class="mt-0">

                        <div class="row">
                            {{-- Tires --}}
                            <div class="col-6 col-md-4 mb-3">
                                <label for="tire_condition" class="font-weight-bold">
                                    <i class="la la-circle mr-1"></i>{{ __('Pneus') }} <span class="text-danger">*</span>
                                </label>
                                <select name="tire_condition" id="tire_condition" required
                                    class="form-control @error('tire_condition') is-invalid @enderror">
                                    <option value="">{{ __('Choisir...') }}</option>
                                    @foreach($tireOptions as $key => $label)
                                        <option value="{{ $key }}" {{ old('tire_condition') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('tire_condition') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Brakes --}}
                            <div class="col-6 col-md-4 mb-3">
                                <label for="brakes" class="font-weight-bold">
                                    <i class="la la-hand-paper mr-1"></i>{{ __('Freins') }} <span class="text-danger">*</span>
                                </label>
                                <select name="brakes" id="brakes" required
                                    class="form-control @error('brakes') is-invalid @enderror">
                                    <option value="">{{ __('Choisir...') }}</option>
                                    @foreach($brakeOptions as $key => $label)
                                        <option value="{{ $key }}" {{ old('brakes') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('brakes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Lights --}}
                            <div class="col-6 col-md-4 mb-3">
                                <label for="lights" class="font-weight-bold">
                                    <i class="la la-lightbulb mr-1"></i>{{ __('Eclairage') }} <span class="text-danger">*</span>
                                </label>
                                <select name="lights" id="lights" required
                                    class="form-control @error('lights') is-invalid @enderror">
                                    <option value="">{{ __('Choisir...') }}</option>
                                    @foreach($lightOptions as $key => $label)
                                        <option value="{{ $key }}" {{ old('lights') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('lights') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Fuel Level --}}
                            <div class="col-6 col-md-4 mb-3">
                                <label for="fuel_level" class="font-weight-bold">
                                    <i class="la la-gas-pump mr-1"></i>{{ __('Carburant') }} <span class="text-danger">*</span>
                                </label>
                                <select name="fuel_level" id="fuel_level" required
                                    class="form-control @error('fuel_level') is-invalid @enderror">
                                    <option value="">{{ __('Choisir...') }}</option>
                                    @foreach($fuelOptions as $key => $label)
                                        <option value="{{ $key }}" {{ old('fuel_level') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="fuel_refill" id="fuel_refill" value="1" {{ old('fuel_refill') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="fuel_refill">
                                        <i class="la la-gas-pump mr-1"></i>{{ __('Plein effectue') }}
                                    </label>
                                </div>
                                @error('fuel_level') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </div>

                            {{-- Oil Level --}}
                            <div class="col-6 col-md-4 mb-3">
                                <label for="oil_level" class="font-weight-bold">
                                    <i class="la la-oil-can mr-1"></i>{{ __('Huile') }} <span class="text-danger">*</span>
                                </label>
                                <select name="oil_level" id="oil_level" required
                                    class="form-control @error('oil_level') is-invalid @enderror">
                                    <option value="">{{ __('Choisir...') }}</option>
                                    @foreach($oilOptions as $key => $label)
                                        <option value="{{ $key }}" {{ old('oil_level') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('oil_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- General Condition --}}
                            <div class="col-6 col-md-4 mb-3">
                                <label for="general_condition_notes" class="font-weight-bold">
                                    <i class="la la-truck mr-1"></i>{{ __('Etat general') }} <span class="text-danger">*</span>
                                </label>
                                <select name="general_condition_notes" id="general_condition_notes" required
                                    class="form-control @error('general_condition_notes') is-invalid @enderror">
                                    <option value="">{{ __('Choisir...') }}</option>
                                    @foreach($generalOptions as $key => $label)
                                        <option value="{{ $key }}" {{ old('general_condition_notes') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('general_condition_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- Optional notes --}}
                        <div class="mb-3">
                            <label for="notes">{{ __('Remarques (optionnel)') }}</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control"
                                placeholder="{{ __('Observations supplementaires...') }}">{{ old('notes') }}</textarea>
                        </div>

                        {{-- ── Issue Flags ── --}}
                        <h6 class="text-muted mb-2 mt-3"><i class="la la-exclamation-triangle mr-1"></i>{{ __('Signaler un probleme') }}</h6>
                        <hr class="mt-0">

                        @php
                            $issueCategories = [
                                'tires' => ['label' => __('Pneus'), 'icon' => 'la-circle'],
                                'fuel' => ['label' => __('Carburant'), 'icon' => 'la-gas-pump'],
                                'oil' => ['label' => __('Huile'), 'icon' => 'la-oil-can'],
                                'brakes' => ['label' => __('Freins'), 'icon' => 'la-hand-paper'],
                                'lights' => ['label' => __('Eclairage'), 'icon' => 'la-lightbulb'],
                                'general' => ['label' => __('General'), 'icon' => 'la-tools'],
                            ];
                            $oldFlags = old('issue_flags', []);
                            $oldNotes = old('issue_notes', []);
                        @endphp

                        <div class="row">
                            @foreach($issueCategories as $key => $cat)
                            <div class="col-6 col-md-4 mb-2">
                                <div class="border rounded p-2 {{ is_array($oldFlags) && in_array($key, $oldFlags) ? 'border-danger bg-light' : '' }}">
                                    <div class="form-check">
                                        <input class="form-check-input issue-toggle" type="checkbox"
                                            name="issue_flags[]" value="{{ $key }}" id="issue-{{ $key }}"
                                            {{ is_array($oldFlags) && in_array($key, $oldFlags) ? 'checked' : '' }}
                                            data-target="notes-{{ $key }}">
                                        <label class="form-check-label font-weight-bold" for="issue-{{ $key }}">
                                            <i class="la {{ $cat['icon'] }} mr-1"></i>{{ $cat['label'] }}
                                        </label>
                                    </div>
                                    <input type="text" name="issue_notes[{{ $key }}]" id="notes-{{ $key }}"
                                        class="form-control form-control-sm mt-1 {{ is_array($oldFlags) && in_array($key, $oldFlags) ? '' : 'd-none' }}"
                                        value="{{ $oldNotes[$key] ?? '' }}"
                                        placeholder="{{ __('Details...') }}">
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 mt-3">
                            <i class="la la-check mr-1"></i> {{ __('Soumettre') }}
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- History sidebar --}}
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <i class="la la-history mr-1"></i>
                    <strong>{{ __('Historique') }}</strong>
                </div>
                <div class="card-body p-0">
                    @forelse($history as $item)
                    <div class="px-3 py-2 border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>{{ \Carbon\Carbon::parse($item->checklist_date)->format('d/m/Y') }}</strong>
                            @if($item->issues->count() > 0)
                                <span class="badge badge-danger">{{ $item->issues->count() }} {{ __('pb') }}</span>
                            @else
                                <span class="badge badge-success"><i class="la la-check"></i></span>
                            @endif
                        </div>
                        <div class="small text-muted">
                            @php
                                $tireLabel = $tireOptions[$item->tire_condition] ?? $item->tire_condition;
                                $brakeLabel = $brakeOptions[$item->brakes] ?? $item->brakes;
                                $generalLabel = $generalOptions[$item->general_condition_notes] ?? $item->general_condition_notes;
                            @endphp
                            {{ __('Pneus') }}: {{ $tireLabel }}
                            | {{ __('Freins') }}: {{ $brakeLabel }}
                            | {{ __('Etat') }}: {{ $generalLabel }}
                        </div>
                    </div>
                    @empty
                    <x-empty-state icon="la la-clipboard" :message="__('Aucun historique')" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script>
document.querySelectorAll('.issue-toggle').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var notes = document.getElementById(this.dataset.target);
        var wrapper = this.closest('.border');
        if (this.checked) {
            notes.classList.remove('d-none');
            wrapper.classList.add('border-danger', 'bg-light');
            notes.focus();
        } else {
            notes.classList.add('d-none');
            notes.value = '';
            wrapper.classList.remove('border-danger', 'bg-light');
        }
    });
});
</script>
@endpush

</x-layouts.main>
