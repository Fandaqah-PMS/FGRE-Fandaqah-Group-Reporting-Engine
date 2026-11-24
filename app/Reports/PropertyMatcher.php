<?php

namespace App\Reports;

use App\Models\Property;
use App\Tenancy\GroupScope;
use Illuminate\Support\Collection;

/**
 * Decides which hotel a report belongs to.
 *
 * Only properties that allow-list the sender are candidates. Among those, the
 * code or name printed on the report and the hotel name in the email subject
 * pick the match. Anything ambiguous or contradictory returns null with a
 * reason and the document goes to review: a report is never guessed onto a hotel.
 *
 * Looks across all groups on purpose (GroupScope bypass): a document arrives
 * before anyone knows which group it belongs to.
 */
class PropertyMatcher
{
    /** Names shorter than this are not searched for inside subjects. */
    private const MIN_SUBJECT_NAME_LENGTH = 4;

    /** @return Collection<int, Property> */
    public function candidatesForSender(?string $fromEmail): Collection
    {
        if ($fromEmail === null || trim($fromEmail) === '') {
            return collect();
        }

        return Property::withoutGlobalScope(GroupScope::class)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Property $p) => $p->acceptsSender($fromEmail))
            ->values();
    }

    /**
     * @return array{0: ?Property, 1: ?string} [property, reason when no match]
     */
    public function match(DocumentHints $hints, ?string $printedCode, ?string $printedName): array
    {
        // Hotel-specific sources (MCP pulls) already know the hotel: only check the report agrees.
        if ($hints->propertyId !== null) {
            return $this->confirmKnown($hints->propertyId, $printedName);
        }

        $candidates = $this->candidatesForSender($hints->fromEmail);

        if ($candidates->isEmpty()) {
            return [null, "Sender [{$hints->fromEmail}] is not allow-listed for any active hotel."];
        }

        $byCode = $printedCode === null ? collect() : $candidates->filter(
            fn (Property $p) => $this->same($p->code, $printedCode) || $this->same($p->pms_property_ref, $printedCode)
        );
        $byName = $printedName === null ? collect() : $candidates->filter(
            fn (Property $p) => collect($p->knownNames())->contains(fn (string $n) => $this->nameMatches($n, $printedName))
        );
        $bySubject = $hints->subject === null ? collect() : $candidates->filter(
            fn (Property $p) => collect($p->knownNames())->contains(fn (string $n) => $this->subjectMentions($hints->subject, $n))
        );

        $pick = function (Collection $found) use ($byName, $bySubject): array {
            $property = $found->first();
            foreach (['printed name' => $byName, 'email subject' => $bySubject] as $source => $other) {
                if ($other->isNotEmpty() && ! $other->contains('id', $property->id)) {
                    return [null, "The {$source} points to a different hotel than [{$property->name}]."];
                }
            }

            return [$property, null];
        };

        if ($byCode->count() === 1) {
            return $pick($byCode);
        }

        if ($byName->count() === 1) {
            return $pick($byName);
        }

        if ($byName->count() > 1 && ($narrowed = $byName->intersectByKeys($bySubject))->count() === 1) {
            return [$narrowed->first(), null];
        }

        if ($printedName === null && $printedCode === null) {
            if ($bySubject->count() === 1) {
                return [$bySubject->first(), null];
            }
            if ($candidates->count() === 1) {
                return [$candidates->first(), null];
            }
        }

        if ($printedName !== null && $byName->isEmpty()) {
            return [null, sprintf(
                'Report is for hotel [%s], which is not a known name of any hotel allowed for sender [%s]. Add it as an alias if correct.',
                $printedName,
                $hints->fromEmail,
            )];
        }

        return [null, sprintf(
            'Could not tell which of %d hotels sharing sender [%s] this report is for (printed: %s).',
            $candidates->count(),
            $hints->fromEmail,
            $printedName ?? $printedCode ?? 'no hotel name',
        )];
    }

    /** @return array{0: ?Property, 1: ?string} */
    private function confirmKnown(int $propertyId, ?string $printedName): array
    {
        $property = Property::withoutGlobalScope(GroupScope::class)->where('is_active', true)->find($propertyId);
        if ($property === null) {
            return [null, "Hotel #{$propertyId} of this source is missing or inactive."];
        }

        if ($printedName !== null && ! collect($property->knownNames())->contains(fn (string $n) => $this->nameMatches($n, $printedName))) {
            return [null, "Source is set up for [{$property->name}] but the report is for [{$printedName}]. Add it as an alias if correct."];
        }

        return [$property, null];
    }

    private function same(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && $this->normalize($a) === $this->normalize($b);
    }

    private function nameMatches(string $known, string $printed): bool
    {
        $k = $this->normalize($known);
        $p = $this->normalize($printed);

        return $k !== '' && $p !== '' && ($k === $p || str_contains($p, $k) || str_contains($k, $p));
    }

    private function subjectMentions(string $subject, string $name): bool
    {
        $n = $this->normalize($name);

        return mb_strlen($n) >= self::MIN_SUBJECT_NAME_LENGTH && str_contains($this->normalize($subject), $n);
    }

    /** Lowercase, letters and digits only; Arabic letter variants folded (أإآ→ا, ة→ه, ى→ي). */
    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ـ' => '']);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }
}
