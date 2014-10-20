<?php

require_once('vendor/autoload.php');
use Symfony\Component\Console\Application;
use Eschmar\GlacierDumpCommand;

$ascii = "\n" .
    "\033[0;34m  ______        _______ _______ _____ _______  ______ \033[0;32m______  _     _ _______  _____ \n" .
    "\033[0;34m |  ____ |      |_____| |         |   |______ |_____/ \033[0;32m|     \\ |     | |  |  | |_____]\n" .
    "\033[1;34m |_____| |_____ |     | |_____  __|__ |______ |    \\_\033[1;32m |_____/ |_____| |  |  | |      \n\n";

$console = new Application($ascii, '0.1');
$console->add(new GlacierDumpCommand);
$console->run();