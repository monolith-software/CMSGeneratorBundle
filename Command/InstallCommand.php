<?php

namespace Monolith\Bundle\CMSGeneratorBundle\Command;

use Monolith\Bundle\CMSBundle\Entity\Language;
use Monolith\Bundle\CMSBundle\Entity\Site;
use Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class InstallCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Monolith CMS clean installer')
            ->setName('cms:install')
            ->addOption('sitename', 's', InputOption::VALUE_OPTIONAL, 'Site name [My]')
            ->addOption('username', 'u', InputOption::VALUE_OPTIONAL, 'Username [root]')
            ->addOption('email',   null, InputOption::VALUE_OPTIONAL, 'Email [root@world.com]')
            ->addOption('password',null, InputOption::VALUE_OPTIONAL, 'Password [123]')
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
        dump($input->getOption('no-interaction'));

        $appDir = realpath($this->getContainer()->get('kernel')->getRootDir());
        $binDir = 'bin';

        $finder = (new Finder())->directories()->depth('== 0')->name('*SiteBundle')->name('SiteBundle')->in($appDir.'/../src');

        if ($finder->count() == 0) {
            $dialog     = $this->getQuestionHelper();
            $filesystem = new Filesystem();

            if (!$input->getOption('no-interaction')) {
                $output->writeln('<error>Installing Monolith CMS. This prosess purge all database tables.</error>');
                $confirm = $dialog->ask($input, $output, new Question('<comment>Are you shure?</comment> [y,N]: ', 'n'));

                if (strtolower($confirm) !== 'y') {
                    $output->writeln('<info>Abort.</info>');

                    return false;
                }
            }

            $sitename = $input->getOption('sitename');
            $username = $input->getOption('username');
            $email    = $input->getOption('email');
            $password = $input->getOption('password');

            if (empty($sitename)) {
                $sitename = $dialog->ask($input, $output, new Question('<comment>Site name</comment> [My]: ', 'My'));
            }

            if (empty($username)) {
                $username = $dialog->ask($input, $output, new Question('<comment>Username</comment> [root]: ', 'root'));
            }

            if (empty($email)) {
                $email    = $dialog->ask($input, $output, new Question('<comment>Email</comment> [root@world.com]: ', 'root@world.com'));
            }

            if (empty($password)) {
                $password = $dialog->ask($input, $output, new Question('<comment>Password</comment> [123]: ', '123'));
            }

            static::executeCommand($output, $binDir, 'doctrine:schema:drop --force');
            static::executeCommand($output, $binDir, 'cms:generate:sitebundle --name='.$sitename);

            $filesystem->remove('app/config/install.yml');

            $process = new Process('bash bin/warmup_cache'); // clear_cache
            $process->run(function ($type, $buffer) {
                /*
                if (Process::ERR === $type) {
                    echo 'ERR > '.$buffer;
                } else {
                    echo $buffer;
                }
                */
            });

            static::executeCommand($output, $binDir, 'doctrine:schema:update --force --complete --env=prod');

            $output->writeln('<comment>Create super admin user:</comment>');

            static::executeCommand($output, $binDir, "fos:user:create --super-admin $username $email $password");

            // Создание языка, домена и сайта в БД.

            /** @var \Doctrine\ORM\EntityManager $em */
            $em = $this->getContainer()->get('doctrine.orm.entity_manager');

            //$user = $em->getRepository('SiteBundle:User')->findOneBy(['username' => $username]);
            $user = $this->getContainer()->get('cms.context')->getUserManager()->findUserBy(['username' => $username]);

            $locale = $this->getContainer()->getParameter('locale');

            $language = $em->getRepository('CMSBundle:Language')->findOneBy(['code' => $locale]);

            if (empty($language)) {
                $language = new Language();
                $language
                    ->setCode($locale)
                    ->setName(mb_strtoupper($locale))
                    ->setUser($user)
                ;

                $em->persist($language);
                $em->flush($language);
            }

            $site = $em->getRepository('CMSBundle:Site')->findOneBy([]);

            if (empty($site)) {
                $site = new Site($sitename);
                $site
                    ->setLanguage($language)
                    ->setTheme('default')
                ;

                $em->persist($site);
                $em->flush($site);
            }
        }

        return null;
    }

    protected function getQuestionHelper()
    {
        $question = $this->getHelperSet()->get('question');
        if (!$question || get_class($question) !== 'Sensio\Bundle\GeneratorBundle\Command\Helper\QuestionHelper') {
            $this->getHelperSet()->set($question = new QuestionHelper());
        }

        return $question;
    }

    protected static function executeCommand(OutputInterface $output, $consoleDir, $cmd, $timeout = 300)
    {
        $php = escapeshellarg(static::getPhp(false));
        $phpArgs = implode(' ', array_map('escapeshellarg', static::getPhpArguments()));
        $console = escapeshellarg($consoleDir.'/console');
        $console .= ' --ansi';

        $process = new Process($php.($phpArgs ? ' '.$phpArgs : '').' '.$console.' '.$cmd, null, null, null, $timeout);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf("An error occurred when executing the \"%s\" command:\n\n%s\n\n%s.", escapeshellarg($cmd), $process->getOutput(), $process->getErrorOutput()));
        }
    }

    protected static function getPhp($includeArgs = true)
    {
        $phpFinder = new PhpExecutableFinder();
        if (!$phpPath = $phpFinder->find($includeArgs)) {
            throw new \RuntimeException('The php executable could not be found, add it to your PATH environment variable and try again');
        }

        return $phpPath;
    }

    protected static function getPhpArguments()
    {
        $arguments = array();

        $phpFinder = new PhpExecutableFinder();
        if (method_exists($phpFinder, 'findArguments')) {
            $arguments = $phpFinder->findArguments();
        }

        if (false !== $ini = php_ini_loaded_file()) {
            $arguments[] = '--php-ini='.$ini;
        }

        return $arguments;
    }
}
