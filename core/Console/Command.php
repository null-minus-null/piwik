<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
namespace Piwik\Console;

use Piwik\Common;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * BaseClass for console commands.
 * @package Piwik_Console
 */
class Command extends SymfonyCommand
{
    public function __construct($name = null)
    {
        if (!Common::isPhpCliMode()) {
            throw new \RuntimeException('Only executable in CLI mode');
        }

        parent::__construct($name);
    }

    public function writeSuccessMessage(OutputInterface $output, $messages)
    {
        $lengths = array_map('strlen', $messages);
        $maxLen = max($lengths) + 4;

        $separator = str_pad('', $maxLen, '*');

        $output->writeln('');
        $output->writeln('<info>' . $separator . '</info>');

        foreach ($messages as $message) {
            $output->writeln('  ' . $message . '  ');
        }

        $output->writeln('<info>' . $separator . '</info>');
        $output->writeln('');
    }
}
