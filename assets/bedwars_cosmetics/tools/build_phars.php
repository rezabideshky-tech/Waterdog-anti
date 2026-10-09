<?php
declare(strict_types=1);
// php -d phar.readonly=0 tools/build_phars.php
$root = dirname(__DIR__);
$source = $root . "/plugins";
$count = 0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)) as $file){
    if($file->getExtension() !== "php"){ continue; }
    try{ token_get_all(file_get_contents($file->getPathname()), TOKEN_PARSE); }
    catch(ParseError $error){ throw new RuntimeException("PHP syntax: " . $file->getPathname() . ": " . $error->getMessage()); }
    ++$count;
}
echo "PASS: parsed $count PHP source files\n";
if((bool) ini_get("phar.readonly")){ throw new RuntimeException("Use -d phar.readonly=0"); }
@mkdir($root . "/compiled", 0755, true);
foreach(["BedWarsCore-lobby", "BedWarsCore-game", "BedWarsLobby", "BedWarsGame"] as $name){
    $target = $root . "/compiled/" . $name . ".phar";
    if(is_file($target)){ unlink($target); }
    $phar = new Phar($target);
    $phar->startBuffering();
    $phar->buildFromDirectory($source . "/" . $name);
    $phar->setStub("<?php __HALT_COMPILER(); ?>\n");
    $phar->setSignatureAlgorithm(Phar::SHA256);
    $phar->stopBuffering();
    $check = new Phar($target);
    if(!isset($check["plugin.yml"]) || $check->getSignature()["hash_type"] !== "SHA-256"){
        throw new RuntimeException("PHAR verification failed: " . $name);
    }
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . "/" . $name, FilesystemIterator::SKIP_DOTS)) as $file){
        $relative = substr($file->getPathname(), strlen($source . "/" . $name) + 1);
        if($check[$relative]->getContent() !== file_get_contents($file->getPathname())){
            throw new RuntimeException("PHAR mismatch: " . $relative);
        }
    }
    echo "PASS: " . $name . ".phar signature and source entries\n";
}
