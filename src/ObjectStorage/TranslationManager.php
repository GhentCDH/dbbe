<?php

namespace App\ObjectStorage;

use Exception;
use stdClass;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use App\Model\Language;
use App\Model\Translation;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * ObjectManager for translations
 */
class TranslationManager extends DocumentManager
{
    /**
     * Get translations with all information
     * @param  array $ids
     * @return array
     */
    public function getMini(array $ids): array
    {
        $translations = [];
        $rawTranslations = $this->dbs->getTranslationsByIds($ids);

        foreach ($rawTranslations as $rawTranslation) {
            $translations[$rawTranslation['translation_id']]= (new Translation())
                ->setId($rawTranslation['translation_id'])
                ->setLanguage(new Language($rawTranslation['language_id'], $rawTranslation['language_name']))
                ->setText($rawTranslation['text_content']);
        }

        $this->setBibliographies($translations);

        return $translations;
    }

    public function getShort(array $ids): array
    {
        return $this->getMini($ids);
    }

    public function getFull(int $id): Translation
    {
        $translations = $this->getShort([$id]);
        if (count($translations) == 0) {
            throw new NotFoundHttpException('Translation with id ' . $id .' not found.');
        }
        return $translations[$id];
    }

    public function add(stdClass $data, int $documentId): Translation
    {
        $this->dbs->beginTransaction();
        try {
            // text and language are mandatory
            if (!property_exists($data, 'text') || !property_exists($data, 'language')) {
                throw new BadRequestHttpException('Incorrect data.');
            }
            if (empty($data->text)) {
                throw new BadRequestHttpException('Incorrect text data.');
            }
            if (!is_object($data->language)
                || !property_exists($data->language, 'id')
                || empty($data->language->id)
                || !is_numeric($data->language->id)
            ) {
                throw new BadRequestHttpException('Incorrect language data.');
            }
            $id = $this->dbs->insert($documentId, $data->language->id, $data->text);

            // prevent language and text from being updated unnecessarily
            unset($data->language);
            unset($data->text);

            $new = $this->update($id, $data, true);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();

            throw $e;
        }

        return $new;
    }

