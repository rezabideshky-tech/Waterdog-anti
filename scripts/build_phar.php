<?php

declare(strict_types=1);

/**
 * Build script to generate AntiAdvertising.phar using PHP CLI:
 * php -d phar.readonly=0 scripts/build_phar.php
 */

$root = dirname(__DIR__);
$pharPath = $root . DIRECTORY_SEPARATOR . "AntiAdvertising.phar";

if (file_exists($pharPath)) {
	unlink($pharPath);
}

$phar = new Phar($pharPath);
$phar->setStub('<?php __HALT_COMPILER(); ?>' . "\r\n");
$phar->setSignatureAlgorithm(Phar::SHA1);
$phar->startBuffering();

$phar->addFile($root . "/plugin.yml", "plugin.yml");

foreach (["resources", "src"] as $dir) {
	$fullDir = $root . DIRECTORY_SEPARATOR . $dir;
	if (!is_dir($fullDir)) {
		continue;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($fullDir, FilesystemIterator::SKIP_DOTS)
	);
	foreach ($iterator as $file) {
		/** @var SplFileInfo $file */
		if ($file->isFile()) {
			$relative = str_replace("\\", "/", substr($file->getPathname(), strlen($root) + 1));
			$phar->addFile($file->getPathname(), $relative);
		}
	}
}

$phar->stopBuffering();
echo "[OK] Built AntiAdvertising.phar (" . filesize($pharPath) . " bytes)\n";
