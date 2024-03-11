<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use App\DatabaseService\DeadLinkService;

class FindDeadLinkCommand extends Command
{
    protected $di = [];

    /**
     * FindDeadLinkCommand constructor.
     * @param UrlGeneratorInterface $urlGenerator
     * @param DeadLinkService $deadLinkService
     * @param ParameterBagInterface $params
     */
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        DeadLinkService $deadLinkService,
        ParameterBagInterface $params
    ) {
        $this->di = [
            'UrlGenerator' => $urlGenerator,
            'DeadLinkService' => $deadLinkService,
            'Params' => $params,
        ];

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:deadlinks:find')
            ->setDescription('Finds dead links.')
            ->setHelp('This command allows you to find dead links.')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Which dead links should be found?');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $curl = curl_init();
        // Only get headers
        curl_setopt($curl, CURLOPT_NOBODY, true);
        // Set timeout to 10 seconds
        curl_setopt ($curl, CURLOPT_CONNECTTIMEOUT, 10);
        // Return value instead of outputting it directly
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        $type = $input->getOption('type');

        // Find dead links in occurrence images
        if ($type == null || $type == 'occurrence_images') {
            $fileSystem = new Filesystem();
            $imageDirectory = $this->di['Params']->get('app.image_directory') . '/';
            $occurrenceImages = $this->di['DeadLinkService']->getOccurrenceImages();

            foreach ($occurrenceImages as $occurrenceImage) {
                if (!$fileSystem->exists($imageDirectory . $occurrenceImage['filename'])) {
                    $output->writeln('* Image: ' . $this->di['UrlGenerator']->generate('image_get', ['id' => $occurrenceImage['image_id']], UrlGeneratorInterface::ABSOLUTE_URL));
                    foreach (json_decode($occurrenceImage['occurrence_ids']) as $occurrenceId) {
                        $output->writeln('  * Occurrence: ' . $this->di['UrlGenerator']->generate('occurrence_get', ['id' => $occurrenceId], UrlGeneratorInterface::ABSOLUTE_URL));
                    }
                }
            }
        }

        // Find dead links in occurrence imagelinks
        if ($type == null || $type == 'occurrence_imagelinks') {
            $occurrenceImageLinks = $this->di['DeadLinkService']->getOccurrenceImageLinks();

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
                    $output->writeln('* Image link (' . $info['http_code'] . '): ' . $occurrenceImageLink['url']);
                    foreach (json_decode($occurrenceImageLink['occurrence_ids']) as $occurrenceId) {
                        $output->writeln('  * Occurrence: ' . $this->di['UrlGenerator']->generate('occurrence_get', ['id' => $occurrenceId], UrlGeneratorInterface::ABSOLUTE_URL));
                    }
                }

                curl_setopt($curl, CURLOPT_NOBODY, true);
                curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, false);
            }
        }

        // Find dead links in online sources
        if ($type == null || $type == 'online_sources') {
            $onlineSources = $this->di['DeadLinkService']->getOnlineSources();

            foreach ($onlineSources as $onlineSource) {
                curl_setopt ($curl, CURLOPT_URL, $onlineSource['url']);
                curl_exec($curl);
                $info = curl_getinfo($curl);
                if ($info['http_code'] !== 200) {
                    $output->writeln('* Online source (' . $info['http_code'] . '): ' . $onlineSource['url']);
                    $output->writeln('  * Online source: ' . $this->di['UrlGenerator']->generate('online_source_get', ['id' => $onlineSource['online_source_id']], UrlGeneratorInterface::ABSOLUTE_URL));
                }

            }
        }

        // Find dead links in online sources relative links
        if ($type == null || $type == 'online_source_relative_links') {
            $onlineSourceRelativeLinks = $this->di['DeadLinkService']->getOnlineSourceRelativeLinks();

            foreach ($onlineSourceRelativeLinks as $onlineSourceRelativeLink) {
                curl_setopt ($curl, CURLOPT_URL, $onlineSourceRelativeLink['url']);
                curl_exec($curl);
                $info = curl_getinfo($curl);
                if ($info['http_code'] !== 200) {
                    $output->writeln('* Online source relative link (' . $info['http_code'] . '): ' . $onlineSourceRelativeLink['url']);
                    $output->writeln('  * ' . ucfirst($onlineSourceRelativeLink['type']) . ': ' . $this->di['UrlGenerator']->generate($onlineSourceRelativeLink['type'] . '_get', ['id' => $onlineSourceRelativeLink['entity_id']], UrlGeneratorInterface::ABSOLUTE_URL));
                }

            }
        }

        // Find dead links in identifier links
        if ($type == null || $type == 'identifier_links') {
            $identifierLinks = $this->di['DeadLinkService']->get('dead_link_service')->getIdentifierLinks();

            foreach ($identifierLinks as $identifierLink) {
                $suffix = '';
                if ($identifierLink['system_name'] == 'diktyon' || $identifierLink['system_name'] == 'ptb') {
                    $suffix = '/';
                }
                curl_setopt ($curl, CURLOPT_URL, $identifierLink['link'] . $identifierLink['identification'] . $suffix);
                curl_exec($curl);
                $info = curl_getinfo($curl);
                if ($info['http_code'] !== 200) {
                    $output->writeln('* Identifier link (' . $info['http_code'] . '): ' . $identifierLink['link'] . $identifierLink['identification'] . $suffix);
                    $output->writeln('  * ' . ucfirst($identifierLink['type']) . ': ' . $this->di['UrlGenerator']->generate($identifierLink['type'] . '_get', ['id' => $identifierLink['entity_id']], UrlGeneratorInterface::ABSOLUTE_URL));
                }

            }
        }

        curl_close($curl);
    }
}
