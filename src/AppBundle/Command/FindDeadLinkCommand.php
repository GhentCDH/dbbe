<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FindDeadLinkCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:deadlinks:find')
            ->setDescription('Finds dead links.')
            ->setHelp('This command allows you to find dead links.')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Which dead links should be found?');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $curl = curl_init();
        // Only get headers
        curl_setopt($curl, CURLOPT_NOBODY, true);
        // Set timeout to 10 seconds
        curl_setopt ($curl, CURLOPT_CONNECTTIMEOUT, 10);
        // Return value instead of outputting it directly
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        $router = $this->getContainer()->get('router');

        $type = $input->getOption('type');

        // Find dead links in occurrence images
        if ($type == null || $type == 'occurrence_images') {
            $fileSystem = new Filesystem();
            $imageDirectory = $this->getContainer()->getParameter('kernel.project_dir') . '/'
                . $this->getContainer()->getParameter('image_directory') . '/';
            $occurrenceImages = $this->getContainer()->get('dead_link_service')->getOccurrenceImages();

            foreach ($occurrenceImages as $occurrenceImage) {
                if (!$fileSystem->exists($imageDirectory . $occurrenceImage['filename'])) {
                    $output->writeln('Image: ' . $router->generate('image_get', ['id' => $occurrenceImage['image_id']], UrlGeneratorInterface::ABSOLUTE_URL));
                    foreach (json_decode($occurrenceImage['occurrence_ids']) as $occurrenceId) {
                        $output->writeln('* Occurrence: ' . $router->generate('occurrence_get', ['id' => $occurrenceId], UrlGeneratorInterface::ABSOLUTE_URL));
                    }
                }
            }
        }

        // Find dead links in occurrence imagelinks
        if ($type == null || $type == 'occurrence_imagelinks') {
            $occurrenceImageLinks = $this->getContainer()->get('dead_link_service')->getOccurrenceImageLinks();

            foreach ($occurrenceImageLinks as $occurrenceImageLink) {
                curl_setopt ($curl, CURLOPT_URL, $occurrenceImageLink['url']);

                // Do get request if head method is not supported
                if (strpos($occurrenceImageLink['url'], 'https://digi.vatlib.it/view/') === 0) {
                    curl_setopt($curl, CURLOPT_NOBODY, false);
                }

                // Follow redirects for certain sites
                if (
                    strpos($occurrenceImageLink['url'], 'http://daten.digitale-sammlungen.de') === 0
                    || strpos($occurrenceImageLink['url'], 'https://digital.bodleian.ox.ac.uk') === 0
                    || strpos($occurrenceImageLink['url'], 'http://www.bl.uk/manuscripts/') === 0
                ) {
                    curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, true);
                }

                curl_exec($curl);
                $info = curl_getinfo($curl);
                if ($info['http_code'] !== 200) {
                    $output->writeln('Image link (' . $info['http_code'] . '): ' . $occurrenceImageLink['url']);
                    foreach (json_decode($occurrenceImageLink['occurrence_ids']) as $occurrenceId) {
                        $output->writeln('* Occurrence: ' . $router->generate('occurrence_get', ['id' => $occurrenceId], UrlGeneratorInterface::ABSOLUTE_URL));
                    }
                }

                curl_setopt($curl, CURLOPT_NOBODY, true);
                curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, false);
            }
        }

        // Find dead links in online sources
        if ($type == null || $type == 'online_sources') {
            $onlineSources = $this->getContainer()->get('dead_link_service')->getOnlineSources();

            foreach ($onlineSources as $onlineSource) {
                curl_setopt ($curl, CURLOPT_URL, $onlineSource['url']);
                curl_exec($curl);
                $info = curl_getinfo($curl);
                if ($info['http_code'] !== 200) {
                    $output->writeln('Online source (' . $info['http_code'] . '): ' . $onlineSource['url']);
                    $output->writeln('* Online source: ' . $router->generate('online_source_get', ['id' => $onlineSource['online_source_id']], UrlGeneratorInterface::ABSOLUTE_URL));
                }

            }
        }

        // Find dead links in online sources relative links
        if ($type == null || $type == 'online_source_relative_links') {
            $onlineSourceRelativeLinks = $this->getContainer()->get('dead_link_service')->getOnlineSourceRelativeLinks();

            foreach ($onlineSourceRelativeLinks as $onlineSourceRelativeLink) {
                curl_setopt ($curl, CURLOPT_URL, $onlineSourceRelativeLink['url']);
                curl_exec($curl);
                $info = curl_getinfo($curl);
                if ($info['http_code'] !== 200) {
                    $output->writeln('Online source relative link (' . $info['http_code'] . '): ' . $onlineSourceRelativeLink['url']);
                    $output->writeln('* ' . ucfirst($onlineSourceRelativeLink['type']) . ': ' . $router->generate($onlineSourceRelativeLink['type'] . '_get', ['id' => $onlineSourceRelativeLink['entity_id']], UrlGeneratorInterface::ABSOLUTE_URL));
                }

            }
        }

        // Find dead links in identifier links
        if ($type == null || $type == 'identifier_links') {
            $identifierLinks = $this->getContainer()->get('dead_link_service')->getIdentifierLinks();

            foreach ($identifierLinks as $identifierLink) {
                $suffix = '';
                if ($identifierLink['system_name'] == 'diktyon' || $identifierLink['system_name'] == 'ptb') {
                    $suffix = '/';
                }
                curl_setopt ($curl, CURLOPT_URL, $identifierLink['link'] . $identifierLink['identification'] . $suffix);
                curl_exec($curl);
                $info = curl_getinfo($curl);
                if ($info['http_code'] !== 200) {
                    $output->writeln('Identifier link (' . $info['http_code'] . '): ' . $identifierLink['link'] . $identifierLink['identification'] . $suffix);
                    $output->writeln('* ' . ucfirst($identifierLink['type']) . ': ' . $router->generate($identifierLink['type'] . '_get', ['id' => $identifierLink['entity_id']], UrlGeneratorInterface::ABSOLUTE_URL));
                }

            }
        }

        curl_close($curl);
    }
}
