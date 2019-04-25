<?php

namespace Monolith\Bundle\CMSGeneratorBundle\Command;

use Monolith\Bundle\CMSGeneratorBundle\Generator\SiteBundleGenerator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class GenerateSiteBundleCommand extends GeneratorCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputOption('name', '', InputOption::VALUE_REQUIRED, 'Site name.'),
            ])
            ->setDescription('Generate SiteBundle for Monolith CMS')
            ->setName('cms:generate:sitebundle')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When namespace doesn't end with Bundle
     * @throws \RuntimeException         When bundle can't be executed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        $name = $input->getOption('name');

        if (empty($name)) {
            $name = 'My';

            $question = new Question($questionHelper->getQuestion('Site name.', $name), $name);

            $name = $questionHelper->ask($input, $output, $question);
        }

        $name = ucfirst($name);

        $dir = dirname($this->getContainer()->getParameter('kernel.root_dir')).'/src';
        $format = 'yml';
        $structure = 'no';

        if (!$this->getContainer()->get('filesystem')->isAbsolutePath($dir)) {
            $dir = getcwd().'/'.$dir;
        }

        $bundle = 'SiteBundle';
        $namespace = $name.$bundle;

        $generator = $this->getGenerator();
        $generator->generate($namespace, $bundle, $dir, $format, $structure);

        $output->writeln('Generating the bundle code: <info>OK</info>');

        $errors = array();
        $runner = $questionHelper->getRunner($output, $errors);

        // check that the namespace is already autoloaded
        $runner($this->checkAutoloader($output, $namespace, $bundle, $dir));

        if (!$errors) {
            $questionHelper->writeSection($output, "'$namespace' succesfuly created!");
        } else {
            $questionHelper->writeSection($output, [
                'The command was not able to configure everything automatically.',
                'You must do the following changes manually.',
            ], 'error');

            $output->writeln($errors);
        }
    }

    protected function checkAutoloader(OutputInterface $output, $namespace, $bundle, $dir)
    {
        $output->write('Checking that the bundle is autoloaded: ');
        if (!class_exists($namespace.'\\'.$bundle)) {
            return array(
                '- Edit the <comment>composer.json</comment> file and register the bundle',
                '  namespace in the "autoload" section:',
                '',
            );
        }
    }

    protected function createGenerator()
    {
        return new SiteBundleGenerator($this->getContainer()->get('filesystem'));
    }
}
