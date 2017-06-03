<?php

class Output implements OutputInterface
{
    /**
     * @param string $line
     * @return void
     */
    public function writeln($line)
    {
        echo $line . PHP_EOL;
    }
}
