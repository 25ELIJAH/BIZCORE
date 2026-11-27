<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CompanyAsset;

/** How a company wants the app to look: logo, glass (see-through) interface and background. */
class Appearance
{
    /** key => [label, built-in image path (null = none)] */
    public const BACKGROUNDS = [
        'plain' => ['Plain', null],
        'sunset' => ['Sunset', 'images/auth-bg.svg'],
        'aurora' => ['Aurora', 'images/bg-aurora.svg'],
        'ocean' => ['Ocean', 'images/bg-ocean.svg'],
        'city-dusk' => ['City dusk', 'images/backgrounds/city-dusk.webp'],
        'fuji-dusk' => ['Fuji dusk', 'images/backgrounds/fuji-dusk.webp'],
        'highlands' => ['Highlands', 'images/backgrounds/highlands.webp'],
        'sunset-peak' => ['Sunset peak', 'images/backgrounds/sunset-peak.webp'],
        'forest-lake' => ['Forest lake', 'images/backgrounds/forest-lake.webp'],
        'lake-cabin' => ['Lake cabin', 'images/backgrounds/lake-cabin.webp'],
    ];

    /**
     * @return array{glass: bool, level: int, alpha: string, blur: int, background: string, backgroundUrl: ?string, logoUrl: ?string, showLogo: bool, hasLogo: bool, hasCustomBackground: bool}
     */
    public static function for(Company $company): array
    {
        $saved = (array) $company->setting('appearance', []);
        $level = max(0, min(100, (int) ($saved['level'] ?? 60)));

        // Only the kind and time are read here (not the image itself): this runs on every page.
        $assets = CompanyAsset::withoutGlobalScopes()->where('company_id', $company->id)->get(['id', 'kind', 'updated_at'])->keyBy('kind');

        $background = (string) ($saved['background'] ?? 'plain');
        if ($background === 'custom' && ! $assets->has(CompanyAsset::BACKGROUND)) {
            $background = 'plain';
        }
        if ($background !== 'custom' && ! isset(self::BACKGROUNDS[$background])) {
            $background = 'plain';
        }

        return [
            'glass' => (bool) ($saved['glass'] ?? false),
            'level' => $level,
            'alpha' => number_format(1 - $level * 0.0075, 3, '.', ''),
            'blur' => (int) round(4 + $level * 0.16),
            'background' => $background,
            'backgroundUrl' => self::backgroundUrl($background, $assets->get(CompanyAsset::BACKGROUND)?->updated_at?->timestamp),
            'logoUrl' => $assets->has(CompanyAsset::LOGO) ? route('company.asset', ['kind' => 'logo', 'v' => $assets->get(CompanyAsset::LOGO)->updated_at->timestamp]) : null,
            'showLogo' => (bool) ($saved['show_logo'] ?? true),
            'hasLogo' => $assets->has(CompanyAsset::LOGO),
            'hasCustomBackground' => $assets->has(CompanyAsset::BACKGROUND),
        ];
    }

    public static function backgroundUrl(string $background, ?int $customVersion = null): ?string
    {
        if ($background === 'custom') {
            return route('company.asset', ['kind' => 'background', 'v' => $customVersion ?? 1]);
        }

        $path = self::BACKGROUNDS[$background][1] ?? null;

        return $path ? asset($path).'?v=1' : null;
    }
}
