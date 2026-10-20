<?php

declare(strict_types=1);

if (! function_exists('money')) {
    function money(float|int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' F CFA';
    }
}

if (! function_exists('qr_code_data_uri')) {
    function qr_code_data_uri(string $data, int $size = 150): string
    {
        $result = (new \Endroid\QrCode\Builder\Builder(data: $data, size: $size, margin: 5))->build();

        return $result->getDataUri();
    }
}

if (! function_exists('barcode_data_uri')) {
    function barcode_data_uri(string $data, int $widthFactor = 1, int $height = 20): string
    {
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG;
        $png = $generator->getBarcode($data, \Picqer\Barcode\BarcodeGeneratorPNG::TYPE_CODE_128, $widthFactor, $height);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}

if (! function_exists('pdf_rtl')) {
    /**
     * Prépare un texte arabe pour dompdf : dompdf lie et ordonne les lettres
     * de chaque mot, mais pose les mots de gauche à droite, si bien qu'un
     * texte de plusieurs mots se lit à l'envers. On inverse donc l'ordre des
     * mots (sans toucher aux lettres). Un texte sans caractère arabe est
     * rendu tel quel. Prévu pour des textes courts tenant sur une ligne : au
     * passage à la ligne, l'ordre des lignes serait inversé.
     */
    function pdf_rtl(?string $text): string
    {
        $text = trim((string) $text);

        if ($text === '' || preg_match('/\p{Arabic}/u', $text) !== 1) {
            return $text;
        }

        return implode(' ', array_reverse(preg_split('/\s+/u', $text) ?: []));
    }
}
