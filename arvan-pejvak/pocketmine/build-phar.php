<?php

declare(strict_types=1);

$source = __DIR__;
$target = $argv[1] ?? (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'ArvanPejvak.phar');
$targetDirectory = dirname($target);

if (!is_dir($source) || !is_file($source . DIRECTORY_SEPARATOR . 'plugin.yml')) {
    fwrite(STDERR, "PocketMine plugin source or plugin.yml is missing.\n");
    exit(1);
}
if (!class_exists(Phar::class)) {
    fwrite(STDERR, "The PHP Phar extension is required to package the plugin.\n");
    exit(1);
}
if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
    fwrite(STDERR, "Could not create output directory: {$targetDirectory}\n");
    exit(1);
}
if (file_exists($target) && !unlink($target)) {
    fwrite(STDERR, "Could not replace existing PHAR: {$target}\n");
    exit(1);
}

try {
    $archive = new Phar($target);
    $archive->startBuffering();
    $archive->buildFromDirectory($source);
    $archive->setStub("<?php __HALT_COMPILER();");
    $archive->stopBuffering();
    unset($archive);

    $archive = new Phar($target);
    if (!$archive->offsetExists('plugin.yml') || !$archive->offsetExists('src/Arvan/Pejvak/PejvakPlugin.php')) {
        throw new RuntimeException('Required PocketMine plugin files are missing from the PHAR.');
    }
    printf("Built PocketMine plugin PHAR: %s (%d bytes)\n", $target, filesize($target));
} catch (Throwable $error) {
    if (file_exists($target)) unlink($target);
    fwrite(STDERR, "PHAR packaging failed: {$error->getMessage()}\n");
    exit(1);
}
