<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:test-redis')]
class TestRedisCommand extends Command
{
    public function __construct(private $redis)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Test Redis connection');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $result = $this->redis->ping();
            $io->success("Redis fonctionne : " . $result);
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Redis erreur : " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}