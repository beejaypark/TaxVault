<?php

namespace App\Http\Controllers\Api;

use App\Domain\FinancialYears\Models\FinancialYear;
use App\Domain\Reporting\Models\FinancialYearExportArchive;
use App\Domain\Reporting\Services\FinancialYearExportService;
use App\Domain\Reporting\Services\TransactionCsvExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\CreateFinancialYearExportRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportController extends Controller
{
    public function store(
        CreateFinancialYearExportRequest $request,
        FinancialYearExportService $exportService,
    ): JsonResponse {
        $export = $exportService->export(
            user: $request->user(),
            financialYearId: $request->string('financial_year_id')->toString(),
        );

        $archive = FinancialYearExportArchive::query()->create([
            'user_id' => $request->user()->getKey(),
            'financial_year_id' => $request->string('financial_year_id')->toString(),
            'export_version' => $export->payload['manifest']['export_version'],
            'sha256' => $export->sha256,
            'generated_at' => $export->payload['manifest']['generated_at'],
            'payload' => $export->payload,
        ]);

        return response()->json([
            'data' => [
                'id' => $archive->getKey(),
                'financial_year_id' => $archive->financial_year_id,
                'export_version' => $archive->export_version,
                'generated_at' => $archive->generated_at->toIso8601String(),
                'sha256' => $archive->sha256,
                'payload' => $archive->payload,
            ],
        ], 201);
    }

    public function transactionsCsv(
        Request $request,
        TransactionCsvExport $csvExport,
    ): StreamedResponse {
        $validated = $request->validate([
            'financial_year_id' => ['required', 'uuid'],
        ]);

        $financialYear = FinancialYear::query()
            ->whereKey($validated['financial_year_id'])
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($financialYear === null) {
            throw (new ModelNotFoundException)->setModel(
                FinancialYear::class,
                [$validated['financial_year_id']],
            );
        }

        return response()->streamDownload(
            fn () => $csvExport->write($request->user(), $financialYear),
            sprintf('taxvault-%s-transactions.csv', $financialYear->year_code),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function packDownload(
        Request $request,
        FinancialYearExportService $exportService,
    ): StreamedResponse {
        $validated = $request->validate([
            'financial_year_id' => ['required', 'uuid'],
            'format' => ['required', 'in:csv,excel'],
        ]);
        $export = $exportService->export($request->user(), $validated['financial_year_id']);
        $payload = $export->payload;
        $year = $payload['manifest']['year_code'];
        $format = $validated['format'];

        if ($format === 'excel') {
            $xlsx = $this->spreadsheetXlsx($payload);

            return response()->streamDownload(
                function () use ($xlsx): void { echo $xlsx; },
                sprintf('taxvault-%s-accountant-pack.xlsx', $year),
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            );
        }

        return response()->streamDownload(
            function () use ($payload): void {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                foreach ($this->groupedRows($payload) as $group => $rows) {
                    fputcsv($handle, [$group]);
                    foreach ($rows as $row) {
                        fputcsv($handle, $row);
                    }
                    fputcsv($handle, []);
                }
                fclose($handle);
            },
            sprintf('taxvault-%s-accountant-pack.csv', $year),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function show(
        Request $request,
        string $id,
    ): JsonResponse {
        $archive = FinancialYearExportArchive::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->getKey())
            ->first();

        if ($archive === null) {
            throw (new ModelNotFoundException)->setModel(
                FinancialYearExportArchive::class,
                [$id],
            );
        }

        return response()->json([
            'data' => [
                'id' => $archive->getKey(),
                'financial_year_id' => $archive->financial_year_id,
                'export_version' => $archive->export_version,
                'generated_at' => $archive->generated_at->toIso8601String(),
                'sha256' => $archive->sha256,
                'payload' => $archive->payload,
            ],
        ]);
    }

    /** @return array<string, list<array<int, mixed>>> */
    private function groupedRows(array $payload): array
    {
        $transactions = [['Date', 'Description', 'Direction', 'Amount', 'ATO category', 'Property ID']];
        foreach ($payload['tax_records']['transactions'] ?? [] as $row) {
            $transactions[] = [$row['transaction_date'] ?? '', $row['description'] ?? '', $row['direction'] ?? '', $row['amount'] ?? '', $row['ato_category'] ?? '', $row['property_id'] ?? ''];
        }

        $deductions = [['Property ID', 'Financial year ID', 'Type', 'Amount', 'Notes', 'Source document']];
        foreach ($payload['assets']['property_deductions'] ?? [] as $row) {
            $deductions[] = [$row['property_id'] ?? '', $row['financial_year_id'] ?? '', $row['deduction_type'] ?? '', $row['amount'] ?? '', $row['notes'] ?? '', $row['source_document_filename'] ?? ''];
        }

        $investments = [['ID', 'Type', 'Acquisition date', 'Cost base', 'Current value', 'Property ID']];
        foreach ($payload['assets']['investments'] ?? [] as $row) {
            $investments[] = [$row['id'] ?? '', $row['investment_type'] ?? '', $row['acquisition_date'] ?? '', $row['cost_base'] ?? '', $row['current_value'] ?? '', $row['property_id'] ?? ''];
        }

        $interestPaid = [['Property', 'Bank', 'Month', 'Interest paid (AUD)', 'Mortgage ID', 'Source']];
        foreach ($payload['assets']['mortgage_interest_entries'] ?? [] as $row) {
            $interestPaid[] = [
                $row['property_reference_code'] ?? $row['property_id'] ?? '',
                $row['bank_name'] ?? '',
                $row['month'] ?? '',
                $row['interest_amount'] ?? '',
                $row['mortgage_id'] ?? '',
                $row['mortgage_id'] ? 'Mortgage interest entry' : 'Rental interest expense',
            ];
        }
        if (count($interestPaid) > 1) {
            $interestPaid[] = ['', '', 'Total amount of interest paid', array_sum(array_map(
                static fn (array $row): float => (float) ($row['interest_amount'] ?? 0),
                $payload['assets']['mortgage_interest_entries'] ?? [],
            )), '', ''];
        }

        $documents = [['ID', 'Filename', 'Description', 'Status', 'Uploaded at']];
        foreach ($payload['source_references']['documents'] ?? [] as $row) {
            $documents[] = [$row['id'] ?? '', $row['original_filename'] ?? '', $row['description'] ?? '', $row['status'] ?? '', $row['uploaded_at'] ?? ''];
        }

        return [
            'Transactions (income & expenses)' => $transactions,
            'Property deductions' => $deductions,
            'Interest paid' => $interestPaid,
            'Investments' => $investments,
            'Documents' => $documents,
        ];
    }

    private function spreadsheetXlsx(array $payload): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The PHP zip extension is required to create an XLSX accountant pack.');
        }

        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $groups = $this->groupedRows($payload);
        $workbookSheets = '';
        $workbookRelationships = '';
        $contentOverrides = '';
        $sheetXml = [];
        $index = 1;

        foreach ($groups as $group => $rows) {
            $name = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $group) ?: 'Group', 0, 31);
            $workbookSheets .= '<sheet name="'.$escape($name).'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
            $workbookRelationships .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
            $contentOverrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';

            $rowsXml = '';
            foreach ($rows as $rowIndex => $row) {
                $cells = '';
                foreach ($row as $columnIndex => $cell) {
                    $column = '';
                    $number = $columnIndex + 1;
                    while ($number > 0) {
                        $remainder = ($number - 1) % 26;
                        $column = chr(65 + $remainder).$column;
                        $number = intdiv($number - 1, 26);
                    }
                    $reference = $column.($rowIndex + 1);
                    if (is_int($cell) || is_float($cell) || (is_string($cell) && is_numeric($cell) && $cell !== '')) {
                        $cells .= '<c r="'.$reference.'" s="'.($rowIndex === 0 ? '1' : '0').'" t="n"><v>'.$escape($cell).'</v></c>';
                    } else {
                        $cells .= '<c r="'.$reference.'" s="'.($rowIndex === 0 ? '1' : '0').'" t="inlineStr"><is><t>'.$escape($cell).'</t></is></c>';
                    }
                }
                $rowsXml .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
            }
            $sheetXml[$index] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rowsXml.'</sheetData></worksheet>';
            $index++;
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$contentOverrides.'</Types>';
        $rootRelationships = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$workbookSheets.'</sheets></workbook>';
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$workbookRelationships.'<Relationship Id="rId'.($index).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/></cellXfs></styleSheet>';

        $temporary = tempnam(sys_get_temp_dir(), 'taxvault-xlsx-');
        $zip = new \ZipArchive();
        if ($temporary === false || $zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the XLSX accountant pack.');
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRelationships);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/styles.xml', $styles);
        foreach ($sheetXml as $sheet => $xml) {
            $zip->addFromString('xl/worksheets/sheet'.$sheet.'.xml', $xml);
        }
        $zip->close();
        $binary = file_get_contents($temporary);
        @unlink($temporary);

        if ($binary === false) {
            throw new \RuntimeException('Unable to read the generated XLSX accountant pack.');
        }

        return $binary;
    }
}

