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
     * Default local dump target location.
     *
     * @var string
     **/
    protected $dump_dir = 'dumps/';

    /**
     * Bucket target location.
     *
     * @var string
     **/
    protected $bucket_dir;

    /**
     * Configuration read from config file.
     *
     * @var array
     **/
    protected $config;

    /**
     * CLI Output.
     *
     * @var OutputInterface
     **/
    protected $output;

    /**
     * Queue of databases to dump.
     *
     * @var array
     **/
    protected $queue;

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
            ->addOption(
               'bucket-dir',
               null,
               InputOption::VALUE_OPTIONAL,
               'Where should the dumps be stored (e.g. "dumps/")?',
               ''
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
        $this->output = $output;
        $this->bucket_dir = $input->getOption('bucket-dir');
        $output->writeln("");

        // make sure the bucket dir has a trailing slash
        if ($this->bucket_dir != '') {
            $this->bucket_dir = rtrim($this->bucket_dir, '/\\') . '/';
        }

        if ($input->getArgument('config')) {
            $this->config_file = $input->getArgument('config');
        }

        if (!file_exists($this->config_file)) {
            $output->writeln(" \033[1;31m[ERROR]: No {$this->config_file} file found.\033[0;0m");
            return;
        }

        try {
            $yaml = new Parser();
            $this->config = $yaml->parse(file_get_contents($this->config_file));
        } catch (\Exception $e) {
            $output->writeln(" \033[1;31m[ERROR]: Not able to parse {$this->config_file}. Please check for syntax errors.\033[0;0m");
            return;
        }

        // check database configuration
        if (!$this->generateQueue()) {
            $output->writeln(" \033[1;31m[ERROR]: Invalid database configuration. Check {$this->config_file}.\033[0;0m");
            return;
        }

        // check amazon s3 configuration
        $s3 = !$input->getOption('skip-s3');
        if ($s3 && (!isset($this->config['aws']['s3']) || !$this->checkForKeys($this->config['aws']['s3'], array('key', 'secret', 'bucket')))) {
            $output->writeln(" \033[1;31m[ERROR]: Invalid amazon s3 configuration. Check {$this->config_file}.\033[0;0m");
            return;
        }

        if ($input->getArgument('location')) {
            $this->dump_dir = $input->getArgument('location');
        }

        // check if target directory exists
        if (!is_dir($this->dump_dir)) {
            mkdir($this->dump_dir, 0777, true);
        }

        // execute dump for each database
        foreach ($this->queue as $db) {
            $this->dump($db, $s3);
        }

        // delete temporary dumps directory
        if ($s3 && !$this->destroyDir($this->dump_dir)) {
            $output->writeln(" \033[1;31m[ERROR]: Unable to delete temporary folder.\033[0;0m");
        }
    }

    /**
     * Parses the yaml database config and fills the queue.
     *
     * @return boolean
     * @author Marcel Eschmann
     **/
    protected function generateQueue()
    {
        if (!isset($this->config['database'])) {
            return false;
        }

        foreach ($this->config['database'] as $config) {
            if (!$this->checkForKeys($config, array('user', 'password', 'name'))) {
                return false;
            }

            $this->queue[] = $config;
        }

        return true;
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

        return rmdir($path);
    }

    /**
     * Dumps a single database.
     *
     * @return boolean
     * @author Marcel Eschmann
     **/
    protected function dump($db, $s3 = true)
    {
        $start = time();

        // generate filename
        $now = new \DateTime();
        $filename = $db['name'] . '_' . $now->format($this->timestamp_format) . '.sql.gz';

        // execute mysql dump
        try {
            $dump = new IMysqldump\Mysqldump(
                $db['name'],
                $db['user'],
                $db['password'],
                'localhost',
                'mysql',
                ['compress' => 'Gzip']
            );

            $dump->start($this->dump_dir . $filename);

        } catch (\Exception $e) {
            $this->output->writeln(" \033[1;31m[ERROR]: " . $e->getMessage() . ".\033[0;0m");
            return false;
        }

        $end = time();
        $elapsed = $end-$start;
        $this->output->writeln("\033[0;0m ...generated \033[36m{$this->dump_dir}$filename\033[0;0m in \033[36m$elapsed\033[0;0m seconds.");

        // write dump to amazon s3 if chosen
        if ($s3) {
            if (!is_file($this->dump_dir.$filename)) {
                $this->output->writeln(" \033[1;31m[ERROR]: Unable to find dumped file.\033[0;0m");
                return false;
            }

            try {
                $client = S3Client::factory(array(
                    'key'    => $this->config['aws']['s3']['key'],
                    'secret' => $this->config['aws']['s3']['secret']
                ));

                $result = $client->putObject([
                    'Key' => $this->bucket_dir . $db['name'] . '/' . $filename,
                    'Bucket' => $this->config['aws']['s3']['bucket'],
                    'Body' => fopen($this->dump_dir.$filename, 'r'),
                    'ContentType' => 'application/gzip'
                ]);

                $this->output->writeln("\033[0;0m ...wrote \033[36m$filename\033[0;0m to \033[36mS3\033[0;0m.");

            } catch (\Exception $e) {
                $this->output->writeln(" \033[1;31m[AWS ERROR]: " . $e->getMessage());
                $this->output->writeln(" \033[36mSkipping S3...\033[0;0m");
            }
        }
    }

} // END class S3DumpCommand extends Command