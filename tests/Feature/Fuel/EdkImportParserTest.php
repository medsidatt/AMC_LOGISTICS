<?php

namespace Tests\Feature\Fuel;

use App\Domain\Fuel\Parsing\ParsedFuelImportRow;
use App\Domain\Fuel\Parsing\ParseError;
use App\Enums\Fuel\FuelSource;
use App\Services\Fuel\EdkImportParser;
use ReflectionClass;
use Tests\TestCase;

/**
 * R6 — the parser produces immutable normalized FACTS only. These tests are pure: no DB, no seeding,
 * no truck/driver resolution — proving the parser needs none of that.
 */
class EdkImportParserTest extends TestCase
{
    private EdkImportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new EdkImportParser;
    }

    private function cardCsv(): string
    {
        return implode("\n", [
            ' ID Transaction; N transaction; Date; Montant; Numero carte;  Porteur',
            '0;855326695981716;01-Juil-2026  11:47:26;210000;37004780201406;6077 TT A1 El Hadji DIENG ',
            '0;557598760639837;30-Jui-2026  13:05:35;210000;37004780201408;6082TTA1 Abdou Khadre DIENG ',
            'Montant Total :;420000;Nombre Transaction :;2',
        ]);
    }

    private function accountCsv(): string
    {
        return implode("\n", [
            ' ID Transaction; N transaction; Date; Montant; Mode de recharge;  Commentaires',
            '0;966020316671347;18-Jui-2026  13:18:24;21840001;Rechargement par Espèces;##########huitaine ',
            '0;189930244713557;09-Jui-2026  17:52:00;210000;Transfert carte vers Compte;Transfert carte vers Compte ',
        ]);
    }

    public function test_card_family_is_detected_and_facts_normalized(): void
    {
        $file = $this->parser->parse($this->cardCsv());

        $this->assertSame(FuelSource::EDK_CARD, $file->source);
        $this->assertFalse($file->hasFileErrors());
        $this->assertSame(2, $file->rowCount());

        $r = $file->rows[0];
        $this->assertSame('855326695981716', $r->transactionRef);
        $this->assertSame('2026-07-01 11:47:26', $r->occurredAt); // Juil → July
        $this->assertSame(210000.0, $r->amount);
        $this->assertSame('37004780201406', $r->cardNumber);
        $this->assertSame('6077TTA1', $r->normalizedRegistration); // "6077 TT A1" normalized (string only)
        $this->assertSame('6077 TT A1 El Hadji DIENG', $r->holderRaw); // original preserved
        $this->assertNull($r->mode);
        $this->assertNull($r->note);
        $this->assertFalse($r->hasErrors());

        $this->assertSame('2026-06-30 13:05:35', $file->rows[1]->occurredAt); // Jui → June
        $this->assertSame('6082TTA1', $file->rows[1]->normalizedRegistration);
    }

    public function test_account_family_is_detected_with_mode_and_note(): void
    {
        $file = $this->parser->parse($this->accountCsv());

        $this->assertSame(FuelSource::EDK_ACCOUNT, $file->source);
        $this->assertSame(2, $file->rowCount());

        $r = $file->rows[0];
        $this->assertSame('2026-06-18 13:18:24', $r->occurredAt);
        $this->assertSame(21840001.0, $r->amount);
        $this->assertSame('Rechargement par Espèces', $r->mode);
        $this->assertSame('##########huitaine', $r->note);
        $this->assertNull($r->cardNumber, 'account family has no card');
        $this->assertNull($r->normalizedRegistration, 'account family has no plate');
        $this->assertNull($r->holderRaw);
    }

    public function test_malformed_row_yields_a_parse_error_but_is_not_lost(): void
    {
        $csv = " ID Transaction; N transaction; Date; Montant; Numero carte;  Porteur\n0;123;too;few";
        $file = $this->parser->parse($csv);

        $this->assertSame(1, $file->rowCount());
        $this->assertTrue($file->rows[0]->hasErrors());
        $this->assertSame(ParseError::MALFORMED_ROW, $file->rows[0]->errors[0]->code);
        $this->assertSame('0;123;too;few', $file->rows[0]->rawLine); // original preserved for audit
    }

    public function test_unparseable_date_keeps_raw_and_flags_error(): void
    {
        $csv = " ID Transaction; N transaction; Date; Montant; Numero carte;  Porteur\n0;123;99-Zzz-2026  10:00:00;210000;CARD1;6077TTA1 X";
        $r = $this->parser->parse($csv)->rows[0];

        $this->assertNull($r->occurredAt);
        $this->assertSame('99-Zzz-2026  10:00:00', $r->occurredAtRaw);
        $this->assertContains(ParseError::UNPARSEABLE_DATE, array_map(fn ($e) => $e->code, $r->errors));
    }

    public function test_unparseable_amount_and_missing_ref_are_flagged(): void
    {
        $csv = " ID Transaction; N transaction; Date; Montant; Numero carte;  Porteur\n0;;01-Mai-2026  10:00:00;abc;CARD1;6077TTA1 X";
        $r = $this->parser->parse($csv)->rows[0];

        $codes = array_map(fn ($e) => $e->code, $r->errors);
        $this->assertContains(ParseError::UNPARSEABLE_AMOUNT, $codes);
        $this->assertContains(ParseError::MISSING_TRANSACTION_REF, $codes);
        $this->assertNull($r->amount);
        $this->assertNull($r->transactionRef);
    }

    public function test_unknown_format_returns_file_error_no_rows(): void
    {
        $file = $this->parser->parse("colA;colB;colC\n1;2;3");

        $this->assertTrue($file->hasFileErrors());
        $this->assertSame(ParseError::UNKNOWN_FORMAT, $file->fileErrors[0]->code);
        $this->assertSame(FuelSource::CSV, $file->source);
        $this->assertSame(0, $file->rowCount());
    }

    public function test_zero_amount_parses_without_error_parser_does_not_judge(): void
    {
        // Parser normalizes 0 as a fact; whether 0 is "invalid" is a ClassificationPolicy decision (R7).
        $csv = " ID Transaction; N transaction; Date; Montant; Numero carte;  Porteur\n0;123;01-Mai-2026  10:00:00;0;CARD1;6077TTA1 X";
        $r = $this->parser->parse($csv)->rows[0];

        $this->assertSame(0.0, $r->amount);
        $this->assertFalse($r->hasErrors());
    }

    public function test_row_dto_is_immutable(): void
    {
        foreach ((new ReflectionClass(ParsedFuelImportRow::class))->getProperties() as $p) {
            $this->assertTrue($p->isReadOnly(), "ParsedFuelImportRow::\${$p->getName()} must be readonly");
        }
    }

    public function test_row_dto_carries_no_business_decision_fields(): void
    {
        $props = array_map(fn ($p) => $p->getName(), (new ReflectionClass(ParsedFuelImportRow::class))->getProperties());
        foreach (['transactionType', 'kpiEligible', 'reviewStatus', 'review', 'needsReview', 'findings', 'technicalFindings', 'businessFindings', 'persistence'] as $forbidden) {
            $this->assertNotContains($forbidden, $props, "parser DTO must not carry the decision field '$forbidden'");
        }
    }
}
