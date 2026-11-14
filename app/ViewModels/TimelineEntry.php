<?php

namespace App\ViewModels;

use App\Enums\LogAction;
use App\Models\IncidentLog;
use Illuminate\Support\Str;

/** Una fila de incident_logs convertida en una entrada legible del historial. */
final readonly class TimelineEntry
{
    public function __construct(
        public int $id,
        public string $label,
        public string $tone,
        public string $summary,
        public ?string $note,
        public string $author,
        public string $date,
        public bool $isSystem,
    ) {}

    public static function from(IncidentLog $log): self
    {
        $action = LogAction::tryFrom($log->action);
        $metadata = $log->metadata ?? [];

        return new self(
            id: $log->id,
            label: $action?->label() ?? Str::headline($log->action),
            tone: $action?->tone() ?? 'neutral',
            summary: self::summary($action, $log),
            note: self::note($metadata),
            author: $log->user_name,
            date: $log->created_at->format('d/m/Y H:i'),
            isSystem: Str::startsWith($log->user_name, 'sistema:'),
        );
    }

    private static function summary(?LogAction $action, IncidentLog $log): string
    {
        $old = Str::limit((string) $log->old_value, 90);
        $new = Str::limit((string) $log->new_value, 90);

        return match ($action) {
            LogAction::Created => "Estado inicial: {$new}",
            LogAction::StatusChanged, LogAction::Reopened => ($old ?: '—')." → {$new}",
            LogAction::TitleChanged, LogAction::DescriptionChanged => "«{$old}» → «{$new}»",
            LogAction::FirstResponse => "Registrada mediante: {$new}",
            default => $new,
        };
    }

    /** @param  array<string, mixed>  $metadata */
    private static function note(array $metadata): ?string
    {
        return match (true) {
            isset($metadata['reason']) => 'Motivo: '.$metadata['reason'],
            isset($metadata['note']) => $metadata['note'],
            isset($metadata['why_not_auto']) => $metadata['why_not_auto'],
            default => null,
        };
    }
}
