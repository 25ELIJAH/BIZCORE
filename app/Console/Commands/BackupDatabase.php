<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/** Dumps the database to a gzip file and removes dumps older than the retention period. */
class BackupDatabase extends Command
{
    protected $signature = 'bizcore:backup {--keep=14 : Days of backups to keep}';

    protected $description = 'Back up the database to storage/app/backups and prune old backups';

    public function handle(): int
    {
        $c = config('database.connections.'.config('database.default'));
        if (($c['driver'] ?? null) !== 'mysql') {
            $this->error('Backups are only supported for MySQL.');

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/bizcore-'.now()->format('Y-m-d_His').'.sql';

        $args = ['mysqldump', '--single-transaction', '--no-tablespaces', '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'], '--result-file='.$file];
        if (env('DB_SSL')) {
            $args[] = '--ssl-mode=REQUIRED';
        }
        $args[] = $c['database'];

        $process = new Process($args, null, ['MYSQL_PWD' => (string) $c['password']]);
        $process->setTimeout(600);
        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->error('Could not run mysqldump: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $process->isSuccessful()) {
            File::delete($file);
            $this->error('Backup failed: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        file_put_contents($file.'.gz', gzencode((string) file_get_contents($file), 9));
        File::delete($file);
        $this->info('Backup written: '.basename($file).'.gz');
        $this->prune($dir, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function prune(string $dir, int $days): void
    {
        foreach (File::files($dir) as $f) {
            if (str_starts_with($f->getFilename(), 'bizcore-') && $f->getMTime() < now()->subDays($days)->getTimestamp()) {
                File::delete($f->getPathname());
            }
        }
    }
}
