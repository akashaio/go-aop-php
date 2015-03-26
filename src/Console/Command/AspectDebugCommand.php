<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Go\Aop\Aspect;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command for warming the cache
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
        $maxName    = strlen('id');
        $maxAspect  = strlen('aspect');

        foreach ($aspectList as $aspectId => $aspect) {
            $refAspect = new \ReflectionObject($aspect);
            $maxName   = max($maxName, strlen($aspectId));
            $maxAspect = max($maxAspect, strlen($refAspect->getName()));
        }

        $format       = '%-' . $maxName . 's %-' . $maxAspect . 's';
        $formatHeader = '%-' . ($maxName + 19) . 's %-' . ($maxAspect + 19) . 's';
        $output->writeln(sprintf($formatHeader, '<comment>Id</comment>', '<comment>Aspect</comment>'));

        foreach ($aspectList as $aspectId => $aspect) {
            $refAspect  = new \ReflectionObject($aspect);
            $aspectName = $refAspect->getName();
            $output->writeln(sprintf($format, $aspectId, $aspectName), OutputInterface::OUTPUT_RAW);
        }
    }

    /**
     * Shows an information about concrete aspect
     *
     * @param OutputInterface $output
     * @param string $aspectId
     */
    private function showAspect(OutputInterface $output, $aspectId)
    {
        $container = $this->aspectKernel->getContainer();
        $aspect    = $container->get($aspectId);
        if (!$aspect instanceof Aspect) {
            throw new \InvalidArgumentException("Service {$aspectId} is not valid aspect!");
        }

        $output->writeln($this->getHelper('formatter')->formatSection('AOP', sprintf('Aspect "%s"', $aspectId)));
        $refAspect = new \ReflectionObject($aspect);

        $output->write('<comment>Class</comment>         ');
        $output->writeln($refAspect->getName(), OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Description</comment>         ');
        $output->writeln($this->getPrettyText($refAspect->getDocComment()), OutputInterface::OUTPUT_RAW);

//        $output->write('<comment>Host</comment>         ');
//        $output->writeln($host, OutputInterface::OUTPUT_RAW);
//
//        $output->write('<comment>Scheme</comment>       ');
//        $output->writeln($scheme, OutputInterface::OUTPUT_RAW);
//
//        $output->write('<comment>Method</comment>       ');
//        $output->writeln($method, OutputInterface::OUTPUT_RAW);
//
//        $output->write('<comment>Class</comment>        ');
//        $output->writeln(get_class($route), OutputInterface::OUTPUT_RAW);
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
