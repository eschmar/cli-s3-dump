<?php

require_once('vendor/autoload.php');
use Eschmar\GenerateConfigCommand;
use Eschmar\S3DumpCommand;
use Symfony\Component\Console\Application;

$ascii = "\n" .
    "\033[0;34m  ___ ____\033[0;32m___                  \n" .
    "\033[0;34m / __|__ /\033[0;32m   \\ _  _ _ __  _ __ \n" .
    "\033[1;34m \\__ \\|_ \\\033[1;32m |) | || | '  \\| '_ \\\n" .
    "\033[1;34m |___/___/\033[1;32m___/ \\_,_|_|_|_| .__/\n" .
    "\033[1;34m          \033[1;32m               |_|   \n";

$console = new Application($ascii, '0.3.0');
$console->add(new S3DumpCommand);
$console->add(new GenerateConfigCommand);
$console->run();