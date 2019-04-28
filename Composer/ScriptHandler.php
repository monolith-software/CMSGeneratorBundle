<?php

namespace Monolith\Bundle\CMSGeneratorBundle\Composer;

use Composer\Script\Event;
use Sensio\Bundle\DistributionBundle\Composer\ScriptHandler as SymfonyScriptHandler;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class ScriptHandler extends SymfonyScriptHandler
{
    /**
     * @param $event Event A instance
     */
    public static function installCms(Event $event)
    {
        $options = parent::getOptions($event);
        $appDir = $options['symfony-app-dir'];
        $binDir = $options['symfony-bin-dir'];

        if (null === $appDir) {
            return;
        }

        $finder = (new Finder())->directories()->depth('== 0')->name('*SiteBundle')->name('SiteBundle')->in($appDir.'/../src');

        if ($finder->count() == 0) {
            $event->getIO()->write('<info>Installing Monolith CMS. This prosess purge all database tables.</info>');
            $confirm  = $event->getIO()->ask(sprintf('<question>%s</question> (<comment>%s</comment>): ', 'Are you shure?', 'Y'), 'Y');

            if (strtoupper($confirm) != 'Y') {
                $event->getIO()->write('<comment>Skipped...</comment>');

                return;
            }

            $sitename = $event->getIO()->ask(sprintf('<question>%s</question> (<comment>%s</comment>): ', 'Site name', 'My'), 'My');

            /* @todo validation
            while ($sitename !== 'Test') {
                $event->getIO()->write('<comment>Incorrest site name. Retry again...</comment>');
                $sitename = $event->getIO()->ask(sprintf('<question>%s</question> (<comment>%s</comment>): ', 'Site name', 'My'), 'My');
            }
            */

            $username = $event->getIO()->ask(sprintf('<question>%s</question> (<comment>%s</comment>): ', 'Username', 'root'), 'root');
            $email    = $event->getIO()->ask(sprintf('<question>%s</question> (<comment>%s</comment>): ', 'Email', 'root@world.com'), 'root@world.com');
            $password = $event->getIO()->ask(sprintf('<question>%s</question> (<comment>%s</comment>): ', 'Password', '123'), '123');


            static::executeCommand($event, $binDir, "cms:install --sitename={$sitename} --username={$username} --email={$email} --password={$password} --no-interaction", $options['process-timeout']);

            /*
            static::clearCacheHard();

            static::executeCommand($event, $binDir, 'doctrine:schema:drop --force', $options['process-timeout']);

            static::executeCommand($event, $binDir, 'cms:generate:sitebundle --name='.$sitename, $options['process-timeout']);

            unlink($appDir.'/config/install.yml');

            static::warmapCacheHard();

            static::executeCommand($event, $binDir, 'doctrine:schema:update --force --complete', $options['process-timeout']);

            $event->getIO()->write('<comment>Create super admin user:</comment>');

            static::executeCommand($event, $binDir, "fos:user:create --super-admin $username $email $password", $options['process-timeout']);

            static::executeCommand($event, $binDir, 'cms:generate:default-site-data', $options['process-timeout']);
            */
        }
    }

    protected static function clearCacheHard()
    {
        $process = new Process('bash bin/clear_cache');
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                echo 'ERR > '.$buffer;
            } else {
                echo $buffer;
            }
        });
    }

    protected static function warmapCacheHard()
    {
        $process = new Process('bash bin/warmup_cache');
        $process->run(function ($type, $buffer) {
            /*
            if (Process::ERR === $type) {
                echo 'ERR > '.$buffer;
            } else {
                echo $buffer;
            }
            */
        });
    }
}
