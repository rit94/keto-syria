<?php

use App\Services\Food\ArabicNormalizer;

describe('normalize', function () {
    it('strips tashkeel and tatweel', function () {
        expect(ArabicNormalizer::normalize('لَبْنَةٌ'))->toBe('لبنه')
            ->and(ArabicNormalizer::normalize('جـــبـــنة'))->toBe('جبنه')
            ->and(ArabicNormalizer::normalize('حَلّوم'))->toBe('حلوم');
    });

    it('unifies alef and hamza forms', function (string $input, string $expected) {
        expect(ArabicNormalizer::normalize($input))->toBe($expected);
    })->with([
        ['أفوكادو', 'افوكادو'],
        ['إريثريتول', 'اريثريتول'],
        ['آيس', 'ايس'],
        ['ٱلزيت', 'الزيت'],
        ['لؤلؤ', 'لولو'],
        ['مائدة', 'مايده'],
    ]);

    it('unifies alef maqsura and ta marbuta', function () {
        expect(ArabicNormalizer::normalize('كوسى'))->toBe('كوسي')
            ->and(ArabicNormalizer::normalize('زهرة'))->toBe('زهره')
            ->and(ArabicNormalizer::normalize('زهره'))->toBe('زهره')
            ->and(ArabicNormalizer::normalize('طحينة'))->toBe(ArabicNormalizer::normalize('طحينه'));
    });

    it('converts Arabic-Indic and Persian digits to Western digits', function () {
        expect(ArabicNormalizer::normalize('دهن ٢٥٪'))->toBe('دهن 25')
            ->and(ArabicNormalizer::normalize('۱۲۳'))->toBe('123');
    });

    it('lowercases Latin text and removes accents', function () {
        expect(ArabicNormalizer::normalize('Halloumi CHEESE'))->toBe('halloumi cheese')
            ->and(ArabicNormalizer::normalize('Crème Fraîche'))->toBe('creme fraiche');
    });

    it('turns punctuation into separators and collapses whitespace', function () {
        expect(ArabicNormalizer::normalize("  زهرة   (قرنبيط)،  نية \n"))->toBe('زهره قرنبيط نيه')
            ->and(ArabicNormalizer::normalize('za\'atar'))->toBe('za atar')
            ->and(ArabicNormalizer::normalize('جبنة بلدية/بيضا'))->toBe('جبنه بلديه بيضا')
            ->and(ArabicNormalizer::normalize('70–85% cacao'))->toBe('70 85 cacao');
    });

    it('returns an empty string for empty or punctuation-only input', function () {
        expect(ArabicNormalizer::normalize(''))->toBe('')
            ->and(ArabicNormalizer::normalize('   '))->toBe('')
            ->and(ArabicNormalizer::normalize('،؛؟!'))->toBe('');
    });

    it('is idempotent', function (string $input) {
        $once = ArabicNormalizer::normalize($input);

        expect(ArabicNormalizer::normalize($once))->toBe($once);
    })->with(['لَبْنَة مُدَعْبَلة بزيت', 'Chicken Breast (skinless)', 'دهنة مرق العضم', 'إريثريتول ١٠٠٪']);
});

describe('stripArticle', function () {
    it('removes a leading "ال" from every token', function () {
        expect(ArabicNormalizer::stripArticle('الزهره المسلوقه'))->toBe('زهره مسلوقه')
            ->and(ArabicNormalizer::stripArticle('دهنه مرق العضم'))->toBe('دهنه مرق عضم');
    });

    it('leaves short tokens and other words alone', function () {
        expect(ArabicNormalizer::stripArticle('الي'))->toBe('الي')
            ->and(ArabicNormalizer::stripArticle('لبنه'))->toBe('لبنه')
            ->and(ArabicNormalizer::stripArticle('alfredo'))->toBe('alfredo')
            ->and(ArabicNormalizer::stripArticle(''))->toBe('');
    });

    it('combines with normalize() in forMatching() and tokens()', function () {
        expect(ArabicNormalizer::forMatching('الْجِبْنة البلديّة'))->toBe('جبنه بلديه')
            ->and(ArabicNormalizer::tokens('الجبنة  الجبنة البيضا'))->toBe(['جبنه', 'بيضا'])
            ->and(ArabicNormalizer::tokens(''))->toBe([]);
    });
});

it('detects Arabic script', function () {
    expect(ArabicNormalizer::isArabic('حلوم'))->toBeTrue()
        ->and(ArabicNormalizer::isArabic('halloumi'))->toBeFalse();
});
