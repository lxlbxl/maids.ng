<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ClearOldLogsCommand extends Command
{
    protected $signature = 'log:clear-old 
                            {--days=30 : Number of days to keep logs}';

    protected $description = 'Clear log files older than specified days';

    public function handle()
    {
        $days = $this->option('days');
        $logPath = storage_path('logs');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Clearing logs older than {$days} days (before {$cutoffDate->format('Y-m-d')})...");

        $deletedCount = 0;
        $totalSize = 0;

        if (File::isDirectory($logPath)) {
            $files = File::files($logPath);

            foreach ($files as $file) {
                if ($file->getExtension() === 'log') {
                    $fileModified = Carbon::createFromTimestamp($file->getMTime());

                    if ($fileModified->lt($cutoffDate)) {
                        $fileSize = $file->getSize();
                        File::delete($file->getPathname());
                        $deletedCount++;
                        $totalSize += $fileSize;
                        $this->line("  Deleted: {$file->getFilename()} (" . $this->formatBytes($fileSize) . ")");
                    }
                }
            }
        }

        $this->newLine();
        $this->info("Cleanup complete! Deleted {$deletedCount} log files (" . $this->formatBytes($totalSize) . " freed)");

        return self::SUCCESS;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
