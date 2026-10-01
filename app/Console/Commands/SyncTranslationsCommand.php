<?php

namespace App\Console\Commands;

use App\Services\AIService;
use Illuminate\Console\Command;

class SyncTranslationsCommand extends Command
{
    protected $signature = 'app:sync-translations {--dry-run : Show missing keys without writing files}';

    protected $description = 'Scan lang files, find missing keys between locales, translate via AI, and write updated files.';

    public function handle(): int
    {
        $locales = ['pt_BR', 'es'];
        $files = [];

        foreach ($locales as $locale) {
            $path = lang_path("{$locale}/messages.php");
            $files[$locale] = file_exists($path) ? include $path : [];
        }

        $allKeys = array_unique(array_merge(array_keys($files['pt_BR']), array_keys($files['es'])));

        $missing = [];
        foreach ($allKeys as $key) {
            foreach ($locales as $locale) {
                if (!array_key_exists($key, $files[$locale])) {
                    $missing[$locale][] = $key;
                }
            }
        }

        $totalMissing = array_sum(array_map('count', $missing));

        if ($totalMissing === 0) {
            $this->info('All translation files are in sync. Nothing to do.');
            return self::SUCCESS;
        }

        foreach ($missing as $locale => $keys) {
            $this->warn("Missing in {$locale}: " . count($keys) . ' keys');
            foreach ($keys as $key) {
                $this->line("  - {$key}");
            }
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (!config('services.anthropic.key')) {
            $this->error('ANTHROPIC_API_KEY not set. Cannot auto-translate.');
            return self::FAILURE;
        }

        foreach ($missing as $locale => $keys) {
            $sourceLocale = $locale === 'es' ? 'pt_BR' : 'es';
            $sourceLang = $locale === 'es' ? 'Brazilian Portuguese' : 'Spanish (Latin America)';
            $targetLang = $locale === 'es' ? 'Spanish (Latin America)' : 'Brazilian Portuguese';

            foreach ($keys as $key) {
                $sourceValue = $files[$sourceLocale][$key] ?? null;

                if (!$sourceValue) {
                    $this->warn("  Skipping {$key} — no source value in {$sourceLocale}");
                    continue;
                }

                $this->line("  Translating [{$locale}] {$key}...");
                $translated = $this->translate($sourceValue, $sourceLang, $targetLang);

                if ($translated) {
                    $files[$locale][$key] = $translated;
                    $this->info("  ✓ {$key} = {$translated}");
                }
            }
        }

        if (!$this->option('dry-run')) {
            foreach ($locales as $locale) {
                $this->writeFile($locale, $files[$locale]);
                $this->info("Written: lang/{$locale}/messages.php (" . count($files[$locale]) . ' keys)');
            }
        }

        return self::SUCCESS;
    }

    private function translate(string $value, string $from, string $to): ?string
    {
        $translated = app(AIService::class)->text(
            'fast',
            'You translate user interface strings. Keep placeholders like :count untouched.',
            "Translate this UI string from {$from} to {$to}.\nString: \"{$value}\"\nReturn only the translation, no quotes, no explanation.",
            256,
        );

        if ($translated === '') {
            $this->error('  Translation failed (see the log).');
            return null;
        }

        return $translated;
    }

    private function writeFile(string $locale, array $keys): void
    {
        $path = lang_path("{$locale}/messages.php");
        $export = var_export($keys, true);
        file_put_contents($path, "<?php\n\nreturn {$export};\n");
    }
}
