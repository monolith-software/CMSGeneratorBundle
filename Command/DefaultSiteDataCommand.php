<?php

declare(strict_types=1);

namespace Monolith\Bundle\CMSGeneratorBundle\Command;

use Monolith\Bundle\CMSBundle\Entity\Domain;
use Monolith\Bundle\CMSBundle\Entity\Folder;
use Monolith\Bundle\CMSBundle\Entity\Language;
use Monolith\Bundle\CMSBundle\Entity\Site;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DefaultSiteDataCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Create Default Site Data')
            ->setName('cms:generate:default-site-data')
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
        $output->write('Create Default Site Data:');

        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        $user = $em->getRepository('SiteBundle:User')->findOneBy([], ['id' => 'ASC']);

        $output->write(' Language');

        $language = new Language();
        $language
            ->setCode('ru')
            ->setName('Русский')
            ->setUser($user)
        ;
        $em->persist($language);
        $em->flush($language);

        $output->write(', Domain');

        $domain = new Domain();
        $domain
            ->setName('localhost')
            ->setUser($user)
            ->setLanguage($language)
        ;
        $em->persist($domain);
        $em->flush($domain);

        $output->write(', Folder');
        $folder = new Folder();
        $folder
            ->setUser($user)
            ->setTitle('Главная')
        ;
        $em->persist($folder);
        $em->flush($folder);

        $output->write(', Site');
        $site = new Site();
        $site
            ->setUser($user)
            ->setDomain($domain)
            ->setLanguage($language)
            ->setName('@todo default site')
            ->setRootFolder($folder)
            ->setTheme('default')
        ;
        $em->persist($site);
        $em->flush($site);

        $output->write(', Theme');
        $fileSystem = new Filesystem();
        $fileSystem->mirror('src/Monolith/Bundle/CMSGeneratorBundle/Resources/skeleton/theme/', 'themes/default/');

        $output->write(' <info>OK</info>'.PHP_EOL);
    }
}
