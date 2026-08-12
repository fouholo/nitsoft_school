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
