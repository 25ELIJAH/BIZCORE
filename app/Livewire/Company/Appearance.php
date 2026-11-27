<?php

namespace App\Livewire\Company;

use App\Models\Company;
use App\Models\CompanyAsset;
use App\Services\ActivityLogger;
use App\Support\Appearance as Look;
use App\Support\Tenancy\CurrentCompany;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Settings: the company's logo and how the whole app looks. Every change is saved the moment it is made. */
#[Layout('layouts.app')]
#[Title('Settings')]
class Appearance extends Component
{
    private const LIMITS = [CompanyAsset::LOGO => 700_000, CompanyAsset::BACKGROUND => 3_000_000];

    public bool $glass = false;

    public int $level = 60;

    public string $background = 'plain';

    public bool $showLogo = true;

    public string $notice = '';

    public function mount(): void
    {
        $this->authorize('manage-company');
        $look = Look::for(app(CurrentCompany::class)->get());
        $this->glass = $look['glass'];
        $this->level = $look['level'];
        $this->background = $look['background'];
        $this->showLogo = $look['showLogo'];
    }

    // ---- toggles and choices (saved as they change) ------------------------------------------------

    public function updatedGlass(): void
    {
        // Turning the see-through look on with nothing behind it would show nothing: pick a scene.
        if ($this->glass && $this->background === 'plain') {
            $this->background = 'sunset';
        }
        $this->persist('See-through interface '.($this->glass ? 'on' : 'off').'.');
    }

    public function updatedLevel(): void
    {
        $this->level = max(0, min(100, $this->level));
        $this->persist();
    }

    public function updatedShowLogo(): void
    {
        $this->persist('Logo '.($this->showLogo ? 'shown in the header' : 'hidden from the header').'.');
    }

    public function chooseBackground(string $key): void
    {
        $this->authorize('manage-company');
        $valid = array_keys(Look::BACKGROUNDS);
        if ($key === 'custom' && ! $this->look()['hasCustomBackground']) {
            return;
        }
        abort_unless($key === 'custom' || in_array($key, $valid, true), 422);

        $this->background = $key;
        $this->persist('Background changed.');
    }

    // ---- images ------------------------------------------------------------------------------------

    /** Receives a data URL the browser has already shrunk (the app has no persistent disk for file uploads). */
    public function uploadLogo(string $dataUrl): void
    {
        $this->storeImage(CompanyAsset::LOGO, $dataUrl, 'logo');
    }

    public function uploadBackground(string $dataUrl): void
    {
        if ($this->storeImage(CompanyAsset::BACKGROUND, $dataUrl, 'background')) {
            $this->background = 'custom';
            $this->persist('Your background is now showing.');
        }
    }

    public function removeLogo(): void
    {
        $this->removeImage(CompanyAsset::LOGO, 'Logo removed.');
    }

    public function removeBackground(): void
    {
        $this->removeImage(CompanyAsset::BACKGROUND, 'Background image removed.');
        if ($this->background === 'custom') {
            $this->background = $this->glass ? 'sunset' : 'plain';
            $this->persist();
        }
    }

    private function storeImage(string $kind, string $dataUrl, string $what): bool
    {
        $this->authorize('manage-company');
        $this->resetErrorBag($what);

        // Only plain raster formats: an SVG can carry scripts, so it is refused.
        if (! preg_match('#^data:(image/(?:png|jpeg|webp));base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            $this->addError($what, 'Please choose a PNG, JPG or WebP picture.');

            return false;
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($bytes === false || $bytes === '') {
            $this->addError($what, 'That picture could not be read. Try another one.');

            return false;
        }
        if (strlen($bytes) > self::LIMITS[$kind]) {
            $this->addError($what, 'That picture is too large. Try a smaller one.');

            return false;
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info['mime'] !== $m[1]) {
            $this->addError($what, 'That file is not a real picture.');

            return false;
        }

        $asset = CompanyAsset::where('kind', $kind)->first() ?? (new CompanyAsset)->forceFill(['kind' => $kind]);
        $asset->forceFill(['mime' => $m[1], 'size' => strlen($bytes), 'data' => base64_encode($bytes)])->save();
        $asset->touch(); // a new version number in the image link, so browsers fetch the new picture

        app(ActivityLogger::class)->log('company.appearance_updated', app(CurrentCompany::class)->get(), ['changed' => $kind]);
        $this->announce($kind === CompanyAsset::LOGO ? 'Logo saved.' : 'Background image saved.');

        return true;
    }

    private function removeImage(string $kind, string $message): void
    {
        $this->authorize('manage-company');
        CompanyAsset::where('kind', $kind)->delete();
        app(ActivityLogger::class)->log('company.appearance_updated', app(CurrentCompany::class)->get(), ['removed' => $kind]);
        $this->announce($message);
    }

    // ---- saving and applying -----------------------------------------------------------------------

    private function persist(?string $message = null): void
    {
        $this->authorize('manage-company');
        $company = Company::findOrFail(app(CurrentCompany::class)->id());
        $settings = (array) $company->settings;
        $settings['appearance'] = [
            'glass' => $this->glass,
            'level' => $this->level,
            'background' => $this->background,
            'show_logo' => $this->showLogo,
        ];
        $company->forceFill(['settings' => $settings])->save();
        app(CurrentCompany::class)->set($company->fresh());

        $this->announce($message);
    }

    /** Tell the page to restyle itself right now, without a reload. */
    private function announce(?string $message): void
    {
        $this->notice = $message ?? '';
        $look = $this->look();
        $this->dispatch('appearance-changed', glass: $look['glass'], alpha: $look['alpha'], blur: $look['blur'], background: $look['backgroundUrl']);
    }

    private function look(): array
    {
        return Look::for(app(CurrentCompany::class)->get());
    }

    public function render()
    {
        return view('livewire.company.appearance', [
            'look' => $this->look(),
            'scenes' => Look::BACKGROUNDS,
            'sceneUrls' => collect(Look::BACKGROUNDS)->map(fn ($s, $k) => Look::backgroundUrl($k))->all(),
        ]);
    }
}
