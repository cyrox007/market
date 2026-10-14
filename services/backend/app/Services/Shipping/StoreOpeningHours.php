<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StoreOpeningHours
{
    public function validate(?array $hours, string $timezone): void
    {
        Validator::make(['opening_hours' => $hours, 'timezone' => $timezone], [
            'timezone' => 'required|timezone', 'opening_hours' => 'nullable|array|max:7',
            'opening_hours.*.day' => 'required|integer|between:1,7|distinct',
            'opening_hours.*.is_closed' => 'required|boolean', 'opening_hours.*.intervals' => 'present|array|max:5',
            'opening_hours.*.intervals.*.opens' => 'required|date_format:H:i',
            'opening_hours.*.intervals.*.closes' => 'required|date_format:H:i',
        ])->validate();
        foreach ($hours ?? [] as $day) {
            $previous = null;
            foreach (collect($day['intervals'])->sortBy('opens') as $interval) {
                if ($day['is_closed'] || $interval['opens'] >= $interval['closes'] || ($previous !== null && $interval['opens'] < $previous)) {
                    throw ValidationException::withMessages(['opening_hours' => 'Проверьте интервалы: закрытый день без интервалов, окончание позже начала, интервалы не пересекаются.']);
                }
                $previous = $interval['closes'];
            }
            if (! $day['is_closed'] && ! $day['intervals']) {
                throw ValidationException::withMessages(['opening_hours' => 'Для рабочего дня укажите хотя бы один интервал.']);
            }
        }
    }
}
