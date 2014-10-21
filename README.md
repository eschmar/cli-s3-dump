# cli-s3-dump
This is a simple console application for [generating mysql dumps](https://github.com/ifsnop/mysqldump-php) and storing them in an [Amazon S3 Bucket](https://github.com/aws/aws-sdk-php) via PHP. With the integration of Amazon Glacier in S3 you can easily move older dumps to the low-cost archive storage service while having recent dumps at your fingertips. That's why I built this tool.

## requirements
* php >5.4
* php-cli
* Amazon S3 Bucket

## installation
1. Get the phar:

    ```
    $ curl -LSs https://github.com/eschmar/cli-s3-dump/releases/download/v0.1/s3dump.phar
    ```

2. Write your credentials into a yaml config file. You can use ``d3dump_example.yml`` as a starting point.

*You may also use the tool without the phar binary. Just fork/clone/download the project, install all dependencies using composer and you're all set.*

## usage
```sh
$ php s3dump.phar dump [--skip-s3] [config] [location]
```

option|default value|description
---|---|---
``--skip-s3``|-|If set, the dump will not be uploaded to Amazon S3 and remain in the target directory.
``config``|``'s3dump.yml'``|YAML config file location. See s3dump_example.yml.
``location``|``'dumps/'``|Directory to write temporary dumps to (with trailing slash).

## phar package
You can use [Box](https://github.com/box-project/box2) to generate your own phar package. The advantage is, that you only have to put 2 files on your webserver: ``s3dump.phar`` and ``s3dump.yml`` (containing your credentials). Simply run:

```sh
$ curl -LSs http://box-project.org/installer.php | php
$ php box.phar build -v
```
The first line will install box and the second will build the actual binary. Please check the [project website](https://github.com/box-project/box2) for more information.

## credits
Uses...
* [MySQLDump - PHP](https://github.com/ifsnop/mysqldump-php) for generating the dumps.
* [AWS SDK for PHP](https://github.com/aws/aws-sdk-php) for S3 integration.
* [Symfony Console](https://github.com/symfony/Console) for the command line interface.
* [Symfony Yaml](https://github.com/symfony/Yaml) for config parsing.

## license
MIT License. Please check the dependencies for their licensing.