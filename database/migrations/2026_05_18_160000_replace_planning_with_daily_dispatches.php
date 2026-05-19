<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Replace the over-engineered driver↔truck long-term assignments with a
 * focused daily dispatch table: "for date D, these drivers have a rotation,
 * optionally with this truck". One row per (driver, date) thanks to the
 * unique constraint. WhatsApp notification tracked via notified_at.
 */
return new class extends Migration
{
    private array $oldPermissions = [
        'driver-truck-assignment-list',
        'driver-truck-assignment-create',
        'driver-truck-assignment-edit',
        'driver-truck-assignment-delete',
    ];

    private array $newPermissions = [
        'daily-dispatch-list',
        'daily-dispatch-edit',
    ];

    public function up(): void
    {
        // ── 1. Drop the previous experiment ──
        Schema::dropIfExists('driver_truck_assignments');

        // ── 2. Create the focused daily dispatch table ──
        if (! Schema::hasTable('daily_dispatches')) {
            Schema::create('daily_dispatches', function (Blueprint $table) {
                $table->id();
                $table->date('dispatch_date');
                $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
                $table->foreignId('truck_id')->nullable()->constrained()->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['driver_id', 'dispatch_date']);
                $table->index(['dispatch_date', 'truck_id']);
            });
        }

        // ── 3. Swap permissions ──
        foreach (['Logistics Responsible', 'Admin', 'Super Admin', 'HSE Agent'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) {
                $role->revokePermissionTo($this->oldPermissions);
            }
        }

        foreach ($this->oldPermissions as $name) {
            $perm = Permission::where('name', $name)->where('guard_name', 'web')->first();
            $perm?->delete();
        }

        foreach ($this->newPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $logistics = Role::where('name', 'Logistics Responsible')->where('guard_name', 'web')->first();
        if ($logistics) {
            $logistics->givePermissionTo($this->newPermissions);
        }
        foreach (['Admin', 'Super Admin'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($this->newPermissions);
            }
        }

        // HSE keeps read-only access for ISO audit
        $hse = Role::where('name', 'HSE Agent')->where('guard_name', 'web')->first();
        if ($hse) {
            $hse->givePermissionTo('daily-dispatch-list');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_dispatches');

        foreach (['Logistics Responsible', 'Admin', 'Super Admin', 'HSE Agent'] as $name) {
            $role = Role::where('name', $name)->where('guard_name', 'web')->first();
            if ($role) {
                $role->revokePermissionTo($this->newPermissions);
            }
        }

        foreach ($this->newPermissions as $name) {
            $perm = Permission::where('name', $name)->where('guard_name', 'web')->first();
            $perm?->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
