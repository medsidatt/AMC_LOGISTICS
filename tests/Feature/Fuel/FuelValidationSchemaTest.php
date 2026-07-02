<?php

namespace Tests\Feature\Fuel;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * R4 — asserts the migrated schema for the Fuel Import Validation architecture. Read-only (no DB
 * writes), so it runs against the migrated dev DB without touching data.
 */
class FuelValidationSchemaTest extends TestCase
{
    public function test_ledger_renamed_old_tables_gone(): void
    {
        $this->assertTrue(Schema::hasTable('fuel_card_transactions'));
        $this->assertFalse(Schema::hasTable('edk_fuel_recharges'));
        $this->assertTrue(Schema::hasTable('fuel_import_rejections'));
        $this->assertFalse(Schema::hasTable('edk_import_exceptions'));
        $this->assertTrue(Schema::hasTable('fuel_transaction_review_events'));
    }

    public function test_ledger_has_all_new_columns(): void
    {
        foreach ([
            'source', 'transaction_type', 'transaction_ref', 'detected_plate', 'kpi_eligible', 'review_status',
            'proposed_technical_findings', 'proposed_business_findings', 'proposed_kpi_eligible', 'policy_version',
            'reviewed_at', 'reviewed_by', 'review_outcome', 'review_note',
        ] as $c) {
            $this->assertTrue(Schema::hasColumn('fuel_card_transactions', $c), "fuel_card_transactions missing $c");
        }
    }

    public function test_decision_columns_carry_no_business_default(): void
    {
        // ClassificationPolicy is the ONLY decision owner: the DB must never assign a business
        // outcome. These columns must be nullable AND have no default (NULL = "not yet decided").
        foreach ([
            ['fuel_card_transactions', 'kpi_eligible'],
            ['fuel_card_transactions', 'review_status'],
            ['fuel_card_transactions', 'proposed_kpi_eligible'],
            ['fuel_import_rejections', 'needs_review'],
        ] as [$table, $col]) {
            $this->assertSame('YES', $this->nullable($table, $col), "$table.$col must be nullable");
            $this->assertNull($this->columnDefault($table, $col), "$table.$col must have no business default");
        }
    }

    public function test_business_rule_nullability(): void
    {
        // [S4] a transaction may have no fleet truck; financial fields optional; facts required.
        $this->assertSame('YES', $this->nullable('fuel_card_transactions', 'truck_id'));
        $this->assertSame('YES', $this->nullable('fuel_card_transactions', 'estimated_litres'));
        $this->assertSame('YES', $this->nullable('fuel_card_transactions', 'price_per_litre'));
        $this->assertSame('NO', $this->nullable('fuel_card_transactions', 'source'));
        $this->assertSame('NO', $this->nullable('fuel_card_transactions', 'transaction_type'));
        $this->assertSame('NO', $this->nullable('fuel_card_transactions', 'amount_fcfa'));
    }

    public function test_transaction_ref_is_globally_unique(): void
    {
        // [S3] idempotency
        $this->assertTrue($this->hasUniqueIndex('fuel_card_transactions', 'fuel_card_transactions_transaction_ref_unique'));
    }

    public function test_truck_fk_is_set_null_to_preserve_financial_truth(): void
    {
        $this->assertSame('SET NULL', $this->fkDeleteRule('fuel_card_transactions', 'truck_id'));
        $this->assertSame('SET NULL', $this->fkDeleteRule('fuel_card_transactions', 'reviewed_by'));
    }

    public function test_ledger_query_indexes_present(): void
    {
        foreach (['fct_kpi_occurred_index', 'fct_review_status_index', 'fct_source_index', 'fct_type_index', 'fct_card_number_index'] as $i) {
            $this->assertTrue($this->hasIndex('fuel_card_transactions', $i), "missing index $i");
        }
    }

    public function test_rejections_reshaped(): void
    {
        foreach (['source', 'transaction_type', 'technical_findings', 'reason_summary', 'transaction_ref', 'detected_plate', 'needs_review'] as $c) {
            $this->assertTrue(Schema::hasColumn('fuel_import_rejections', $c), "fuel_import_rejections missing $c");
        }
        $this->assertFalse(Schema::hasColumn('fuel_import_rejections', 'status'), 'single status must be gone');
    }

    public function test_review_events_are_append_only(): void
    {
        foreach (['fuel_card_transaction_id', 'reviewer_id', 'outcome', 'note', 'before', 'after', 'created_at'] as $c) {
            $this->assertTrue(Schema::hasColumn('fuel_transaction_review_events', $c), "review_events missing $c");
        }
        $this->assertFalse(Schema::hasColumn('fuel_transaction_review_events', 'updated_at'), 'append-only: no updated_at');
        $this->assertSame('CASCADE', $this->fkDeleteRule('fuel_transaction_review_events', 'fuel_card_transaction_id'));
    }

    public function test_batches_extended_counters(): void
    {
        foreach (['accepted_rows', 'rejected_rows', 'source_counts', 'type_counts', 'technical_finding_counts', 'business_finding_counts', 'decision_counts', 'policy_version'] as $c) {
            $this->assertTrue(Schema::hasColumn('fuel_import_batches', $c), "fuel_import_batches missing $c");
        }
        $this->assertFalse(Schema::hasColumn('fuel_import_batches', 'valid_rows'));
        $this->assertFalse(Schema::hasColumn('fuel_import_batches', 'exception_rows'));
    }

    private function nullable(string $table, string $col): string
    {
        return DB::selectOne(
            'SELECT IS_NULLABLE n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
            [$table, $col]
        )->n;
    }

    private function columnDefault(string $table, string $col): ?string
    {
        return DB::selectOne(
            'SELECT COLUMN_DEFAULT d FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',
            [$table, $col]
        )->d;
    }

    private function hasUniqueIndex(string $table, string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))->contains(fn ($r) => $r->Key_name === $name && (int) $r->Non_unique === 0);
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))->contains(fn ($r) => $r->Key_name === $name);
    }

    private function fkDeleteRule(string $table, string $col): ?string
    {
        $row = DB::selectOne(
            'SELECT rc.DELETE_RULE d
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             JOIN information_schema.KEY_COLUMN_USAGE k
               ON k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ?',
            [$table, $col]
        );

        return $row?->d;
    }
}