    public function update(int $id, stdClass $data, bool $isNew = false): Translation
    {
        $this->dbs->beginTransaction();
        try {
            $old = $this->getFull($id);

            $correct = false;
            if (property_exists($data, 'text')) {
                if (empty($data->text) || !is_string($data->text)) {
                    throw new BadRequestHttpException('Incorrect text data.');
                }
                $correct = true;
                $this->dbs->updateText($id, $data->text);
            }
            if (property_exists($data, 'language')) {
                if (!is_object($data->language)
                    || !property_exists($data->language, 'id')
                    || empty($data->language->id)
                    || !is_numeric($data->language->id)
                ) {
                    throw new BadRequestHttpException('Incorrect language data.');
                }
                $correct = true;
                $this->dbs->updateLanguage($id, $data->language->id);
            }
            if (property_exists($data, 'bibliography')) {
                if (!is_object($data->bibliography)) {
                    throw new BadRequestHttpException('Incorrect bibliography data.');
                }
                $correct = true;
                $this->updateBibliography($old, $data->bibliography);
            }

            if (!$correct && !$isNew) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

            $this->cache->invalidateTags(['translations']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return $new;
    }

    public function updateIfRequired(Translation $old, stdClass $data): Translation
    {
        if (!property_exists($data, 'text')
            || empty($data->text)
            || !is_string($data->text)
        ) {
            throw new BadRequestHttpException('Incorrect text data.');
        }
        if (!property_exists($data, 'language')
            || !is_object($data->language)
            || !property_exists($data->language, 'id')
            || empty($data->language->id)
            || !is_numeric($data->language->id)
        ) {
            throw new BadRequestHttpException('Incorrect language data.');
        }
        if (!property_exists($data, 'bibliography')
            || !is_object($data->bibliography)
        ) {
            throw new BadRequestHttpException('Incorrect bibliography data.');
        }
        $newBiblio = $data->bibliography;
        foreach (['book', 'article', 'bookChapter', 'onlineSource'] as $bibType) {
            $plurBibType = $bibType . 's';
            if (!property_exists($newBiblio, $plurBibType) || !is_array($newBiblio->$plurBibType)) {
                throw new BadRequestHttpException('Incorrect bibliography data.');
            }
            foreach ($newBiblio->$plurBibType as $bib) {
                if (!is_object($bib)
                    || (property_exists($bib, 'id') && (empty($bib->id) || !is_numeric($bib->id)))
                    || !property_exists($bib, $bibType) || !is_object($bib->$bibType)
                    || !property_exists($bib->$bibType, 'id')
                    || empty($bib->$bibType->id)
                    || !is_numeric($bib->$bibType->id)
                ) {
                    throw new BadRequestHttpException('Incorrect bibliography data.');
                }
                if (in_array($bibType, ['book', 'article', 'bookChapter'])) {
                    if (!property_exists($bib, 'startPage') || !(empty($bib->startPage) || is_string($bib->startPage))
                        || !property_exists($bib, 'endPage')  || !(empty($bib->endPage) || is_string($bib->endPage))
                    ) {
                        throw new BadRequestHttpException('Incorrect bibliography data.');
                    }
                } else {
                    if (!property_exists($bib, 'relUrl') || !(empty($bib->relUrl) ||is_string($bib->relUrl))
                    ) {
                        throw new BadRequestHttpException('Incorrect bibliography data.');
                    }
                }
            }
        }

        $update = new stdClass();
        if ($old->getText() !== $data->text) {
            $update->text = $data->text;
        }
        if ($old->getLanguage()->getId() !== $data->language->id) {
            $update->language = $data->language;
        }
        // check for new bibliography items
        $new = false;
        $updated = false;
        foreach ($newBiblio as $newBibItems) {
            foreach ($newBibItems as $newBibItem) {
                if (!property_exists($newBibItem, 'id')) {
                    $new = true;
                    break;
                }
            }
            if ($new) {
                break;
            }
        }
        if ($new) {
            $update->bibliography = $newBiblio;
        } else {
            // check for updated bibliography items
            $oldBiblio = $old->getBibliographies();
            $matchedIds = [];
            foreach ($newBiblio as $key => $value) {
                foreach ($value as $newBibItem) {
                    if (property_exists($newBibItem, 'id')) {
                        $matchedIds[] = $newBibItem->id;
                        $oldBibItem = $oldBiblio[$newBibItem->id];
                        switch ($key) {
                        case 'articles':
                            if ($oldBibItem->getArticle()->getId() !== $newBibItem->article->id
                                || $oldBibItem->getStartPage() !== $newBibItem->startPage
                                || $oldBibItem->getEndPage() !== $newBibItem->endPage
                            ) {
                                $updated = true;
                            }
                            break;
                        case 'books':
                            if ($oldBibItem->getBook()->getId() !== $newBibItem->book->id
                                || $oldBibItem->getStartPage() !== $newBibItem->startPage
                                || $oldBibItem->getEndPage() !== $newBibItem->endPage
                            ) {
                                $updated = true;
                            }
                            break;
                        case 'bookChapters':
                            if ($oldBibItem->getBookChapter()->getId() !== $newBibItem->bookChapter->id
                                || $oldBibItem->getStartPage() !== $newBibItem->startPage
                                || $oldBibItem->getEndPage() !== $newBibItem->endPage
                            ) {
                                $updated = true;
                            }
                            break;
                        case 'onlineSources':
                            if ($oldBibItem->getOnlineSource()->getId() !== $newBibItem->onlineSource->id
                                || $oldBibItem->getRelUrl() !== $newBibItem->relUrl
                            ) {
                                $updated = true;
                            }
                            break;
                        }
                    }
                    if ($updated) {
                        break;
                    }
                }
                if ($updated) {
                    break;
                }
            }
            if ($updated) {
                $update->bibliography = $newBiblio;
            } else {
                // check for deleted bibliography items
                foreach ($oldBiblio as $oldBibItem) {
                    if (!in_array($oldBibItem->getId(), $matchedIds)) {
                        $update->bibliography = $newBiblio;
                        break;
                    }
                }
            }
        }

        if (!empty((array)$update)) {
            return $this->update($old->getId(), $update);
        }

        return $old;
    }


    public function delete(int $id): void
    {
        $this->dbs->beginTransaction();
        try {
            // Throws NotFoundException if not found
            $old = $this->getFull($id);

            // Cascades to translation_of table
            $this->dbs->delete($id);

            $this->updateModified($old, null);

            $this->cache->invalidateTags(['translations']);

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return;
    }
}