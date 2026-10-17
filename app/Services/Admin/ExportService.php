<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Core\Response;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exports CSV (UTF-8 avec BOM, séparateur « ; » pour Excel francophone) et Excel (PhpSpreadsheet).
 * Les fichiers sont écrits dans storage/exports/ puis envoyés en téléchargement.
 */
final class ExportService
{
    /**
     * @param list<string> $headers
     * @param iterable<list<scalar|null>> $rows
     */
    public static function download(string $format, string $baseName, array $headers, iterable $rows): Response
    {
        $dir = storage_path('exports');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = preg_replace('/[^a-z0-9_-]/i', '', $baseName) . '-' . gmdate('Ymd-His');
        if ($format === 'xlsx') {
            $path = $dir . '/' . $name . '.xlsx';
            self::writeXlsx($path, $headers, $rows);

            return Response::file($path, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, 'attachment', $name . '.xlsx');
        }
        $path = $dir . '/' . $name . '.csv';
        self::writeCsv($path, $headers, $rows);

        return Response::file($path, 'text/csv; charset=utf-8', null, 'attachment', $name . '.csv');
    }

    /**
     * @param list<string> $headers
     * @param iterable<list<scalar|null>> $rows
     */
    private static function writeCsv(string $path, array $headers, iterable $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Export impossible : ' . $path);
        }
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers, ';', '"', '\\', "\r\n");
        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn ($v) => self::safeCell($v), $row), ';', '"', '\\', "\r\n");
        }
        fclose($handle);
    }

    /**
     * @param list<string> $headers
     * @param iterable<list<scalar|null>> $rows
     */
    private static function writeXlsx(string $path, array $headers, iterable $rows): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Export');
        $sheet->setRightToLeft(\App\Core\Lang::isRtl());
        $col = 1;
        foreach ($headers as $header) {
            $sheet->getCell([$col, 1])->setValueExplicit($header, DataType::TYPE_STRING);
            $sheet->getStyle([$col, 1])->getFont()->setBold(true);
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            $col++;
        }
        $line = 2;
        foreach ($rows as $row) {
            $col = 1;
            foreach ($row as $value) {
                $sheet->getCell([$col, $line])->setValueExplicit(self::safeCell($value), DataType::TYPE_STRING);
                $col++;
            }
            $line++;
        }
        $sheet->freezePane('A2');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }

    /** Neutralise les formules (injection CSV) et normalise la valeur. */
    private static function safeCell(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($text)) {
            $text = "'" . $text;
        }

        return $text;
    }

    /** Supprime les exports de plus de $hours heures. */
    public static function purge(int $hours = 24): int
    {
        $count = 0;
        foreach (glob(storage_path('exports') . '/*.{csv,xlsx}', GLOB_BRACE) ?: [] as $file) {
            if (filemtime($file) < time() - $hours * 3600 && @unlink($file)) {
                $count++;
            }
        }

        return $count;
    }
}
