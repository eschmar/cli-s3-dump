<?php

namespace Eschmar;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Ifsnop\Mysqldump as IMysqldump;

/**
 * Command for dumping a mysql database and writing it to Amazon Glacier.
 *
 * @author Marcel Eschmann
 **/
class GlacierMysqlDumpCommand extends Command
{
    protected $timestamp_format = 'Y-m-d_H-i-s';
    protected $dump_dir = 'dumps/';

    /**
     * Command input configuration.
     *
     * @return void
     * @author Marcel Eschmann
     **/
    protected function configure()
    {
        $this
            ->setName('dump')
            ->setDescription('Dumps a MySQL database and write it to Amazon Glacier')
            ->addArgument('user', InputArgument::REQUIRED, 'Database username.')
            ->addArgument('pw', InputArgument::REQUIRED, 'Database password.')
            ->addArgument('db', InputArgument::REQUIRED, 'Database name.')
            ->addOption(
               'skip-glacier',
               null,
               InputOption::VALUE_NONE,
               'If set, the dump won\'t be written to Amazon Glacier'
            )
        ;
    }

    /**
     * Command execution.
     *
     * @return void
     * @author Marcel Eschmann
     **/
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = time();

        $user = $input->getArgument('user');
        $pw = $input->getArgument('pw');
        $pw = $pw == 'null' ? null : $pw;
        $db = $input->getArgument('db');

        $output->writeln("");
        $now = new \DateTime();
        $filename = $db . '_' . $now->format($this->timestamp_format) . '.sql';

        if (!is_dir($this->dump_dir)) {
            mkdir($this->dump_dir, 0777, true);
        }

        try {
            $dump = new IMysqldump\Mysqldump($db, $user, $pw, 'localhost', 'mysql', array(
                'compress' => 'Gzip'
            ));

            $dump->start($this->dump_dir . $filename);
        } catch (\Exception $e) {
            $output->writeln(" \033[1;31m[ERROR]: " . $e->getMessage());
        }

        if ($input->getOption('skip-glacier')) {
            $output->writeln(" \033[36mSkiping Glacier...");
        }

        $end = time();
        $elapsed = $end-$start;

        $output->writeln("\033[0;0m ...generated \033[36m$filename\033[0;0m in \033[36m$elapsed\033[0;0m seconds.");
    }

} // END class GlacierMysqlDumpCommand extends Command