<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Reconciliation\CsvExporter;

class CsvExporterTest extends TestCase
{
    public function test_builds_csv_string_with_headers_and_rows()
    {
        $headers = ['id', 'name', 'amount'];
        $rows = [
            [1, 'Alice', '10.00'],
            [2, 'Bob', '20.50'],
        ];

        $csv = CsvExporter::buildCsv($headers, $rows);

        $this->assertStringContainsString('id,name,amount', $csv);
        $this->assertStringContainsString('1,Alice,10.00', $csv);
        $this->assertStringContainsString('2,Bob,20.50', $csv);
    }
}
