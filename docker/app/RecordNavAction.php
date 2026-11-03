<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Company;
use App\Models\CustomField;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use Illuminate\Database\Eloquent\Model;

/**
 * Walk the book without going back to the list: one arrow per neighbour.
 *
 * The order is the call queue — Next Follow-up ascending (undated properties
 * last), then name — so the arrows move you through "who do I call next"
 * rather than through whatever page of the table you happened to open.
 *
 * Company-specific ordering only applies to Company; People and Opportunities
 * keep alphabetical order by name.
 */
final class RecordNavAction
{
    /** @var array<string, list<string>> */
    private static array $orderedIds = [];

    /** @var array<string, ?string> */
    private static array $neighbours = [];

    public static function previous(?string $name = null): Action
    {
        return self::make($name ?? 'previousRecord', -1, 'Previous record', 'heroicon-o-chevron-left');
    }

    public static function next(?string $name = null): Action
    {
        return self::make($name ?? 'nextRecord', 1, 'Next record', 'heroicon-o-chevron-right');
    }

    private static function make(string $name, int $direction, string $label, string $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->tooltip(fn ($livewire): string => self::neighbourLabel($livewire->getRecord(), $direction) ?? $label)
            ->icon($icon)
            ->color('gray')
            ->size(Size::Small)
            ->button()
            ->hiddenLabel()
            ->visible(fn ($livewire): bool => self::neighbourId($livewire->getRecord(), $direction) !== null)
            ->url(fn ($livewire): ?string => self::url($livewire, $direction));
    }

    private static function url($livewire, int $direction): ?string
    {
        $id = self::neighbourId($livewire->getRecord(), $direction);

        return $id === null
            ? null
            : $livewire->getResource()::getUrl('view', ['record' => $id]);
    }

    private static function neighbourId(Model $record, int $direction): ?string
    {
        $cacheKey = $record->getMorphClass().':'.$record->getKey().':'.$direction;

        if (array_key_exists($cacheKey, self::$neighbours)) {
            return self::$neighbours[$cacheKey];
        }

        $orderKey = $record->getMorphClass();
        $ids = self::$orderedIds[$orderKey] ??= self::orderedIds($record);
        $index = array_search((string) $record->getKey(), $ids, true);

        $target = $index === false ? null : ($ids[$index + $direction] ?? null);

        return self::$neighbours[$cacheKey] = $target === null ? null : (string) $target;
    }

    private static function neighbourLabel(Model $record, int $direction): ?string
    {
        $id = self::neighbourId($record, $direction);

        return $id === null ? null : (string) $record->newQuery()->find($id)?->getAttribute('name');
    }

    /**
     * @return list<string>
     */
    private static function orderedIds(Model $record): array
    {
        $query = $record->newQuery();
        $table = $record->getTable();

        if ($record instanceof Company) {
            $field = CustomField::query()
                ->where('entity_type', 'company')
                ->where('code', 'next_follow_up')
                ->first();

            if ($field !== null) {
                $query->orderByRaw(
                    '(select v.date_value from custom_field_values v
                      where v.entity_id = '.$table.'.id
                        and v.entity_type = ?
                        and v.custom_field_id = ?) asc nulls last',
                    [$record->getMorphClass(), $field->getKey()],
                );
            }
        }

        return $query
            ->orderBy($table.'.name')
            ->limit(2000)
            ->pluck($table.'.id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }
}
