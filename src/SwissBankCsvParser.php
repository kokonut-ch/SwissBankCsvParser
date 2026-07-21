<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser;

use Kokonut\SwissBankCsvParser\Contracts\BankProfile;
use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Csv\Reader;
use Kokonut\SwissBankCsvParser\Detection\Candidate;
use Kokonut\SwissBankCsvParser\Detection\DetectionReport;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\ParsedFile;
use Kokonut\SwissBankCsvParser\Exceptions\UnreadableFileException;
use Kokonut\SwissBankCsvParser\Exceptions\UnsupportedFileException;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

/**
 * Reads a CSV account statement from a Swiss bank and reports what is in it.
 *
 * That is the whole remit. It identifies the exporting bank, maps the columns,
 * and hands back dates, labels and exact decimal amounts. What those rows mean,
 * and what should happen to them, is the caller's business.
 */
final class SwissBankCsvParser
{
    public function __construct(
        private readonly ProfileRegistry $profiles = new ProfileRegistry,
    ) {}

    /**
     * @throws UnreadableFileException when the file holds no data
     * @throws UnsupportedFileException when no profile recognises it
     */
    public function parse(string $contents): ParsedFile
    {
        $document = Reader::read($contents);

        if ($document->isEmpty()) {
            throw UnreadableFileException::empty();
        }

        $ranked = $this->rank($document);

        if ($ranked === []) {
            throw UnsupportedFileException::notRecognised();
        }

        [$profile, $match] = $ranked[0];

        return $profile->extract($document, $match);
    }

    /**
     * @throws UnreadableFileException when the file is missing or unreadable
     * @throws UnsupportedFileException when no profile recognises it
     */
    public function parseFile(string $path): ParsedFile
    {
        if (! is_file($path)) {
            throw UnreadableFileException::missing($path);
        }

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw UnreadableFileException::unreadable($path);
        }

        return $this->parse($contents);
    }

    /**
     * Everything that recognises the file, best first, without extracting a
     * single row. Cheap enough to run on upload, and it gives a UI what it needs
     * to say "this looks like X" and to offer the runners-up.
     */
    public function detect(string $contents): DetectionReport
    {
        $document = Reader::read($contents);

        if ($document->isEmpty()) {
            return new DetectionReport;
        }

        return new DetectionReport(array_map(
            static fn (array $entry): Candidate => new Candidate(
                bank: $entry[0]->identity(),
                profile: $entry[0]->id(),
                score: $entry[1]->score,
                reasons: $entry[1]->reasons,
            ),
            $this->rank($document),
        ));
    }

    /**
     * Whether {@see parse()} would succeed.
     *
     * The generic fallback counts, so true means "readable", not "recognised as
     * a known bank". Use {@see detect()} when that distinction matters.
     */
    public function supports(string $contents): bool
    {
        return ! $this->detect($contents)->isEmpty();
    }

    public function profiles(): ProfileRegistry
    {
        return $this->profiles;
    }

    /**
     * Sorted by confidence, then by profile priority so ties resolve the same
     * way on every run.
     *
     * @return list<array{0: BankProfile, 1: ProfileMatch}>
     */
    private function rank(CsvDocument $document): array
    {
        $ranked = [];

        foreach ($this->profiles->all() as $profile) {
            $match = $profile->match($document);

            if ($match !== null) {
                $ranked[] = [$profile, $match];
            }
        }

        usort(
            $ranked,
            static fn (array $a, array $b): int => $b[1]->score <=> $a[1]->score
                ?: $a[0]->priority() <=> $b[0]->priority(),
        );

        return $ranked;
    }
}
