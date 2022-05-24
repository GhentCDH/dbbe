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

        // Translator
        $this->setPersonRoles($translations);

        $this->setComments($translations);

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
            if (property_exists($data, 'personRoles')) {
                if (!is_object($data->personRoles)) {
                    throw new BadRequestHttpException('Incorrect personRole data.');
                }
                $roles = $this->container->get(RoleManager::class)->getByType('translation');
                foreach ($roles as $role) {
                    if (property_exists($data->personRoles, $role->getSystemName())) {
                        $correct = true;
                        $this->updatePersonRole($old, $role, $data->personRoles->{$role->getSystemName()});
                    }
                }
            }
            if (property_exists($data, 'publicComment')) {
                if (!empty($data->publicComment) && !is_string($data->publicComment)) {
                    throw new BadRequestHttpException('Incorrect public comment data.');
                }
                $correct = true;
                $this->dbs->updatePublicComment($id, $data->publicComment);
            }

            if (!$correct && !$isNew) {
                throw new BadRequestHttpException('Incorrect data.');
            }

            // load new data
            $new = $this->getFull($id);

            $this->updateModified($isNew ? null : $old, $new);

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
        if (property_exists($data, 'personRoles')
            && !is_object($data->personRoles)
        ) {
            throw new BadRequestHttpException('Incorrect personRole data.');
        }
        $roles = $this->container->get(RoleManager::class)->getByType('translation');
        foreach ($roles as $role) {
            if (!property_exists($data->personRoles, $role->getSystemName())
                || !is_array($data->personRoles->{$role->getSystemName()})
            ) {
                throw new BadRequestHttpException('Incorrect ' . $role->getSystemName() . ' data.');
            }
            foreach ($data->personRoles->{$role->getSystemName()} as $person) {
                if (!is_object($person)
                    || (property_exists($person, 'id') && !is_numeric($person->id))
                ) {
                    throw new BadRequestHttpException('Incorrect ' . $role->getSystemName() . ' data.');
                }
            }
        }
        if (property_exists($data, 'publicComment')
            && (
                !empty($data->publicComment)
                && !is_string($data->publicComment)
            )
        ) {
            throw new BadRequestHttpException('Incorrect public comment data.');
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

        $updated = false;
        $oldPersonRoles = $old->getPersonRoles();
        foreach ($roles as $role) {
            $oldPersonIdsWithRole = [];
            if (array_key_exists($role->getSystemName(), $oldPersonRoles)) {
                $oldPersonIdsWithRole = array_keys($oldPersonRoles[$role->getSystemName()][1]);
            }
            if (count($oldPersonIdsWithRole) != count($data->personRoles->{$role->getSystemName()})) {
                $updated = true;
                break;
            }
            foreach ($data->personRoles->{$role->getSystemName()} as $newPerson) {
                if (!in_array($newPerson->id, $oldPersonIdsWithRole)) {
                    $updated = true;
                }
            }
            if ($updated) {
                break;
            }
        }
        if ($updated) {
            $update->personRoles = $data->personRoles;
        }

        if (
            // No old, new
            (
                empty($old->getPublicComment())
                && (
                    property_exists($data, 'publicComment')
                    && empty($data->publicComment)
                )
            )
            // Old, no new
            || (
                !empty($old->getPublicComment())
                && (
                    !property_exists($data, 'publicComment')
                    || empty($data->publicComment)
                )
            )
            // Different old and new
            || (
                property_exists($data, 'publicComment')
                && $old->getPublicComment() !== $data->publicComment
            )
        ) {
            $update->publicComment = $data->publicComment;
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

            // commit transaction
            $this->dbs->commit();
        } catch (Exception $e) {
            $this->dbs->rollBack();
            throw $e;
        }

        return;
    }
}
