<?php

require_once('vendor/autoload.php');
use Symfony\Component\Console\Application;
use Eschmar\GlacierMysqlDumpCommand;

$ascii = "\n\n" .
    "\033[1;34m  _______         _         \033[1;32m__  ___              _____                \n" .
    "\033[1;34m / ___/ /__ _____(_)__ ____\033[1;32m/  |/  /_ _____ ___ _/ / _ \\__ ____ _  ___ \n" .
    "\033[1;34m/ (_ / / _ `/ __/ / -_) __/\033[1;32m /|_/ / // (_-</ _ `/ / // / // /  ' \\/ _ \\\n" .
    "\033[1;34m\\___/_/\\_,_/\\__/_/\\__/_/\033[1;32m /_/  /_/\\_, /___/\\_, /_/____/\\_,_/_/_/_/ .__/\n" .
    "\033[1;34m                        \033[1;32m        /___/      /_/                 /_/    \n";

$console = new Application($ascii, '0.1');
$console->add(new GlacierMysqlDumpCommand);
$console->run();