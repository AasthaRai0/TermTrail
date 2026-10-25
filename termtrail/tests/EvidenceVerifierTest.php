<?php
declare(strict_types=1);
namespace TermTrail\Tests;

use PHPUnit\Framework\TestCase;
use TermTrail\services\EvidenceVerifier as EV;

final class EvidenceVerifierTest extends TestCase
{
    private string $text;

    protected function setUp(): void
    {
        $this->text = EV::normalize("The Client shall pay all invoices within thirty (30) days of receipt.  Late payments accrue interest at 2% per month.\nEither party may terminate on \u{201C}sixty (60) days\u{201D} written notice \u{2013} no penalty.");
    }

    public function testExactQuoteIsVerified(): void
    {
        $this->assertTrue(EV::verify('Late payments accrue interest at 2% per month.', $this->text));
    }

    public function testCaseWhitespaceAndLineBreaksAreTolerated(): void
    {
        $this->assertTrue(EV::verify("LATE PAYMENTS   accrue interest\nat 2% per month", $this->text));
    }

    public function testCurlyQuotesAndDashesAreNormalised(): void
    {
        $this->assertTrue(EV::verify('terminate on "sixty (60) days" written notice - no penalty', $this->text));
    }

    public function testEllipsisSkipsTextButKeepsOrder(): void
    {
        $this->assertTrue(EV::verify('The Client shall pay ... within thirty (30) days of receipt', $this->text));
        $this->assertTrue(EV::verify("The Client shall pay \u{2026} within thirty (30) days", $this->text));
        $this->assertFalse(EV::verify('Late payments accrue ... The Client shall pay all invoices', $this->text), 'fragments must appear in order');
    }

    public function testFabricatedQuotesAreRejected(): void
    {
        $this->assertFalse(EV::verify('The Client shall pay a penalty of 50% for late delivery', $this->text));
        $this->assertFalse(EV::verify('thirty (31) days of receipt', $this->text), 'a single changed number is detected');
    }

    public function testTooShortOrEmptyQuotesAreRejected(): void
    {
        foreach ([null, '', '   ', 'pay', 'the', '"..."'] as $q) { $this->assertFalse(EV::verify($q, $this->text), var_export($q, true)); }
    }

    public function testChunkIndexLocatesTheQuote(): void
    {
        $chunks = [EV::normalize('Alpha beta gamma delta epsilon.'), EV::normalize('The notice period is sixty (60) days in total.')];
        $this->assertSame(1, EV::chunkIndex('notice period is sixty (60) days', $chunks));
        $this->assertNull(EV::chunkIndex('not anywhere in the document', $chunks));
    }
}
