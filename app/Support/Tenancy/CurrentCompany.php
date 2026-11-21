<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use Closure;

/**
 * Holds the company the current request/job is operating in.
 * Registered as a scoped singleton so state never leaks between requests.
 */
class CurrentCompany
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->getKey();
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    /** Run a callback inside a company context (seeders, jobs, tests), then restore the previous one. */
    public function runAs(Company $company, Closure $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback();
        } finally {
            $this->company = $previous;
        }
    }
}
