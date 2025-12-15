<?php

namespace App\Services\Reconciliation;

use League\Csv\Writer;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CsvExporter
{
    /**
     * Build a CSV string from headers and rows.
     *
     * @param array $headers
     * @param array $rows
     * @return string
     */
    public static function buildCsv(array $headers, array $rows): string
    {
        // Prefer League\Csv if available for correct BOM handling and performance.
        if (class_exists(Writer::class)) {
            $csv = Writer::createFromString('');
            $csv->setDelimiter(',');
            // set BOM for UTF-8
            if (defined(Writer::class . '::BOM_UTF8')) {
                $csv->setOutputBOM(Writer::BOM_UTF8);
            }

            if (!empty($headers)) {
                $csv->insertOne($headers);
            }

            foreach ($rows as $row) {
                $csv->insertOne($row);
            }

            return $csv->toString();
        }

        // Fallback: native PHP CSV writer to temp stream
        $fp = fopen('php://temp', 'r+');
        if ($fp === false) {
            return '';
        }

        if (!empty($headers)) {
            fputcsv($fp, $headers);
        }

        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }

        rewind($fp);
        $contents = stream_get_contents($fp);
        fclose($fp);

        // Prepend UTF-8 BOM for compatibility
        return "\xEF\xBB\xBF" . ($contents ?? '');
    }

    /**
     * Return a Response suitable for a streamed CSV download.
     *
     * @param string $filename
     * @param array $headers
     * @param array $rows
     * @return \Illuminate\Http\Response
     */
    public static function streamDownloadResponse(string $filename, array $headers, array $rows): Response
    {
        $csvString = self::buildCsv($headers, $rows);

        $response = response($csvString, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            'Cache-Control' => 'no-store, no-cache',
        ]);

        return $response;
    }
}
