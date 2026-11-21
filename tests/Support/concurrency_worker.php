<?php

/**
 * Child process used by ConcurrencyTest. Performs ONE business action as a separate
 * PHP process (its own DB connection) and prints "OK" or "REFUSED: <message>".
 *
 * Usage: php concurrency_worker.php <sale|pay> <companyId> <userId> <id> [amount]
 * Refuses to run outside the testing environment.
 */

use App\Exceptions\BusinessRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Company;
use App\Models\Sale;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SaleService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing')) {
    fwrite(STDERR, "concurrency_worker.php only runs with APP_ENV=testing\n");
    exit(2);
}

[, $action, $companyId, $userId, $id] = $argv;
$amount = $argv[5] ?? null;

app(CurrentCompany::class)->set(Company::findOrFail((int) $companyId));
Auth::login(User::findOrFail((int) $userId));

try {
    if ($action === 'sale') {
        app(SaleService::class)->create(['items' => [['product_id' => (int) $id, 'quantity' => 1]]]);
    } else {
        app(PaymentService::class)->record(Sale::findOrFail((int) $id), $amount, 'cash');
    }
    echo 'OK';
} catch (BusinessRuleException|InsufficientStockException $e) {
    echo 'REFUSED: '.$e->getMessage();
} catch (Throwable $e) {
    echo 'ERROR: '.get_class($e).': '.$e->getMessage();
}
