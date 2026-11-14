<?php

namespace App\Enums;

enum LogAction: string
{
    case Created = 'created';
    case StatusChanged = 'status_changed';
    case Reopened = 'reopened';
    case TitleChanged = 'title_changed';
    case DescriptionChanged = 'description_changed';
    case FirstResponse = 'first_response';
    case DataRepair = 'data_repair';
    case ManualReview = 'manual_review';

    /**
     * Acciones cuyo new_value representa el estado de la incidencia.
     *
     * @return list<string>
     */
    public static function statusBearing(): array
    {
        return [self::Created->value, self::StatusChanged->value, self::Reopened->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Incidencia creada',
            self::StatusChanged => 'Cambio de estado',
            self::Reopened => 'Reapertura',
            self::TitleChanged => 'Título editado',
            self::DescriptionChanged => 'Descripción editada',
            self::FirstResponse => 'Primera respuesta',
            self::DataRepair => 'Saneamiento de datos',
            self::ManualReview => 'Marcada para revisión manual',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Created => 'info',
            self::Reopened, self::DataRepair => 'warning',
            self::FirstResponse => 'success',
            self::ManualReview => 'danger',
            default => 'neutral',
        };
    }
}
