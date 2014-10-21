<?php

namespace Eschmar;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Ifsnop\Mysqldump as IMysqldump;
use Aws\S3\S3Client;

/**
 * Command for dumping a mysql database and writing it to Amazon S3.
 *
 * @author Marcel Eschmann
 **/
class S3DumpCommand extends Command
{
    /**
     * Config file name.
     *
     * @var string
     **/
    protected $config_file = 's3dump.yml';

    /**
     * Default timestamp format for filenames.
     *
     * @var string
     **/
    protected $timestamp_format = 'Y-m-d_H-i-s';

    /**
     * Default dump target location.
     *
     * @var string
     **/
    protected $dump_dir = 'dumps/';

    /**
     * Configuration read from config file.
     *
     * @var array
     **/
    protected $config;

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
            ->setDescription('Dumps a MySQL database and writes it to Amazon S3')
            ->addArgument('config', InputArgument::OPTIONAL, 'Use this yaml config file.')
            ->addArgument('location', InputArgument::OPTIONAL, 'Write dumps to this directory (with trailing slash).')
            ->addOption(
               'skip-s3',
               null,
               InputOption::VALUE_NONE,
               'If set, the dump will not be uploaded to Amazon S3 and remain in the target directory.'
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
        $output->writeln("");
        $start = time();

        if ($input->getArgument('config')) {
            $this->config_file = $input->getArgument('config');
        }

        if (!file_exists($this->config_file)) {
            $output->writeln(" \033[1;31m[ERROR]: No {$this->config_file} file found.");
            return;
        }

        try {
            $yaml = new Parser();
            $this->config = $yaml->parse(file_get_contents($this->config_file));
        } catch (\Exception $e) {
            $output->writeln(" \033[1;31m[ERROR]: Not able to parse {$this->config_file}. Please check for syntax errors.");
            return;
        }

        // check database configuration
        if (!isset($this->config['database']) || !$this->checkForKeys($this->config['database'], array('user', 'password', 'name'))) {
            $output->writeln(" \033[1;31m[ERROR]: Invalid database configuration. Check {$this->config_file}.");
            return;
        }

        // check amazon s3 configuration
        $s3 = !$input->getOption('skip-s3');
        if ($s3 && (!isset($this->config['aws']['s3']) || !$this->checkForKeys($this->config['aws']['s3'], array('key', 'secret', 'bucket')))) {
            $output->writeln(" \033[1;31m[ERROR]: Invalid amazon s3 configuration. Check {$this->config_file}.");
            return;
        }

        if ($input->getArgument('location')) {
            $this->dump_dir = $input->getArgument('location');
        }

        // check if target directory exists
        if (!is_dir($this->dump_dir)) {
            mkdir($this->dump_dir, 0777, true);
        }

        // generate filename
        $now = new \DateTime();
        $filename = $this->config['database']['name'] . '_' . $now->format($this->timestamp_format) . '.sql';

        // execute mysql dump
        try {
            $dump = new IMysqldump\Mysqldump($this->config['database']['name'], $this->config['database']['user'], $this->config['database']['password'], 'localhost', 'mysql', array(
                'compress' => 'Gzip'
            ));

            $dump->start($this->dump_dir . $filename);
        } catch (\Exception $e) {
            $output->writeln(" \033[1;31m[ERROR]: " . $e->getMessage());
            return;
        }

        // file was compressed and has new extension
        $filename .= '.gz';

        $end = time();
        $elapsed = $end-$start;
        $output->writeln("\033[0;0m ...generated \033[36m{$this->dump_dir}$filename\033[0;0m in \033[36m$elapsed\033[0;0m seconds.");

        // write dump to amazon s3 if chosen
        if ($s3) {
            if (!is_file($this->dump_dir.$filename)) {
                $output->writeln(" \033[1;31m[ERROR]: Unable to find dumped file.");
                return;
            }

            try {
                $client = S3Client::factory(array(
                    'key'    => $this->config['aws']['s3']['key'],
                    'secret' => $this->config['aws']['s3']['secret']
                ));

                $result = $client->putObject([
                    'Key' => $this->config['database']['name'] . '/' . $filename,
                    'Bucket' => $this->config['aws']['s3']['bucket'],
                    'Body' => fopen($this->dump_dir.$filename, 'r'),
                    'ContentType' => 'application/gzip'
                ]);

                $output->writeln("\033[0;0m ...wrote \033[36m$filename\033[0;0m to \033[36mS3\033[0;0m.");
            } catch (\Exception $e) {
                $output->writeln(" \033[1;31m[AWS ERROR]: " . $e->getMessage());
                $output->writeln(" \033[36mSkipping S3...");
            }
        }

        // delete temporary dumps directory
        if ($s3 && !$this->destroyDir($this->dump_dir)) {
            $output->writeln(" \033[1;31m[ERROR]: Unable to delete temporary folder.");
        }
    }

    /**
     * Batch check for array keys.
     *
     * @return boolean
     * @author Marcel Eschmann
     **/
    protected function checkForKeys($haystack, $needles)
    {
        foreach ($needles as $needle) {
            if (!array_key_exists($needle, $haystack)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deletes a folder and all of its contents
     *
     * @return boolean
     * @author Marcel Eschamnn
     **/
    protected function destroyDir($path)
    {
        if (!is_dir($path)) {
            return false;
        }

        try {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if (!in_array($file->getBasename(), ['.', '..'])) {
                    if ($file->isDir()) {
                        $this->destroyDir($file->getPathname());
                    }else {
                        unlink($file->getPathname());
                    }
                }
            }
        } catch (\Exception $e) {
            return false;
        }
    }

} // END class S3DumpCommand extends Command