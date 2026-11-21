<?php

namespace App\Reports;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Decides which hotel a report belongs to.
 *
 * Only properties that allow-list the sender are candidates. Among those, the
 * property code or name printed on the report picks the match. Anything
 * ambiguous returns null with a reason, and the document goes to review:
 * a report is never guessed onto a hotel.
 */
class PropertyMatcher
{
    /** @return Collection<int, Property> */
    public function candidatesForSender(?string $fromEmail): Collection
    {
        if ($fromEmail === null || trim($fromEmail) === '') {
            return collect();
        }

        return Property::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Property $p) => $p->acceptsSender($fromEmail))
            ->values();
    }

    /**
     * @return array{0: ?Property, 1: ?string} [property, reason when no match]
     */
    public function match(?string $fromEmail, ?string $printedCode, ?string $printedName): array
    {
        $candidates = $this->candidatesForSender($fromEmail);

        if ($candidates->isEmpty()) {
            return [null, "Sender [{$fromEmail}] is not allow-listed for any active property."];
        }

        $byCode = $printedCode === null ? collect() : $candidates->filter(
            fn (Property $p) => $this->same($p->code, $printedCode) || $this->same($p->pms_property_ref, $printedCode)
        );

        $byName = $printedName === null ? collect() : $candidates->filter(
            fn (Property $p) => collect([$p->name, ...($p->aliases ?? [])])
                ->contains(fn (?string $n) => $this->nameMatches($n, $printedName))
        );

        if ($byCode->count() === 1) {
            $property = $byCode->first();
            // A printed name that clearly points at a *different* candidate is a conflict.
            if ($byName->isNotEmpty() && ! $byName->contains('id', $property->id)) {
                return [null, "Printed code [{$printedCode}] and name [{$printedName}] point to different hotels."];
            }

            return [$property, null];
        }

        if ($byName->count() === 1) {
            return [$byName->first(), null];
        }

        if ($candidates->count() === 1 && $printedCode === null && $printedName === null) {
            return [$candidates->first(), null];
        }

        if ($candidates->count() === 1) {
            return [null, sprintf(
                'Report shows hotel [%s] but its sender belongs to [%s].',
                $printedName ?? $printedCode,
                $candidates->first()->name,
            )];
        }

        return [null, sprintf(
            'Could not tell which of %d hotels sharing sender [%s] this report is for (printed: %s).',
            $candidates->count(),
            $fromEmail,
            $printedName ?? $printedCode ?? 'no hotel name',
        )];
    }

    private function same(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && $this->normalize($a) === $this->normalize($b);
    }

    private function nameMatches(?string $known, string $printed): bool
    {
        if ($known === null) {
            return false;
        }

        $k = $this->normalize($known);
        $p = $this->normalize($printed);

        return $k !== '' && $p !== '' && ($k === $p || Str::contains($p, $k) || Str::contains($k, $p));
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value)) ?? '';
    }
}
