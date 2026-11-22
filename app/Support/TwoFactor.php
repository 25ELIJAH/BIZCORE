<?php

namespace App\Support;

use App\Models\User;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** Authenticator-app (TOTP) verification: secrets, QR codes, code checks and one-time recovery codes. */
class TwoFactor
{
    /** Session flag set once this session has passed the code check. */
    public const SESSION_KEY = 'two_factor_verified';

    private static ?bool $schemaReady = null;

    /** On when required by config AND the database has the columns (so a deploy before the migration cannot lock everyone out). */
    public static function required(): bool
    {
        if (! config('bizcore.two_factor_required')) {
            return false;
        }

        return self::$schemaReady ??= Schema::hasColumn('users', 'two_factor_confirmed_at');
    }

    public static function forgetSchemaCache(): void
    {
        self::$schemaReady = null;
    }

    public function newSecret(): string
    {
        return (new Google2FA)->generateSecretKey();
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        return $code !== '' && ctype_digit($code) && (new Google2FA)->verifyKey($secret, $code, 1) !== false;
    }

    /** otpauth:// URL that authenticator apps read from the QR code. */
    public function url(User $user, string $secret): string
    {
        return (new Google2FA)->getQRCodeUrl('BIZCORE ERP', $user->email, $secret);
    }

    /** Inline SVG of the QR code. */
    public function qrSvg(string $url): string
    {
        $options = new QROptions(['outputType' => QROutputInterface::MARKUP_SVG, 'outputBase64' => false, 'svgAddXmlHeader' => false, 'scale' => 5, 'addQuietzone' => true]);

        return (new QRCode($options))->render($url);
    }

    /**
     * Ten single-use recovery codes. Returns [plain codes to show once, hashed codes to store].
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    public function newRecoveryCodes(): array
    {
        $plain = collect(range(1, 10))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();

        return [$plain, array_map(fn ($c) => Hash::make($c), $plain)];
    }

    /** Uses up a recovery code if it matches; returns whether it did. */
    public function useRecoveryCode(User $user, string $input): bool
    {
        $input = Str::lower(trim($input));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $i => $hash) {
            if (Hash::check($input, $hash)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }
}
