<?php

namespace Eschmar;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

/**
 * Command for generating a yaml config file.
 *
 * @author Marcel Eschmann
 **/
class GenerateConfigCommand extends Command
{
    /**
     * Config file name.
     *
     * @var string
     **/
    protected $config_file = 's3dump.yml';

    /**
     * Dummy config file content.
     *
     * @var array
     **/
    protected $dummy = [
        'database' => [
            [
                'user' => '',
                'password' => '',
                'name' => ''
            ]
        ],
        'aws' => [
            's3' => [
                'key' => '',
                'secret' => '',
                'bucket' => ''
            ]
        ]
    ];

    /**
     * Command input configuration.
     *
     * @return void
     * @author Marcel Eschmann
     **/
    protected function configure()
    {
        $this
            ->setName('generate-config')
            ->setDescription('Generates a yaml config file according to arguments.')
            ->addArgument('database', InputArgument::OPTIONAL, 'Database name.')
            ->addArgument('user', InputArgument::OPTIONAL, 'Database user name.')
            ->addArgument('password', InputArgument::OPTIONAL, 'Database user password.')
            ->addArgument('s3-key', InputArgument::OPTIONAL, 'Your AWS S3 key.')
            ->addArgument('s3-secret', InputArgument::OPTIONAL, 'Your AWS S3 secret.')
            ->addArgument('s3-bucket', InputArgument::OPTIONAL, 'Use this bucket as save location.')
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
        
        $this->dummy['database'][0]['user'] = $input->getArgument('user') ? $input->getArgument('user') : 'root';
        $this->dummy['database'][0]['password'] = $input->getArgument('password') ? $input->getArgument('password') : '';
        $this->dummy['database'][0]['name'] = $input->getArgument('database') ? $input->getArgument('database') : 'database';

        $this->dummy['aws']['s3']['key'] = $input->getArgument('s3-key') ? $input->getArgument('s3-key') : 's3-key';
        $this->dummy['aws']['s3']['secret'] = $input->getArgument('s3-secret') ? $input->getArgument('s3-secret') : 's3-secret';
        $this->dummy['aws']['s3']['bucket'] = $input->getArgument('s3-bucket') ? $input->getArgument('s3-bucket') : 's3-bucket';

        $dumper = new Dumper();
        $yaml = $dumper->dump($this->dummy);
        file_put_contents($this->config_file, $yaml);

        $output->writeln("\033[0;0m Generated file \033[36m{$this->config_file}\033[0;0m.");
    }

} // END class GenerateConfigCommand extends Command