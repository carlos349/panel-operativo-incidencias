<?php

/*
|--------------------------------------------------------------------------
| Modelo de SLA
|--------------------------------------------------------------------------
|
| Los plazos se expresan en MINUTOS LABORALES. El reloj solo avanza dentro
| del horario de oficina, en la zona horaria de la aplicación (APP_TIMEZONE).
|
*/

return [

    'business_hours' => [
        // ISO-8601: 1 = lunes ... 7 = domingo.
        'days' => [1, 2, 3, 4, 5],
        'start' => '08:00',
        'end' => '17:00',
    ],

    // Fechas no laborables en formato Y-m-d. Vacío por defecto.
    'holidays' => [],

    // Jornada de 9 h => 540 minutos por día laboral.
    'matrix' => [
        'P1' => ['first_response' => 15,  'resolution' => 240],   // 15 min / 4 h
        'P2' => ['first_response' => 60,  'resolution' => 540],   // 1 h / 1 día laboral
        'P3' => ['first_response' => 240, 'resolution' => 1620],  // 4 h / 3 días laborales
        'P4' => ['first_response' => 540, 'resolution' => 2700],  // 1 día / 5 días laborales
    ],

    // Una incidencia pasa a "en riesgo" cuando le queda este porcentaje
    // (o menos) de su plazo de resolución.
    'at_risk_remaining_ratio' => 0.25,

];
