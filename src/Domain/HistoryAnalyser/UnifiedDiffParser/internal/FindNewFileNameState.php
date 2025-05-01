<?php

/**
 * Static Analysis Results Baseliner (sarb).
 *
 * (c) Dave Liddament
 *
 * For the full copyright and licence information please view the LICENSE file distributed with this source code.
 */

declare(strict_types=1);

namespace DaveLiddament\StaticAnalysisResultsBaseliner\Domain\HistoryAnalyser\UnifiedDiffParser\internal;

use DaveLiddament\StaticAnalysisResultsBaseliner\Domain\HistoryAnalyser\UnifiedDiffParser\NewFileName;
use DaveLiddament\StaticAnalysisResultsBaseliner\Domain\Utils\StringUtils;

/**
 * Looking for the New File name. Previous line of the diff will have contained the Original File name.
 *
 * If this refers to either an added or deleted file then ignore the Change Hunks and scan for next File Diff.
 */
class FindNewFileNameState implements State
{
    public const NEW_FILE = '+++ b/';
    public const NEW_FILE_WITH_UNICODE_ESCAPES = '+++ "b/';

    /**
     * @var FileMutationBuilder
     */
    private $fileMutationBuilder;

    /**
     * FindNewFileNameState constructor.
     */
    public function __construct(FileMutationBuilder $fileMutationBuilder)
    {
        $this->fileMutationBuilder = $fileMutationBuilder;
    }

    public function processLine(string $line): State
    {
        if (LineTypeDetector::isDeletedFile($line)) {
            return new FindFileDiffStartState($this->fileMutationBuilder->build());
        }

        // Pre-preocess in case where c style escape sequences are used
        if (StringUtils::startsWith(self::NEW_FILE_WITH_UNICODE_ESCAPES, $line)) {
            // Get the text between the double quotes
            // Value e.g, +++ "b/.build/DEVOPS-1066-high-plo\342\200\223001\342\200\223002-stored-cros-lf-test"
            preg_match('/"(.*)"/', $line, $matches);
            $lineInner = $matches[1] ?? throw new \RuntimeException('No match found');
            $decoded = stripcslashes($lineInner);
            $line = "+++ $decoded";
        }

        if (!StringUtils::startsWith(self::NEW_FILE, $line)) {
            throw DiffParseException::missingNewFileName($line);
        }

        $newFileName = StringUtils::removeFromStart(self::NEW_FILE, $line);
        $this->fileMutationBuilder->setNewFileName(new NewFileName($newFileName));

        if ($this->fileMutationBuilder->isAddedFile()) {
            return new FindFileDiffStartState($this->fileMutationBuilder->build());
        }

        return new FindChangeHunkStartState($this->fileMutationBuilder);
    }

    public function finish(): void
    {
        throw DiffParseException::missingNewFileName(DiffParseException::END_OF_FILE);
    }
}
