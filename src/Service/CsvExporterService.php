<?php

namespace Akyos\UXExportBundle\Service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use ZipArchive;

class CsvExporterService
{
    private ExporterService $exporterService;

    public function __construct(ExporterService $exporterService)
    {
        $this->exporterService = $exporterService;
    }

    /**
     * Generate CSV files from data and properties.
     * Returns the path of the generated file (CSV or ZIP).
     */
    public function export(array $data, array $properties, string $path, string $baseName): string
    {
        $spreadsheet = new Spreadsheet();
        $this->exporterService->generateMatrix($spreadsheet, $properties);
        $this->exporterService->populateData($spreadsheet, $data, $properties);

        $files = [];
        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            $csvWriter = new Csv($spreadsheet);
            $csvWriter->setSheetIndex($index);

            $suffix = $index === 0 ? '' : '_' . $sheet->getTitle();
            $filePath = rtrim($path, '/').'/'.$baseName.$suffix.'.csv';
            $csvWriter->save($filePath);
            $files[] = $filePath;
        }

        if (count($files) === 1) {
            return $files[0];
        }

        $zipPath = rtrim($path, '/').'/'.$baseName.'.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        foreach ($files as $file) {
            @unlink($file);
        }

        return $zipPath;
    }
}
