<?php

namespace App\Services;

class PdfService
{
    public function generate(string $filename, string $content): string
    {
        $path = __DIR__ . '/../../storage/documents/' . $filename;
        file_put_contents($path, "PDF EXPORT\n\n" . $content);
        return $path;
    }
}
