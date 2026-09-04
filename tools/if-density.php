<?php

declare(strict_types=1);

/**
 * IRD (If/Row Density) calculator.
 * Counts `if` statements in PHP files, excluding comments and strings.
 */

$dir = $argv[1] ?? __DIR__ . '/../app';
$dir = realpath($dir);

if ($dir === false || !is_dir($dir)) {
    fwrite(STDERR, "Error: directory not found: {$dir}\n");
    exit(1);
}

$ifCount = 0;
$totalLines = 0;
$filesProcessed = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $lines = file($file->getPathname());
    if ($lines === false) {
        continue;
    }

    $totalLines += count($lines);
    $ifCount += countIfStatements($lines);
    $filesProcessed++;
}

$density = $totalLines > 0 ? ($ifCount * 100 / $totalLines) : 0;

echo "📊 PHP IRD (If/Row Density)\n";
echo "  If count:      {$ifCount}\n";
echo "  Total lines:   {$totalLines}\n";
echo "  Files:         {$filesProcessed}\n";
echo sprintf("  IRD: %.2f%%\n", $density);

/**
 * Counts `if` statements in an array of lines,
 * ignoring comments and string literals.
 *
 * @param string[] $lines
 */
function countIfStatements(array $lines): int
{
    $count = 0;
    $inBlockComment = false;

    foreach ($lines as $line) {
        $cleanLine = stripCommentsAndStrings($line, $inBlockComment);
        $count += preg_match_all('/\bif\s*\(/', $cleanLine);
    }

    return $count;
}

/**
 * Removes comments and string contents from a PHP line.
 * String contents are replaced with spaces to preserve positions.
 *
 * @param string $line
 * @param bool $inBlockComment (by reference)
 */
function stripCommentsAndStrings(string $line, bool &$inBlockComment): string
{
    $result = '';
    $length = strlen($line);

    for ($i = 0; $i < $length; $i++) {
        if ($inBlockComment) {
            // Look for end of block comment
            if ($line[$i] === '*' && ($line[$i + 1] ?? '') === '/') {
                $inBlockComment = false;
                $result .= '  ';
                $i++;
            } else {
                $result .= ' ';
            }
            continue;
        }

        // Single-line comments
        if ($line[$i] === '/' && ($line[$i + 1] ?? '') === '/') {
            break;
        }
        if ($line[$i] === '#') {
            break;
        }

        // Start of block comment
        if ($line[$i] === '/' && ($line[$i + 1] ?? '') === '*') {
            $inBlockComment = true;
            $result .= '  ';
            $i++;
            continue;
        }

        // String literals
        if ($line[$i] === '"' || $line[$i] === "'") {
            $quote = $line[$i];
            $result .= ' ';
            $i++;

            while ($i < $length) {
                // Escaped character
                if ($line[$i] === '\\' && $i + 1 < $length) {
                    $result .= '  ';
                    $i += 2;
                    continue;
                }
                // End of string
                if ($line[$i] === $quote) {
                    $result .= ' ';
                    break;
                }
                $result .= ' ';
                $i++;
            }
            continue;
        }

        $result .= $line[$i];
    }

    return $result;
}
