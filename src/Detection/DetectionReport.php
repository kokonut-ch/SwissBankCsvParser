<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Detection;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Everything that recognised a file, best first.
 *
 * A ranked list rather than a yes/no, because more than one profile can
 * plausibly read the same file and the caller is better placed than this
 * package to settle it — usually by asking the person who uploaded it.
 *
 * @implements IteratorAggregate<int, Candidate>
 */
final readonly class DetectionReport implements Countable, IteratorAggregate
{
    /** @param list<Candidate> $candidates Sorted best first. */
    public function __construct(public array $candidates = []) {}

    public function best(): ?Candidate
    {
        return $this->candidates[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->candidates === [];
    }

    /**
     * True when one candidate stands clearly above the rest — a reasonable
     * signal for skipping a confirmation step in a UI.
     */
    public function isConfident(float $threshold = 0.6, float $margin = 0.15): bool
    {
        $best = $this->best();

        if ($best === null || $best->score < $threshold) {
            return false;
        }

        $runnerUp = $this->candidates[1] ?? null;

        return $runnerUp === null || ($best->score - $runnerUp->score) >= $margin;
    }

    public function count(): int
    {
        return count($this->candidates);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->candidates);
    }
}
