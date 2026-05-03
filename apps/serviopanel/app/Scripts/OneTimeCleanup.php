<?php

namespace App\Scripts;

/**
 * Handles one-time cleanup tasks such as deleting legacy directories.
 * You can add more paths to the $targets list in run() when needed.
 * All completed cleanups are recorded in a single flag file (JSON).
 */
class OneTimeCleanup
{
    /** Single flag file that stores all completed cleanup entries (path => status). */
    private const FLAG_FILE = 'one_time_cleanup_done.json';

    /**
     * Entry point for running all one-time cleanups.
     * Add new entries to the $targets array to support more paths.
     */
    public static function run(): void
    {
        // list of cleanups that should run only once
        // each item: "relative" => path relative to APPPATH (used as key in the single flag file)
        $targets = [
            ['relative' => 'Controllers/api'],
            ['relative' => 'Controllers/partner/api'],
            ['relative' => 'Controllers/Apis/Customer/LanguageApiController.php'],
            ['relative' => 'Jobs/BookingNotification.php'],
            ['relative' => 'Jobs/ChatNotification.php'],
            ['relative' => 'Jobs/NumberLoggerJob.php'],
            ['relative' => 'Scripts/cleanup_migrations_once.php'],
            ['relative' => 'Controllers/Apis/Provider/V1.php'],
            ['relative' => 'Controllers/Apis/Customer/V1.php'],
            ['relative' => 'Controllers/Webhooks/Webhooks.php'],
            ['relative' => 'Models/CustomIonAuthModel.php'],
            // you can add more entries here in future, for example:
            // ['relative' => 'Controllers/OldSomething'],
        ];

        foreach ($targets as $target) {
            self::deletePathOnce($target['relative']);
        }
    }

    /**
     * Reads the single flag file and returns completed entries as [ path => status ].
     * Returns empty array if file missing or invalid.
     */
    private static function readCompletedEntries(): array
    {
        $flagFile = WRITEPATH . self::FLAG_FILE;
        if (!is_file($flagFile)) {
            return [];
        }
        $raw = @file_get_contents($flagFile);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Marks one entry as completed in the single flag file.
     * Merges with existing entries and writes with LOCK_EX for safe concurrent use.
     */
    private static function markEntryCompleted(string $relativePath, string $status): void
    {
        $flagFile = WRITEPATH . self::FLAG_FILE;
        $entries = self::readCompletedEntries();
        $entries[$relativePath] = $status;
        @file_put_contents($flagFile, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * Deletes a file OR directory (relative to APPPATH) exactly once.
     * Completion is recorded in the single flag file (all entries in one place).
     * Works for any entry in the $targets list regardless of type.
     */
    private static function deletePathOnce(string $relativePath): void
    {
        try {
            $entries = self::readCompletedEntries();
            // if this path is already in the flag file, skip
            if (isset($entries[$relativePath])) {
                return;
            }

            // build the absolute path from APPPATH + the relative path
            $targetPath = APPPATH . $relativePath;

            // resolve the real filesystem path (returns false if path does not exist)
            $resolvedTarget = realpath($targetPath);

            if ($resolvedTarget === false) {
                // path is already gone; record in the single flag file so we never retry
                self::markEntryCompleted($relativePath, 'already_missing:' . date('c'));
                return;
            }

            // safety check: make sure the resolved path is still inside the app folder
            $appRoot = realpath(APPPATH);
            if ($appRoot === false) {
                return;
            }

            if (strpos($resolvedTarget, $appRoot . DIRECTORY_SEPARATOR) !== 0) {
                // resolved path escaped outside app root — refuse to delete
                return;
            }

            // confirm the last segment of the resolved path matches what we expect
            // this prevents accidental deletions caused by symlinks or renamed parents
            $expectedLastSegment = basename(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
            $actualLastSegment   = basename($resolvedTarget);
            if ($expectedLastSegment !== $actualLastSegment) {
                // path segment mismatch — do nothing
                return;
            }

            // handle files and directories separately
            if (is_file($resolvedTarget)) {
                // single file — just unlink it
                @unlink($resolvedTarget);
            } elseif (is_dir($resolvedTarget)) {
                // directory — remove recursively
                self::deleteDirectoryRecursively($resolvedTarget);
            }

            // record in the single flag file so this cleanup never runs again for this path
            self::markEntryCompleted($relativePath, 'deleted:' . date('c'));
        } catch (\Throwable $e) {
            // log any error but do not break the normal request flow
            if (function_exists('log_message')) {
                log_message('error', '[OneTimeCleanup] ' . $e->getMessage());
            }
        }
    }

    /**
     * Recursively deletes a directory and all its contents.
     * Assumes path safety validation has already been performed.
     */
    private static function deleteDirectoryRecursively(string $directoryPath): void
    {
        if (!is_dir($directoryPath)) {
            return;
        }

        $items = scandir($directoryPath);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $directoryPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($fullPath)) {
                self::deleteDirectoryRecursively($fullPath);
            } else {
                @unlink($fullPath);
            }
        }

        @rmdir($directoryPath);
    }
}

