<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\Company;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Illuminate\Support\Carbon;

/**
 * One click: "I called this property today."
 *
 * Stamps Last Contacted, moves the follow-up a week out, and only promotes the
 * outreach stage out of "not contacted" — a call must not knock a property back
 * from Under contract to Contacted.
 *
 * Values are written one field at a time. The bulk custom-field writer saves
 * every field and clears whatever is missing from its array, which would wipe
 * the phone numbers and BBLs of the record.
 */
final class LogCallAction extends Action
{
    public const int FOLLOW_UP_DAYS = 7;

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'logCall')
            ->label('Log call')
            ->tooltip('Log a call today and push the follow-up a week out')
            ->icon('heroicon-o-phone')
            ->color('gray')
            ->size(Size::Small)
            ->button()
            ->hiddenLabel()
            ->visible(fn ($livewire): bool => ($livewire?->getRecord() ?? null) instanceof Company)
            ->action(function ($livewire): void {
                $company = $livewire->getRecord();

                if (! $company instanceof Company) {
                    return;
                }

                $today = Carbon::today();
                $followUp = $today->copy()->addDays(self::FOLLOW_UP_DAYS);
                $fields = $company->customFields()->get()->keyBy('code');

                if ($last = $fields->get('last_contacted')) {
                    $company->saveCustomFieldValue($last, $today->toDateString());
                }

                if ($next = $fields->get('next_follow_up')) {
                    $company->saveCustomFieldValue($next, $followUp->toDateString());
                }

                if (($stage = $fields->get('outreach_stage')) && blank($company->getCustomFieldValue($stage))) {
                    $company->saveCustomFieldValue($stage, 'Contacted');
                }

                $company->refresh()->load('customFieldValues.customField.options');
                $livewire->dispatch('$refresh');

                Notification::make()
                    ->title('Call logged')
                    ->body('Last contacted '.$today->format('M j').', next follow-up '.$followUp->format('M j').'.')
                    ->success()
                    ->send();
            });
    }
}
