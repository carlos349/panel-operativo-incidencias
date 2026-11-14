<?php

namespace App\Providers;

use App\Support\Sla\BusinessHoursCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BusinessHoursCalculator::class, fn () => BusinessHoursCalculator::fromConfig());
    }

    public function boot(): void
    {
        // En desarrollo, un N+1 o un atributo inexistente falla en vez de pasar inadvertido.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
