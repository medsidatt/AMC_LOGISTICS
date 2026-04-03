<div class="row mt-1">
    <x-dashboard.kpi title="{{ __('Poids transporté') }}" :value="$totalTransported['amount']" :unit="$totalTransported['unit']" color="info" />
    <x-dashboard.kpi title="{{ __('Poids reçu') }}" :value="$totalReceived['amount']" :unit="$totalReceived['unit']" :percentage="$totalReceived['percentage']" color="warning" />
    <x-dashboard.kpi title="{{ __('Poids perdus') }}" :value="$totalDifference['amount']" :unit="$totalDifference['unit']" :percentage="$totalDifference['percentage']" color="danger" />
    <x-dashboard.kpi title="{{ __('Poids anomalies') }}" :value="$totalPoidsAnomalies['amount']" :unit="$totalPoidsAnomalies['unit']" :percentage="$totalPoidsAnomalies['percentage']" color="danger" />
</div>

<div class="row mt-2">
    <x-dashboard.kpi title="{{ __('Rotations anomalies') }}" :value="$totalRotationsAnomalies['amount']" :percentage="$totalRotationsAnomalies['percentage']" color="info" />
    <x-dashboard.kpi title="{{ __('Rotations normales') }}" :value="$totalRotationsNormal['amount']" :percentage="$totalRotationsNormal['percentage']" color="warning" />
    <x-dashboard.kpi title="{{ __('Rotations perdues') }}" :value="$totalRotationsPerdues['amount']" :percentage="$totalRotationsPerdues['percentage']" color="danger" />
</div>
