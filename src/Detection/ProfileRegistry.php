<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Detection;

use Kokonut\SwissBankCsvParser\Contracts\BankProfile;
use ReflectionClass;

/**
 * The profiles the parser will try.
 *
 * Profiles are found by scanning banks/, one directory per bank, rather than
 * being listed anywhere. That is deliberate: adding a bank must touch only that
 * bank's directory, so two contributors adding two banks never collide, and a
 * pull request adding a bank is readable in one screen.
 */
final class ProfileRegistry
{
    /**
     * Class names are resolved once per process; instances are not shared, so a
     * caller can hold two registries with different contents.
     *
     * @var list<class-string<BankProfile>>|null
     */
    private static ?array $discovered = null;

    /** @var list<BankProfile> */
    private array $profiles;

    /**
     * @param  list<BankProfile>|null  $profiles  Pass a list to bypass discovery entirely,
     *                                            which is what tests for a single bank do.
     */
    public function __construct(?array $profiles = null)
    {
        $this->profiles = $profiles ?? array_map(
            static fn (string $class): BankProfile => new $class,
            self::discover(),
        );

        $this->sort();
    }

    public function register(BankProfile $profile): self
    {
        $this->profiles[] = $profile;
        $this->sort();

        return $this;
    }

    /** @return list<BankProfile> */
    public function all(): array
    {
        return $this->profiles;
    }

    public function get(string $id): ?BankProfile
    {
        foreach ($this->profiles as $profile) {
            if ($profile->id() === $id) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * Every bank key the registry can recognise, deduplicated and sorted.
     *
     * @return list<string>
     */
    public function banks(): array
    {
        $keys = [];

        foreach ($this->profiles as $profile) {
            $keys[$profile->identity()->key] = true;
        }

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }

    private function sort(): void
    {
        usort(
            $this->profiles,
            static fn (BankProfile $a, BankProfile $b): int => $a->priority() <=> $b->priority()
                ?: strcmp($a->id(), $b->id()),
        );
    }

    /** @return list<class-string<BankProfile>> */
    private static function discover(): array
    {
        if (self::$discovered !== null) {
            return self::$discovered;
        }

        $files = glob(dirname(__DIR__, 2).'/banks/*/*Profile.php');
        $classes = [];

        foreach ($files === false ? [] : $files as $file) {
            $class = 'Kokonut\\SwissBankCsvParser\\Banks\\'
                .basename(dirname($file)).'\\'
                .basename($file, '.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->implementsInterface(BankProfile::class)) {
                continue;
            }

            /** @var class-string<BankProfile> $class */
            $classes[] = $class;
        }

        sort($classes);

        return self::$discovered = $classes;
    }
}
