<?php

declare(strict_types=1);

test('pdf_rtl inverse l’ordre des mots d’un texte arabe sans toucher aux lettres', function () {
    expect(pdf_rtl('رقم التسجيل'))->toBe('التسجيل رقم')
        ->and(pdf_rtl('أنشئت في 20/10/2026 06:22'))->toBe('06:22 20/10/2026 في أنشئت')
        ->and(pdf_rtl("  القرآن   الكريم \n"))->toBe('الكريم القرآن')
        ->and(pdf_rtl('المدير'))->toBe('المدير');
});

test('pdf_rtl laisse intact un texte sans caractère arabe', function () {
    expect(pdf_rtl('Nom et signature du prof'))->toBe('Nom et signature du prof')
        ->and(pdf_rtl(null))->toBe('')
        ->and(pdf_rtl(''))->toBe('');
});
