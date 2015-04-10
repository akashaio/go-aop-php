<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use Go\Core\AspectLoader;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command for querying an information about aspects
 */
class AspectDebugCommand extends BaseAspectCommand
{

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('aop:aspect:debug')
            ->addArgument('aspectId', InputArgument::OPTIONAL, "Optional aspect Id")
            ->setDescription("Provides an interface for querying the information about aspects")
            ->setHelp(<<<EOT
Allows to query an information about enabled aspects.
EOT
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $aspectId = $input->getArgument('aspectId');
        if (!$aspectId) {
            $this->showAspects($output);
        } else {
            $this->showAspect($output, $aspectId);
        }
    }

    /**
     * Shows an information about registered aspects
     *
     * @param OutputInterface $output
     */
    private function showAspects(OutputInterface $output)
    {
        $container = $this->aspectKernel->getContainer();
        $output->writeln($this->getHelper('formatter')->formatSection('AOP', 'Registered aspects'));

        $aspectList = $container->getByTag('aspect');
        $maxAspect  = strlen('aspect');

        foreach ($aspectList as $aspect) {
            $refAspect = new \ReflectionObject($aspect);
            $maxAspect = max($maxAspect, strlen($refAspect->getName()));
        }

        $format       = '%-' . $maxAspect . 's';
        $formatHeader = '%-' . ($maxAspect + 19) . 's';
        $output->writeln(sprintf($formatHeader, '<comment>Aspect</comment>') . PHP_EOL);

        foreach ($aspectList as $aspect) {
            $refAspect  = new \ReflectionObject($aspect);
            $aspectName = $refAspect->getName();
            $output->writeln(sprintf($format, $aspectName), OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * Shows an information about concrete aspect
     *
     * @param OutputInterface $output
     * @param string $aspectName
     */
    private function showAspect(OutputInterface $output, $aspectName)
    {
        $container    = $this->aspectKernel->getContainer();
        /** @var AspectLoader $aspectLoader */
        $aspectLoader = $container->get('aspect.loader');
        $aspect       = $container->getAspect($aspectName);
        if (!$aspect instanceof Aspect) {
            throw new \InvalidArgumentException("Service {$aspectName} is not valid aspect!");
        }

        $output->writeln($this->getHelper('formatter')->formatSection('AOP', "Aspect information"));
        $refAspect = new \ReflectionObject($aspect);

        $output->write('<comment>Class</comment>         ');
        $output->writeln($refAspect->getName(), OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Description</comment>         ');
        $output->writeln($this->getPrettyText($refAspect->getDocComment()), OutputInterface::OUTPUT_RAW);

        $output->writeln('<comment>Pointcuts and advices</comment>');

        $aspectItems = $aspectLoader->load($aspect);
        foreach ($aspectItems as $itemId => $item) {
            $type = 'Unknown';
            if ($item instanceof Pointcut) {
                $type = 'Pointcut';
            }
            if ($item instanceof Advisor) {
                $type = 'Advisor';
            }

            $output->writeln("$type <comment>$itemId</comment>");
        }
    }

    /**
     * Gets the reformatted comment text.
     *
     * @param string $comment
     *
     * @return mixed|string
     */
    public function getPrettyText($comment)
    {
        $text = preg_replace('|^\s*/?\*+/?|m', '', $comment);

        return $text;
    }
}
