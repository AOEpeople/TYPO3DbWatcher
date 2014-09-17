#!/usr/bin/env php
<?php
$composerAutoloader = __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!file_exists($composerAutoloader)) {
    exit(
        PHP_EOL . 'This script requires the autoloader file created at install time by Composer. Looked for "' .
        $composerAutoloader . '" without success.'
    );
}
require $composerAutoloader;

$application = new \Symfony\Component\Console\Application();
$application->add(new \Aoe\TYPO3DbWatcher\Console\Command\TimestampCommand());
$application->run();