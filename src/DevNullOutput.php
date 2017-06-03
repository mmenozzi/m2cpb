<?php


class DevNullOutput implements OutputInterface
{
    /**
     * @param string $line
     * @return void
     */
    public function writeln($line)
    {
    }
}
