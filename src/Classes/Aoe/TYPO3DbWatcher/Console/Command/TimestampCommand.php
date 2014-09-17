<?php
namespace Aoe\TYPO3DbWatcher\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package Aoe\TYPO3DbWatcher\Console\Command
 */
class TimestampCommand extends Command
{
    /**
     * configures the command
     */
    protected function configure()
    {
        $this->setName('Timestamp')->setDescription('Check DB timestamps for consistence.');

        $this->addOption(
            'user',
            'u',
            InputOption::VALUE_REQUIRED,
            'db user',
            'root'
        );

        $this->addOption(
            'pass',
            'p',
            InputOption::VALUE_REQUIRED,
            'db pass'
        );

        $this->addOption(
            'host',
            'H',
            InputOption::VALUE_OPTIONAL,
            'db host',
            'localhost'
        );

        $this->addOption(
            'database',
            'D',
            InputOption::VALUE_REQUIRED,
            'db name'
        );

        $this->addOption(
            'port',
            'P',
            InputOption::VALUE_OPTIONAL,
            'db port',
            3306
        );

        $this->addOption(
            'fields',
            'f',
            InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            'db fields to be checked',
            array('tstamp', 'crdate')
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     * @return string
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = mysqli_connect(
            $input->getOption('host'),
            $input->getOption('user'),
            $input->getOption('pass'),
            $input->getOption('database'),
            $input->getOption('port')
        );
        if (false === $connection) {
            throw new \Exception(mysqli_error($connection));
        }
        foreach ($this->getTables($connection) as $table) {
            $table = $table[0];
            foreach ($input->getOption('fields') as $field) {
                if (false === $this->hasColumnInTable($connection, $field, $table)) {
                    $output->writeln("<info>Table {$table} has no {$field}</info>");
                    continue;
                }
                $warnings = $this->getRowsWithInvalidTimestamp($connection, $field, $table);
                if (count($warnings) > 0) {
                    $output->writeln(
                        "<error>found " . count($warnings) . " warnings for table {$table} in column {$field}</error>"
                    );
                    foreach ($warnings as $warning) {
                        $uid = $warning['uid'];
                        $fieldValue = $warning[$field];
                        $output->writeln(
                            '    <error>data set with uid ' .
                            $uid . ' in table ' . $table .
                            ' in column ' . $field . ': "' .
                            $fieldValue . '"</error>'
                        );
                    }
                } else {
                    $output->writeln("<info>no warnings for table {$table} in column {$field}</info>");
                }
            }
        }
    }

    /**
     * @param \mysqli $mysqli
     * @param string $column
     * @param string $table
     * @return array
     */
    private function getRowsWithInvalidTimestamp(\mysqli $mysqli, $column, $table)
    {
        $query = $this->buildQuery(
            $mysqli,
            "SELECT * FROM {$table} WHERE {$column} > UNIX_TIMESTAMP(NOW());"
        );
        return \mysqli_fetch_all($query, MYSQLI_ASSOC);
    }

    /**
     * @param \mysqli $mysqli
     * @param string $column
     * @param string $table
     * @return boolean
     */
    private function hasColumnInTable(\mysqli $mysqli, $column, $table)
    {
        $query = $this->buildQuery($mysqli, "SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return (\mysqli_num_rows($query)) ? true : false;
    }

    /**
     * @param \mysqli $mysqli
     * @return object
     */
    private function getTables(\mysqli $mysqli)
    {
        $tables = $this->buildQuery($mysqli, "SHOW TABLES;");
        return \mysqli_fetch_all($tables);
    }

    /**
     * @param \mysqli $mysqli
     * @param $select
     * @return \mysqli_result
     * @throws \Exception
     */
    private function buildQuery(\mysqli $mysqli, $select)
    {
        $query = \mysqli_query($mysqli, $select);
        if (false === $query) {
            throw new \Exception(mysqli_error($mysqli));
        }
        return $query;
    }
}
