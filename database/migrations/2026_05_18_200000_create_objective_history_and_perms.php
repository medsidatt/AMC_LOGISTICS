<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Audit trail for every change to "objective" fields (fleet defaults,
 * per-truck targets, client demand tonnages). Each row keeps proof of
 * who decided what, when, and the justification (note).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('objective_history_entries')) {
            Schema::create('objective_history_entries', function (Blueprint $table) {
                $table->id();
                $table->string('subject_type');     // e.g. App\Models\FleetSetting, Truck, ClientDemandPlan
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('subject_label')->nullable();  // human-readable: "Camion 6066TTA1", "Demande client X"
                $table->string('field_name');      // target_rotations_per_week, required_tons, default_capacity_tonnage, capacity_tonnage
                $table->string('field_label')->nullable();    // human label for the field
                $table->string('old_value')->nullable();
                $table->string('new_value')->nullable();
                $table->decimal('magnitude', 12, 2)->nullable();  // |new - old| if numeric
                $table->enum('direction', ['increase', 'decrease', 'neutral'])->default('neutral');
                $table->text('note');
                $table->json('context')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('changed_at')->useCurrent();
                $table->timestamps();

                $table->index(['subject_type', 'subject_id']);
                $table->index('changed_at');
                $table->index('field_name');
            });
        }

        foreach (['objective-history-list'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['Logistics Responsible', 'Admin', 'Super Admin'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            $role?->givePermissionTo('objective-history-list');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('objective_history_entries');

        foreach (['Logistics Responsible', 'Admin', 'Super Admin'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            $role?->revokePermissionTo('objective-history-list');
        }
        Permission::where('name', 'objective-history-list')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
