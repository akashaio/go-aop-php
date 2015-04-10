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
use Go\Core\AdviceMatcher;
use Go\Core\AspectContainer;
use Go\Core\AspectLoader;
use Go\Instrument\CleanableMemory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TokenReflection\Broker;
use TokenReflection\ReflectionClass;

/**
 * Console command to debug an advisors
 */
class AdvisorDebugCommand extends BaseAspectCommand
{

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('aop:advisor:debug')
            ->addArgument('advisorId', InputArgument::REQUIRED, "Identifier of advisor")
            ->setDescription("Provides an interface for checking and debugging advisors")
            ->setHelp(<<<EOT
Allows to query an information about matching joinpoints for specified advisor.
EOT
            );
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $advisorId = $input->getArgument('advisorId');
        $output->writeln($this->getHelper('formatter')->formatSection('AOP', "Debug info for advisor $advisorId"));
        $this->showAdvisorInformation($advisorId, $output);
    }

    private function showAdvisorInformation($advisorId, OutputInterface $output)
    {
        $aspectContainer = $this->aspectKernel->getContainer();

        /** @var AdviceMatcher $adviceMatcher */
        $adviceMatcher = $aspectContainer->get('aspect.advice_matcher');
        $aspectLoader  = $aspectContainer->get('aspect.cached.loader');
        $advisor       = $this->loadAdvisor($advisorId, $aspectContainer, $aspectLoader);
        $sourceBroker  = new Broker(new CleanableMemory());

        // TODO: should be replaced with file enumerator
        $options = $this->aspectKernel->getOptions();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $options['appDir'],
                \FilesystemIterator::SKIP_DOTS
            )
        );

        /** @var \CallbackFilterIterator|\SplFileInfo[] $iterator */
        $iterator   = new \CallbackFilterIterator($iterator, $this->getFileFilter($options));
        $totalFiles = iterator_count($iterator);
        $output->writeln("Total <info>{$totalFiles}</info> files to analyze.");
        $iterator->rewind();

        foreach ($iterator as $file) {
            $reflectionFile       = $sourceBroker->processFile($file, true);
            $reflectionNamespaces = $reflectionFile->getNamespaces();
            foreach ($reflectionNamespaces as $reflectionNamespace) {
                foreach ($reflectionNamespace->getClasses() as $reflectionClass) {
                    // TODO: recursive loads all dependencies for this class
                    $advices = $adviceMatcher->getAdvicesForClass($reflectionClass, array($advisor));
                    if ($advices) {
                        $this->writeInfoAboutAdvices($output, $reflectionClass, $advices);
                    }
                }
            }
        }
    }

    /**
     * Filter for files
     *
     * @param array $options Kernel options
     *
     * @return callable
     */
    private function getFileFilter(array $options)
    {
        $includePaths   = $options['includePaths'];
        $excludePaths   = $options['excludePaths'];
        $excludePaths[] = $options['cacheDir'];

        return function (\SplFileInfo $file) use ($includePaths, $excludePaths) {
            if ($file->getExtension() !== 'php') {
                return false;
            };

            if ($includePaths) {
                $found = false;
                foreach ($includePaths as $includePath) {
                    if (strpos($file->getRealPath(), realpath($includePath)) === 0) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return false;
                }
            }

            foreach ($excludePaths as $excludePath) {
                if (strpos($file->getRealPath(), realpath($excludePath)) === 0) {
                    return false;
                }
            }

            return true;
        };
    }

    private function writeInfoAboutAdvices(OutputInterface $output, ReflectionClass $reflectionClass, array $advices)
    {
        $className = $reflectionClass->getName();
        foreach ($advices as $type=>$typedAdvices) {
            foreach ($typedAdvices as $pointName=>$advice) {
                $output->writeln("  -> matching <comment>{$type} {$className}->{$pointName}</comment>");
            }
        }
    }

    /**
     * Loads an advisor from the containter or from aspect
     *
     * @param string $advisorId Identifier of advisor
     * @param AspectContainer $aspectContainer Container
     * @param AspectLoader $loader Instance of aspect loader
     *
     * @return Advisor
     */
    private function loadAdvisor($advisorId, AspectContainer $aspectContainer, AspectLoader $loader)
    {
        try {
            $advisor = $aspectContainer->getAdvisor($advisorId);
        } catch (\OutOfBoundsException $e) {
            list($aspect)   = explode('->', $advisorId);
            $aspectInstance = $aspectContainer->getAspect($aspect);
            $loader->loadAndRegister($aspectInstance);
            $advisor = $aspectContainer->getAdvisor($advisorId);
        }

        return $advisor;
    }
}
