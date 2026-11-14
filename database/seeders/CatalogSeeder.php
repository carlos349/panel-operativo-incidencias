<?php

namespace Database\Seeders;

use App\Enums\IncidentStatus;
use App\Models\Category;
use App\Models\Status;
use Illuminate\Database\Seeder;

/** Catálogos iniciales: estados y categorías. Idempotente. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (IncidentStatus::cases() as $status) {
            Status::query()->firstOrCreate(['name' => $status->value]);
        }

        foreach (['Seguridad', 'Redes', 'Accesos', 'Bug Informático'] as $category) {
            Category::query()->firstOrCreate(['name' => $category]);
        }
    }
}
