<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;

class AppBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup 
                            {--db-only : Backup only database and uploads} 
                            {--restore= : Path to backup file to restore} 
                            {--encrypt : Encrypt the backup using GPG}
                            {--rclone : Upload to rclone remote}
                            {--remote= : Specific rclone remote name}
                            {--keep-days=0 : Number of days to keep local backups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'KidsStore Unified Backup Utility (Auto-switches between Windows/Linux scripts)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $os = PHP_OS_FAMILY;
        $this->info("Detected OS: {$os}");

        $dbOnly = $this->option('db-only');
        $restore = $this->option('restore');
        $encrypt = $this->option('encrypt');
        $rclone = $this->option('rclone');
        $remote = $this->option('remote') ?? config('shop.backup_rclone_remote', 'GD_FeeStore');
        $keepDays = $this->option('keep-days');

        if ($os === 'Windows') {
            return $this->runWindowsBackup($dbOnly, $restore, $encrypt, $rclone, $remote, $keepDays);
        } else {
            return $this->runLinuxBackup($dbOnly, $restore, $encrypt, $rclone, $remote, $keepDays);
        }
    }

    protected function handleRemoteDownload($filename, $remote)
    {
        if (file_exists($filename)) return $filename;

        $this->info("File not found locally. Checking rclone remote '{$remote}'...");
        
        // If it's just a filename, assume it's in the backups folder on remote
        $remotePath = "{$remote}:backups/" . basename($filename);
        $localPath = storage_path('app/' . basename($filename));

        $rcloneBinary = 'rclone';
        if (PHP_OS_FAMILY === 'Windows') {
            $possiblePath = 'C:\Users\nsanni\Downloads\rclone-v1.74.3-windows-amd64\rclone-v1.74.3-windows-amd64\rclone.exe';
            if (file_exists($possiblePath)) {
                $rcloneBinary = $possiblePath;
            }
        }

        $process = new SymfonyProcess([$rcloneBinary, 'copy', $remotePath, storage_path('app/'), '--progress']);
        $process->setTimeout(3600);
        $process->run();

        if ($process->isSuccessful() && file_exists($localPath)) {
            $this->info("Successfully downloaded from cloud to: {$localPath}");
            return $localPath;
        }

        $this->error("Could not find file '{$filename}' locally or on remote.");
        return null;
    }

    protected function runWindowsBackup($dbOnly, $restore, $encrypt, $rclone, $remote, $keepDays)
    {
        if ($restore) {
            $restore = $this->handleRemoteDownload($restore, $remote);
            if (!$restore) return 1;
        }

        $scriptPath = base_path('scripts/backup.ps1');
        
        $args = [
            "-ExecutionPolicy", "Bypass",
            "-File", $scriptPath,
            "-DbName", config('database.connections.mysql.database'),
            "-DbUser", config('database.connections.mysql.username'),
            "-KeepDays", $keepDays
        ];

        if ($dbOnly) $args[] = "-DbOnly";
        if ($restore) {
            $args[] = "-Restore";
            $args[] = "-RestoreFile";
            $args[] = $restore;
        }
        if ($encrypt) $args[] = "-Encrypt";
        if ($rclone) {
            $args[] = "-UseRclone";
            $args[] = "-RcloneRemote";
            $args[] = $remote;
        }

        $this->info("Invoking PowerShell Backup...");
        return $this->executeCommand('powershell.exe', $args);
    }

    protected function runLinuxBackup($dbOnly, $restore, $encrypt, $rclone, $remote, $keepDays)
    {
        if ($restore) {
            $restore = $this->handleRemoteDownload($restore, $remote);
            if (!$restore) return 1;
        }

        $scriptPath = base_path('scripts/backup.sh');
        
        $args = [
            $scriptPath,
            "--db-name", config('database.connections.mysql.database'),
            "--db-user", config('database.connections.mysql.username'),
            "--keep-days", $keepDays
        ];

        if ($dbOnly) $args[] = "--db-only";
        if ($restore) {
            $args[] = "--restore";
            $args[] = "--restore-file";
            $args[] = $restore;
        }
        if ($encrypt) $args[] = "--encrypt";
        if ($rclone) {
            $args[] = "--rclone-remote";
            $args[] = $remote;
        }

        $this->info("Invoking Bash Backup...");
        return $this->executeCommand('bash', $args);
    }

    protected function executeCommand($binary, $args)
    {
        // Using Symfony process for real-time output in artisan
        $process = new SymfonyProcess(array_merge([$binary], $args));
        $process->setTimeout(3600); // 1 hour timeout
        
        // Pass the standard input, output and error to the terminal
        // This is necessary for interactive prompts like MySQL or GPG passwords
        $process->setTty(SymfonyProcess::isTtySupported());
        if (!SymfonyProcess::isTtySupported()) {
            $process->setInput(STDIN);
        }

        $process->run(function ($type, $buffer) {
            if ($type === SymfonyProcess::ERR) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });

        return $process->getExitCode();
    }
}
