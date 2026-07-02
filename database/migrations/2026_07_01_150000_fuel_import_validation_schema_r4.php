<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R4 — Schema for the approved Fuel Import Validation architecture (six-concept model).
 * Frozen decisions: [S1] rename edk_fuel_recharges → fuel_card_transactions; [S2] account movements
 * stay in the SAME ledger (distinguished by transaction_type); [S3] transaction_ref globally UNIQUE;
 * [S4] truck existence semantics (UNKNOWN vs INACTIVE) — realised by a nullable truck_id here.
 *
 * Schema only: no models/services/validators/policies (those are R5+). Enum columns follow the fuel
 * domain convention (varchar + app-level PHP-enum cast), so new types/reasons never need an ALTER.
 * truck_id uses nullOnDelete (never cascade) so financial truth is never lost when a truck is removed.
 * All three existing fuel tables are empty, so the rename/reshape carries no data risk.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------- 1) Ledger: edk_fuel_recharges -> fuel_card_transactions -------
        Schema::rename('edk_fuel_recharges', 'fuel_card_transactions');

        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            $t->dropUnique('edk_recharges_txn_truck_unique');
            $t->dropIndex('edk_fuel_transactions_transaction_id_index');
            $t->dropForeign('edk_fuel_transactions_truck_id_foreign');
        });

        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            $t->renameColumn('transaction_id', 'transaction_ref');
        });

        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            $t->decimal('estimated_litres', 12, 2)->nullable()->change();
            $t->decimal('price_per_litre', 8, 2)->nullable()->change();
            $t->unsignedBigInteger('truck_id')->nullable()->change();
        });

        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            // provenance + classification facts (validator proposal is immutable)
            $t->string('source', 20)->after('id');
            $t->string('transaction_type', 24)->after('source');
            $t->string('detected_plate', 32)->nullable()->after('holder_raw');
            // Effective decisions — NULLABLE, NO DEFAULT. The database must never assign a business
            // outcome; ClassificationPolicy (R7+) is the sole decider. NULL = "not yet decided by the app".
            $t->boolean('kpi_eligible')->nullable()->after('price_per_litre');
            $t->string('review_status', 12)->nullable()->after('kpi_eligible');
            // Proposal snapshot (immutable audit) — likewise no DB-invented value.
            $t->json('proposed_technical_findings')->nullable()->after('review_status');
            $t->json('proposed_business_findings')->nullable()->after('proposed_technical_findings');
            $t->boolean('proposed_kpi_eligible')->nullable()->after('proposed_business_findings');
            $t->string('policy_version', 20)->nullable()->after('proposed_kpi_eligible');
            // review outcome (mutable by reviewer only)
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $t->string('review_outcome', 28)->nullable()->after('reviewed_by');
            $t->text('review_note')->nullable()->after('review_outcome');
        });

        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            $t->foreign('truck_id')->references('id')->on('trucks')->nullOnDelete();
            $t->unique('transaction_ref', 'fuel_card_transactions_transaction_ref_unique');
            $t->index(['kpi_eligible', 'occurred_at'], 'fct_kpi_occurred_index');
            $t->index('review_status', 'fct_review_status_index');
            $t->index('source', 'fct_source_index');
            $t->index('transaction_type', 'fct_type_index');
            $t->index('card_number', 'fct_card_number_index');
        });

        // ------- 2) Rejections: edk_import_exceptions -> fuel_import_rejections -------
        Schema::rename('edk_import_exceptions', 'fuel_import_rejections');

        Schema::table('fuel_import_rejections', function (Blueprint $t) {
            $t->dropIndex('edk_import_exceptions_status_index');
            $t->dropIndex('edk_import_exceptions_transaction_id_index');
        });

        Schema::table('fuel_import_rejections', function (Blueprint $t) {
            $t->renameColumn('transaction_id', 'transaction_ref');
            $t->renameColumn('reason', 'reason_summary');
        });

        Schema::table('fuel_import_rejections', function (Blueprint $t) {
            $t->dropColumn('status'); // single status replaced by findings + type
            $t->string('source', 20)->after('fuel_import_batch_id');
            $t->string('transaction_type', 24)->nullable()->after('source');
            $t->json('technical_findings')->nullable()->after('reason_summary');
            $t->string('detected_plate', 32)->nullable()->after('holder_raw');
            // Review requirement is a ClassificationPolicy outcome — NULLABLE, NO DEFAULT (the DB never decides).
            $t->boolean('needs_review')->nullable()->after('detected_driver_id');
        });

        Schema::table('fuel_import_rejections', function (Blueprint $t) {
            $t->index('transaction_ref', 'fir_transaction_ref_index');
            $t->index('needs_review', 'fir_needs_review_index');
        });

        // ------- 3) Review events (append-only reviewer history) -------
        Schema::create('fuel_transaction_review_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fuel_card_transaction_id')->constrained('fuel_card_transactions')->cascadeOnDelete();
            $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('outcome', 28);
            $t->text('note')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamp('created_at')->nullable()->useCurrent(); // append-only: no updated_at
            $t->index(['fuel_card_transaction_id', 'created_at'], 'ftre_txn_created_index');
        });

        // ------- 4) Batches: counters by source/type/finding/decision + policy version -------
        Schema::table('fuel_import_batches', function (Blueprint $t) {
            $t->renameColumn('valid_rows', 'accepted_rows');
            $t->renameColumn('exception_rows', 'rejected_rows');
        });

        Schema::table('fuel_import_batches', function (Blueprint $t) {
            $t->json('source_counts')->nullable()->after('category_counts');
            $t->json('type_counts')->nullable()->after('source_counts');
            $t->json('technical_finding_counts')->nullable()->after('type_counts');
            $t->json('business_finding_counts')->nullable()->after('technical_finding_counts');
            $t->json('decision_counts')->nullable()->after('business_finding_counts');
            $t->string('policy_version', 20)->nullable()->after('decision_counts');
        });
    }

    public function down(): void
    {
        // ------- 4) batches -------
        Schema::table('fuel_import_batches', function (Blueprint $t) {
            $t->dropColumn(['source_counts', 'type_counts', 'technical_finding_counts', 'business_finding_counts', 'decision_counts', 'policy_version']);
        });
        Schema::table('fuel_import_batches', function (Blueprint $t) {
            $t->renameColumn('accepted_rows', 'valid_rows');
            $t->renameColumn('rejected_rows', 'exception_rows');
        });

        // ------- 3) review events -------
        Schema::dropIfExists('fuel_transaction_review_events');

        // ------- 2) rejections -> edk_import_exceptions -------
        Schema::table('fuel_import_rejections', function (Blueprint $t) {
            $t->dropIndex('fir_transaction_ref_index');
            $t->dropIndex('fir_needs_review_index');
            $t->dropColumn(['source', 'transaction_type', 'technical_findings', 'detected_plate', 'needs_review']);
        });
        Schema::table('fuel_import_rejections', function (Blueprint $t) {
            $t->string('status', 20)->default('')->after('fuel_import_batch_id');
        });
        Schema::table('fuel_import_rejections', function (Blueprint $t) {
            $t->renameColumn('transaction_ref', 'transaction_id');
            $t->renameColumn('reason_summary', 'reason');
        });
        Schema::table('fuel_import_rejections', function (Blueprint $t) {
            $t->index('status', 'edk_import_exceptions_status_index');
            $t->index('transaction_id', 'edk_import_exceptions_transaction_id_index');
        });
        Schema::rename('fuel_import_rejections', 'edk_import_exceptions');

        // ------- 1) ledger -> edk_fuel_recharges -------
        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            $t->dropForeign(['reviewed_by']);
            $t->dropForeign(['truck_id']);
            $t->dropUnique('fuel_card_transactions_transaction_ref_unique');
            $t->dropIndex('fct_kpi_occurred_index');
            $t->dropIndex('fct_review_status_index');
            $t->dropIndex('fct_source_index');
            $t->dropIndex('fct_type_index');
            $t->dropIndex('fct_card_number_index');
        });
        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            $t->dropColumn([
                'source', 'transaction_type', 'detected_plate', 'kpi_eligible', 'review_status',
                'proposed_technical_findings', 'proposed_business_findings', 'proposed_kpi_eligible',
                'policy_version', 'reviewed_at', 'reviewed_by', 'review_outcome', 'review_note',
            ]);
        });
        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            $t->decimal('estimated_litres', 12, 2)->nullable(false)->change();
            $t->decimal('price_per_litre', 8, 2)->nullable(false)->change();
            $t->unsignedBigInteger('truck_id')->nullable(false)->change();
        });
        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            $t->renameColumn('transaction_ref', 'transaction_id');
        });
        Schema::table('fuel_card_transactions', function (Blueprint $t) {
            // Restore the exact original FK name so a subsequent re-migrate (down → up) can drop it.
            $t->foreign('truck_id', 'edk_fuel_transactions_truck_id_foreign')->references('id')->on('trucks')->cascadeOnDelete();
            $t->unique(['transaction_id', 'truck_id'], 'edk_recharges_txn_truck_unique');
            $t->index('transaction_id', 'edk_fuel_transactions_transaction_id_index');
        });
        Schema::rename('fuel_card_transactions', 'edk_fuel_recharges');
    }
};
