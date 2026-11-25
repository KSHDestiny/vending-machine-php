<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Csv;
use PHPUnit\Framework\TestCase;

final class CsvTest extends TestCase
{
    public function testCellsAreQuotedAndFormulasNeutralised(): void
    {
        self::assertSame("\"7\",\"Say \"\"hi\"\"\",\"'=HYPERLINK(1)\",\"'+1\",\"'-2\",\"'@x\",\"6.885\"\r\n", Csv::line([7, 'Say "hi"', '=HYPERLINK(1)', '+1', '-2', '@x', '6.885']));
    }
}
