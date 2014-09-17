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
        $connection = $this->connect(
            $input->getOption('host'),
            $input->getOption('database'),
            $input->getOption('port'),
            $input->getOption('user'),
            $input->getOption('pass')
        );

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
     * @param string $host
     * @param string $db
     * @param integer $port
     * @param string $user
     * @param string $pass
     * @return \PDO
     */
    private function connect($host, $db, $port, $user, $pass)
    {
        $dsn = 'mysql:dbname=%s;host=%s;port=%s';
        return new \PDO(sprintf($dsn, $db, $host, $port), $user, $pass);
    }

    /**
     * @param \PDO $connection
     * @param string $column
     * @param string $table
     * @return array
     */
    private function getRowsWithInvalidTimestamp(\PDO $connection, $column, $table)
    {
        $stmt = $this->buildQuery($connection, "SELECT * FROM {$table} WHERE {$column} > UNIX_TIMESTAMP(NOW());");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param \PDO $connection
     * @param string $column
     * @param string $table
     * @return boolean
     */
    private function hasColumnInTable(\PDO $connection, $column, $table)
    {
        $stmt = $this->buildQuery($connection, "SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return (count($stmt->fetchAll()) > 0) ? true : false;
    }

    /**
     * @param \PDO $connection
     * @return object
     */
    private function getTables(\PDO $connection)
    {
        $stmt = $this->buildQuery($connection, "SHOW TABLES;");
        return $stmt->fetchAll(\PDO::FETCH_NUM);
    }

    /**
     * @param \PDO $connection
     * @param string $select
     * @return \PDOStatement
     * @throws \Exception
     */
    private function buildQuery(\PDO $connection, $select)
    {
        $query = $connection->query($select);
        if (false === $query) {
            $errorInfo = $connection->errorInfo();
            throw new \Exception($errorInfo[2]);
        }
        return $query;
    }
}
