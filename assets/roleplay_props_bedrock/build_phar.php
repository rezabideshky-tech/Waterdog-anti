<?php
declare(strict_types=1);

// Run: php -d phar.readonly=0 build_phar.php
$source = __DIR__ . "/plugin/ArvanRoleplayProps";
$target = __DIR__ . "/ArvanRoleplayProps.phar";
if((bool) ini_get("phar.readonly")){
    throw new RuntimeException("Run PHP with -d phar.readonly=0");
}
if(is_file($target)){
    unlink($target);
}
$phar = new Phar($target);
$phar->startBuffering();
$phar->buildFromDirectory($source, '/\.(php|yml)$/');
$phar->setStub("<?php __HALT_COMPILER(); ?>\n");
$phar->setSignatureAlgorithm(Phar::SHA256);
$phar->stopBuffering();
$check = new Phar($target);
if($check->getSignature()["hash_type"] !== "SHA-256" || !isset($check["plugin.yml"])){
    throw new RuntimeException("PHAR verification failed");
}
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file){
    $relative = substr($file->getPathname(), strlen($source) + 1);
    if($check[$relative]->getContent() !== file_get_contents($file->getPathname())){
        throw new RuntimeException("PHAR content mismatch: " . $relative);
    }
}
echo "PASS: PHAR SHA-256 signature and all source entries verified\n";
