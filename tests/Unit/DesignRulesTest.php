<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The design system as a test (docs/DESIGN-SYSTEM.md). Screens are built from the shared
 * components in resources/js/Components/ui; these rules fail the build when a screen
 * hand-rolls one of them or bypasses the theme tokens.
 */
class DesignRulesTest extends TestCase
{
    private const JS = __DIR__.'/../../resources/js';

    /** Files allowed to define the primitives themselves. */
    private const PRIMITIVES = ['Components/ui/', 'Components/Charts/'];

    /** @return array<string, string> relative path => contents, for every screen file */
    private function screens(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::JS, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() === 'vue') {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(self::JS) + 1));
                $out[$rel] = file_get_contents($file->getPathname());
            }
        }

        return $out;
    }

    private function isPrimitive(string $path): bool
    {
        foreach (self::PRIMITIVES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> "path:line  code" for each line matching $pattern */
    private function offenders(string $pattern, ?callable $skipFile = null, array $allowLines = []): array
    {
        $found = [];
        foreach ($this->screens() as $path => $code) {
            if ($skipFile && $skipFile($path)) {
                continue;
            }
            foreach (explode("\n", $code) as $i => $line) {
                if (preg_match($pattern, $line) && ! array_filter($allowLines, fn ($a) => str_contains($line, $a))) {
                    $found[] = $path.':'.($i + 1).'  '.trim(mb_substr($line, 0, 140));
                }
            }
        }

        return $found;
    }

    public function test_key_figures_use_the_one_kpi_card(): void
    {
        // A big tabular number styled by hand is a KPI tile: use <KpiCard> (or <StatTile> inside a card).
        $found = $this->offenders('/class="[^"]*\b(text-(xl|2xl|3xl|4xl)|text-\[(1\.[2-9]|[2-9])[\d.]*rem\])\b[^"]*\btabular\b/', fn ($p) => $this->isPrimitive($p));
        $this->assertSame([], $found, "Hand-made KPI figures; use Components/ui/KpiCard or StatTile:\n".implode("\n", $found));

        $this->assertFileDoesNotExist(self::JS.'/Components/KpiCard.vue', 'There is one KPI card: Components/ui/KpiCard.vue.');
    }

    public function test_actions_use_the_one_button(): void
    {
        $found = $this->offenders('/\bbtn-coral\b/');
        $this->assertSame([], $found, "Hand-styled primary buttons; use Components/ui/UiButton:\n".implode("\n", $found));
    }

    public function test_text_and_surfaces_use_theme_tokens(): void
    {
        // text-navy / bg-white do not switch with the theme: use text-ink / bg-surface.
        $found = [
            ...$this->offenders('/\btext-navy\b/'),
            // The switch knob stays white; the theme previews in Profile show each theme's own colours.
            ...$this->offenders('/\bbg-white(?![\/\w-])/', fn ($p) => $p === 'Pages/Settings/Profile.vue', ['size-5 rounded-full bg-white shadow']),
        ];
        $this->assertSame([], $found, "Use theme tokens (text-ink, bg-surface):\n".implode("\n", $found));
    }

    public function test_screens_do_not_hard_code_colours(): void
    {
        // Colours come from tokens (CSS) or lib/palette.js (charts), never hex values in a screen.
        $found = $this->offenders('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b(?![0-9a-fA-F])/', fn ($p) => $p === 'Pages/Settings/Profile.vue' || $p === 'Pages/DesignSystem.vue');
        $this->assertSame([], $found, "Hard-coded colours; use tokens or lib/palette.js:\n".implode("\n", $found));
    }

    public function test_badges_and_icon_tiles_use_the_tone_scale(): void
    {
        // Tints paired with ad-hoc text colours drift apart; the tone scale keeps them together.
        $found = $this->offenders('/bg-(coral|peach|success)-tint text-\[/', fn ($p) => $this->isPrimitive($p));
        $this->assertSame([], $found, "Use Badge / IconTile tones (Components/ui/tones.js):\n".implode("\n", $found));
    }
}
