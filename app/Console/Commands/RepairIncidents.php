<?php

namespace App\Console\Commands;

use App\Services\Integrity\Finding;
use App\Services\Integrity\IncidentIntegrityService;
use Illuminate\Console\Command;

class RepairIncidents extends Command
{
    protected $signature = 'incidents:repair
        {--dry-run : Muestra lo que se haría sin modificar la base de datos}';

    protected $description = 'Diagnostica y sanea inconsistencias entre incidencias y su historial de logs';

    public function handle(IncidentIntegrityService $integrity): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $findings = $integrity->scan();

        if ($findings->isEmpty()) {
            $this->components->info('No se han detectado inconsistencias.');

            return self::SUCCESS;
        }

        $labels = [
            Finding::AUTO => '<fg=green>Autocorrección</>',
            Finding::MANUAL => '<fg=yellow>Revisión manual</>',
            Finding::ALREADY_FLAGGED => '<fg=gray>Ya marcada</>',
        ];

        $this->table(
            ['Incidencia', 'Tipo', 'Problema', 'Tratamiento', 'Acción'],
            $findings->map(fn (Finding $f) => [
                "#{$f->incidentId}",
                $f->code,
                wordwrap($f->problem, 44, "\n", true),
                $labels[$f->resolution],
                wordwrap($f->plan, 60, "\n", true),
            ])->all(),
        );

        $counts = $findings->countBy('resolution');
        $summary = sprintf(
            '%d hallazgos: %d autocorregibles, %d para revisión manual, %d ya marcados.',
            $findings->count(),
            $counts[Finding::AUTO] ?? 0,
            $counts[Finding::MANUAL] ?? 0,
            $counts[Finding::ALREADY_FLAGGED] ?? 0,
        );

        if ($dryRun) {
            $this->components->warn("DRY-RUN. {$summary} No se ha modificado ningún dato.");

            return self::SUCCESS;
        }

        // Cada hallazgo se aplica en su propia transacción: un fallo no arrastra al resto.
        $findings->each(fn (Finding $finding) => $integrity->apply($finding));

        $this->components->info("{$summary} Cambios aplicados.");

        return self::SUCCESS;
    }
}
