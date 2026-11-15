<?php

namespace Database\Seeders;

use App\Enums\IncidentStatus;
use App\Models\Category;
use App\Models\Incident;
use App\Models\Status;
use App\Services\IncidentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Incidencias de ejemplo coherentes. Se crean a través de IncidentService
 * "viajando en el tiempo", de modo que cada una tiene su traza completa
 * y sus plazos de SLA reales.
 */
class IncidentSeeder extends Seeder
{
    public function run(IncidentService $service): void
    {
        $now = CarbonImmutable::now();

        foreach ($this->scenarios() as $scenario) {
            Carbon::setTestNow($now->subMinutes($scenario['created_minutes_ago']));

            $incident = $service->create([
                'title' => $scenario['title'],
                'description' => $scenario['description'],
                'status_id' => Status::findByType(IncidentStatus::Open)->id,
                'priority' => $scenario['priority'],
                'category_id' => Category::query()->where('name', $scenario['category'])->value('id'),
                'requester_name' => $scenario['requester'],
                'assigned_to' => $scenario['assignee'],
            ], $scenario['requester']);

            foreach ($scenario['steps'] as $step) {
                Carbon::setTestNow($now->subMinutes($step['minutes_ago']));
                $this->applyStep($service, $incident, $step);
            }
        }

        Carbon::setTestNow();
    }

    /** @param  array<string, mixed>  $step */
    private function applyStep(IncidentService $service, Incident $incident, array $step): void
    {
        if (isset($step['comment'])) {
            $service->addComment($incident, $step['by'], $step['comment']);

            return;
        }

        $service->changeStatus($incident, Status::findByType($step['to']), $step['by'], $step['reason'] ?? null);
    }

