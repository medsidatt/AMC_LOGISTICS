<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $newFields = [
        // 1. Etat général
        'cleanliness',
        'visible_damage_check',
        'dump_body_cracks_check',
        // 2. Moteur
        'oil_level',
        'coolant_level',
        'fuel_level_check',
        'engine_noise',
        // 3. Système hydraulique
        'hydraulic_cylinder',
        'hydraulic_oil_leak',
        'dump_lift_function',
        'dump_descent_function',
        'hydraulic_hose',
        // 4. Benne
        'dump_body_condition',
        'dump_body_locking',
        'dump_body_tarp',
        'dump_body_ridelle',
        // 5. Freinage et direction
        'parking_brake',
        // 6. Pneumatique
        'tire_pressure',
        'tire_cuts',
        'spare_tire',
        // 7. Signalisation
        'beacon_light',
        'reverse_alarm',
        // 8. Equipement de sécurité
        'safety_vest',
        'wheel_chocks',
        'passenger_seatbelt',
        // 9. Cabine
        'dashboard_indicators',
        'wipers',
    ];

    public function up(): void
    {
        $cond = ['ok', 'needs_attention', 'critical', 'na'];

        Schema::table('inspection_checklists', function (Blueprint $table) use ($cond) {
            foreach ($this->newFields as $field) {
                $table->enum($field, $cond)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inspection_checklists', function (Blueprint $table) {
            $table->dropColumn($this->newFields);
        });
    }
};
