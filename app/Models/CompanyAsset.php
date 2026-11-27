<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A company's logo or custom background image (kept in the database; see the migration for why). */
class CompanyAsset extends Model
{
    use BelongsToCompany;

    public const LOGO = 'logo';

    public const BACKGROUND = 'background';

    protected $guarded = ['*'];

    public function bytes(): string
    {
        return (string) base64_decode($this->data, true);
    }
}