    /** @return list<array<string, mixed>> */
    private function scenarios(): array
    {
        $day = 1440;

        return [
            [
                'title' => 'Caída del servicio de VPN corporativa',
                'description' => 'Los usuarios remotos no pueden establecer el túnel VPN desde primera hora. Afecta a toda la plantilla en teletrabajo.',
                'priority' => 'P1', 'category' => 'Redes',
                'requester' => 'Marta Ruiz', 'assignee' => 'Javier Ortega',
                'created_minutes_ago' => 3 * $day,
                'steps' => [
                    ['minutes_ago' => 3 * $day - 10, 'to' => IncidentStatus::InReview, 'by' => 'Javier Ortega'],
                    ['minutes_ago' => 3 * $day - 45, 'by' => 'Javier Ortega', 'comment' => 'El concentrador principal no responde. Escalado al proveedor.'],
                ],
            ],
            [
                'title' => 'Usuarios no pueden restablecer su contraseña',
                'description' => 'El enlace de recuperación devuelve un error 500 en el portal de empleados.',
                'priority' => 'P2', 'category' => 'Accesos',
                'requester' => 'Luis Paredes', 'assignee' => 'Ana Beltrán',
                'created_minutes_ago' => 4 * $day,
                'steps' => [
                    ['minutes_ago' => 4 * $day - 30, 'to' => IncidentStatus::InReview, 'by' => 'Ana Beltrán'],
                    ['minutes_ago' => 3 * $day, 'to' => IncidentStatus::Blocked, 'by' => 'Ana Beltrán', 'reason' => 'Pendiente de que el proveedor de correo libere la cuota de envío.'],
                ],
            ],
            [
                'title' => 'Error al exportar el informe mensual de ventas',
                'description' => 'La exportación a Excel se queda en blanco cuando el rango supera los 30 días.',
                'priority' => 'P3', 'category' => 'Bug Informático',
                'requester' => 'Carmen Vidal', 'assignee' => null,
                'created_minutes_ago' => 2 * $day,
                'steps' => [],
            ],
            [
                'title' => 'Solicitud de acceso a la carpeta de Finanzas',
                'description' => 'Nueva incorporación en el equipo de control de gestión necesita permisos de lectura.',
                'priority' => 'P4', 'category' => 'Accesos',
                'requester' => 'Pablo Serrano', 'assignee' => null,
                'created_minutes_ago' => 90,
                'steps' => [],
            ],
            [
                'title' => 'Campaña de phishing dirigida a Administración',
                'description' => 'Varios empleados han recibido un correo que suplanta al banco. Dos de ellos abrieron el enlace.',
                'priority' => 'P2', 'category' => 'Seguridad',
                'requester' => 'Elena Castro', 'assignee' => 'Javier Ortega',
                'created_minutes_ago' => 6 * $day,
                'steps' => [
                    ['minutes_ago' => 6 * $day - 20, 'to' => IncidentStatus::InReview, 'by' => 'Javier Ortega'],
                    ['minutes_ago' => 6 * $day - 60, 'by' => 'Javier Ortega', 'comment' => 'Dominio bloqueado en el proxy y credenciales de los dos usuarios rotadas.'],
                    ['minutes_ago' => 6 * $day - 150, 'to' => IncidentStatus::Resolved, 'by' => 'Javier Ortega'],
                ],
            ],
            [
                'title' => 'La impresora de la planta 2 imprime en blanco',
                'description' => 'Tras el cambio de tóner las páginas salen sin contenido.',
                'priority' => 'P3', 'category' => 'Bug Informático',
                'requester' => 'Rosa Molina', 'assignee' => 'Diego Navarro',
                'created_minutes_ago' => 13 * $day,
                'steps' => [
                    ['minutes_ago' => 12 * $day, 'to' => IncidentStatus::InReview, 'by' => 'Diego Navarro'],
                    ['minutes_ago' => 3 * $day, 'to' => IncidentStatus::Resolved, 'by' => 'Diego Navarro'],
                ],
            ],
            [
                'title' => 'Pérdida intermitente de wifi en la sala de reuniones',
                'description' => 'La señal se corta durante las videollamadas. Ya se resolvió una vez y ha vuelto a ocurrir.',
                'priority' => 'P3', 'category' => 'Redes',
                'requester' => 'Sergio León', 'assignee' => 'Ana Beltrán',
                'created_minutes_ago' => 9 * $day,
                'steps' => [
                    ['minutes_ago' => 9 * $day - 120, 'to' => IncidentStatus::InReview, 'by' => 'Ana Beltrán'],
                    ['minutes_ago' => 7 * $day, 'to' => IncidentStatus::Resolved, 'by' => 'Ana Beltrán'],
                    ['minutes_ago' => 600, 'to' => IncidentStatus::Open, 'by' => 'Sergio León', 'reason' => 'El corte se ha reproducido tras reiniciar el punto de acceso.'],
                ],
            ],
            [
                'title' => 'Actualizar el certificado SSL del portal de proveedores',
                'description' => 'El certificado caduca a final de mes. Hay que renovarlo y desplegarlo sin corte de servicio.',
                'priority' => 'P4', 'category' => 'Seguridad',
                'requester' => 'Irene Campos', 'assignee' => 'Diego Navarro',
                'created_minutes_ago' => 5 * $day,
                'steps' => [
                    ['minutes_ago' => 5 * $day - 200, 'to' => IncidentStatus::InReview, 'by' => 'Diego Navarro'],
                ],
            ],
            [
                'title' => 'El TPV de la tienda 14 no sincroniza ventas',
                'description' => 'Las ventas del turno de tarde no aparecen en el sistema central.',
                'priority' => 'P1', 'category' => 'Bug Informático',
                'requester' => 'Nuria Gil', 'assignee' => 'Ana Beltrán',
                'created_minutes_ago' => 40,
                'steps' => [
                    ['minutes_ago' => 35, 'to' => IncidentStatus::InReview, 'by' => 'Ana Beltrán'],
                ],
            ],
            [
                'title' => 'Solicitud de segundo monitor para el puesto 12',
                'description' => 'Petición de material duplicada: ya se tramitó por el circuito de compras.',
                'priority' => 'P4', 'category' => 'Accesos',
                'requester' => 'Pablo Serrano', 'assignee' => null,
                'created_minutes_ago' => 3 * $day,
                'steps' => [
                    ['minutes_ago' => 2 * $day, 'to' => IncidentStatus::Cancelled, 'by' => 'Ana Beltrán', 'reason' => 'Duplicada: la petición ya está gestionada por Compras.'],
                ],
            ],
            [
                'title' => 'Cuenta de servicio bloqueada en el ERP',
                'description' => 'La integración nocturna falla porque la cuenta técnica quedó bloqueada por intentos fallidos.',
                'priority' => 'P2', 'category' => 'Accesos',
                'requester' => 'Óscar Prieto', 'assignee' => null,
                'created_minutes_ago' => 200,
                'steps' => [],
            ],
        ];
    }
}
