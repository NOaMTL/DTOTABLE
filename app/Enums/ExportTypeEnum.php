<?php

namespace App\Enums;

enum ExportTypeEnum: string
{
    case PDF = 'pdf';
    case EXCEL = 'excel';
    case CSV = 'csv';

    public function label(): string
    {
        return match($this) {
            self::PDF => 'PDF',
            self::EXCEL => 'Excel',
            self::CSV => 'CSV',
        };
    }
}
