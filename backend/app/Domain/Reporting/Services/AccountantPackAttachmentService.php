<?php

namespace App\Domain\Reporting\Services;

use RuntimeException;
use ZipArchive;

final class AccountantPackAttachmentService
{
    public function pdf(array $payload): string
    {
        $manifest = $payload['manifest'] ?? [];
        $counts = $manifest['record_counts'] ?? [];
        $lines = [
            'TaxVault Accountant Pack',
            'Financial year: '.($manifest['year_code'] ?? 'Unknown'),
            'Generated: '.($manifest['generated_at'] ?? 'Unknown'),
            'Export version: '.($manifest['export_version'] ?? 'Unknown'),
            '',
            'Record summary',
        ];

        foreach ([
            'transactions' => 'Transactions',
            'documents' => 'Documents',
            'properties' => 'Properties',
            'investments' => 'Investments',
            'property_deductions' => 'Property deductions',
            'mortgage_interest_entries' => 'Mortgage interest entries',
            'reviews' => 'Reviews',
        ] as $key => $label) {
            $lines[] = $label.': '.(int) ($counts[$key] ?? 0);
        }

        $lines[] = '';
        $lines[] = 'Transactions';
        $lines[] = 'Date | Description | Direction | Amount | ATO category';
        foreach ($payload['tax_records']['transactions'] ?? [] as $row) {
            $lines[] = implode(' | ', [
                $row['transaction_date'] ?? '',
                $row['description'] ?? '',
                $row['direction'] ?? '',
                $row['amount'] ?? '',
                $row['ato_category'] ?? '',
            ]);
        }

        $lines[] = '';
        $lines[] = 'Documents';
        $lines[] = 'Filename | Status | Uploaded at';
        foreach ($payload['source_references']['documents'] ?? [] as $row) {
            $lines[] = implode(' | ', [
                $row['original_filename'] ?? '',
                $row['status'] ?? '',
                $row['uploaded_at'] ?? '',
            ]);
        }

        return $this->simplePdf($lines);
    }

    public function xlsx(array $payload): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to create an XLSX accountant pack.');
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
                    $style = $rowIndex === 0 ? '1' : '0';
                    if (is_int($cell) || is_float($cell) || (is_string($cell) && is_numeric($cell) && $cell !== '')) {
                        $cells .= '<c r="'.$reference.'" s="'.$style.'" t="n"><v>'.$escape($cell).'</v></c>';
                    } else {
                        $cells .= '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t>'.$escape($cell).'</t></is></c>';
                    }
                }
                $rowsXml .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
            }
            $sheetXml[$index] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rowsXml.'</sheetData></worksheet>';
            $index++;
        }

        $zip = new ZipArchive;
        $temporary = tempnam(sys_get_temp_dir(), 'taxvault-email-xlsx-');
        if ($temporary === false || $zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the XLSX accountant pack.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$contentOverrides.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$workbookSheets.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$workbookRelationships.'<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/></cellXfs></styleSheet>');
        foreach ($sheetXml as $sheet => $xml) {
            $zip->addFromString('xl/worksheets/sheet'.$sheet.'.xml', $xml);
        }
        $zip->close();
        $binary = file_get_contents($temporary);
        @unlink($temporary);

        if ($binary === false) {
            throw new RuntimeException('Unable to read the generated XLSX accountant pack.');
        }

        return $binary;
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
        $interest = [['Property', 'Bank', 'Month', 'Interest paid (AUD)', 'Mortgage ID', 'Source']];
        foreach ($payload['assets']['mortgage_interest_entries'] ?? [] as $row) {
            $interest[] = [$row['property_reference_code'] ?? $row['property_id'] ?? '', $row['bank_name'] ?? '', $row['month'] ?? '', $row['interest_amount'] ?? '', $row['mortgage_id'] ?? '', $row['mortgage_id'] ? 'Mortgage interest entry' : 'Rental interest expense'];
        }
        if (count($interest) > 1) {
            $interest[] = ['', '', 'Total amount of interest paid', array_sum(array_map(static fn (array $row): float => (float) ($row['interest_amount'] ?? 0), $payload['assets']['mortgage_interest_entries'] ?? [])), '', ''];
        }
        $investments = [['ID', 'Type', 'Acquisition date', 'Cost base', 'Current value', 'Property ID']];
        foreach ($payload['assets']['investments'] ?? [] as $row) {
            $investments[] = [$row['id'] ?? '', $row['investment_type'] ?? '', $row['acquisition_date'] ?? '', $row['cost_base'] ?? '', $row['current_value'] ?? '', $row['property_id'] ?? ''];
        }
        $documents = [['ID', 'Filename', 'Description', 'Status', 'Uploaded at']];
        foreach ($payload['source_references']['documents'] ?? [] as $row) {
            $documents[] = [$row['id'] ?? '', $row['original_filename'] ?? '', $row['description'] ?? '', $row['status'] ?? '', $row['uploaded_at'] ?? ''];
        }

        return [
            'Transactions (income & expenses)' => $transactions,
            'Property deductions' => $deductions,
            'Interest paid' => $interest,
            'Investments' => $investments,
            'Documents' => $documents,
        ];
    }

    /** @param list<string> $lines */
    private function simplePdf(array $lines): string
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $text = trim((string) $line);
            if ($text === '') {
                $wrapped[] = '';

                continue;
            }
            foreach (str_split($text, 105) as $part) {
                $wrapped[] = $part;
            }
        }

        $pages = array_chunk($wrapped, 50);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageReferences = [];
        $nextObject = 4;

        foreach ($pages as $page) {
            $content = "BT\n/F1 10 Tf\n40 800 Td\n";
            foreach ($page as $index => $line) {
                $safe = $this->pdfText($line);
                $content .= ($index === 0 ? '' : "0 -14 Td\n").'('.$safe.') Tj\n';
            }
            $content .= "ET\n";
            $contentObject = $nextObject++;
            $pageObject = $nextObject++;
            $objects[$contentObject] = '<< /Length '.strlen($content).' >>\nstream\n'.$content.'endstream';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $pageReferences[] = $pageObject.' 0 R';
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageReferences).'] /Count '.count($pageReferences).' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($number = 1; $number <= count($objects); $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number] ?? 0);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $value): string
    {
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
