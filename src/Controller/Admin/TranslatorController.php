<?php

namespace Starfruit\TranslatorBundle\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

use Pimcore\Db;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\Version;
use Pimcore\Model\Element;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Textarea;
use Pimcore\Model\DataObject\ClassDefinition\Data\Wysiwyg;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;

#[Route('/translator')]
class TranslatorController extends BaseController
{
    #[Route('/object-can-translate/{id}', methods:"POST")]
    public function canTranslateAction($id)
    {
        if (empty($this->classNeedTranslateList)) return $this->sendResponse(['ok' => false]);

        $object = DataObject::getById((int) $id);
        $validObject = $object && !($object instanceof DataObject\Folder);

        if (!$validObject) return $this->sendResponse(['ok' => false]);

        $canTranslate = array_key_exists($object->getClassname(), $this->classNeedTranslateList);

        return $this->sendResponse(['ok' => $canTranslate]);
    }

    #[Route('/object/{id}/{language}', methods:"POST")]
    public function objectAction($id, $language)
    {
        $object = DataObject::getById((int) $id);

        // validate
        if ($language == 'vi') return $this->sendResponse('Invalid language!');

        if ($language == 'all') {
            $validLanguages = \Pimcore\Tool::getValidLanguages();
            $translateLanguages = array_filter($validLanguages, fn($e) => $e !== 'vi');
        } else {
            $translateLanguages = [$language];
        }

        $validObject = $object && !($object instanceof DataObject\Folder);
        if (!$validObject) return $this->sendResponse('Invalid object!');

        $classname = $object->getClassname();
        $canTranslate = array_key_exists($classname, $this->classNeedTranslateList);
        if (!$canTranslate) return $this->sendResponse('No config for Class of object!');

        $classConfig = $this->classNeedTranslateList[$classname];
        $fieldNeedTranslates = isset($classConfig['field_need_translate']) ? $classConfig['field_need_translate'] : [];
        if (empty($fieldNeedTranslates)) return $this->sendResponse('No fields config for translate!');

        // get lastest data
        $list = new Version\Listing();
        $list->setLoadAutoSave(true);
        $list->setCondition('cid = ? AND ctype = ?', [
            $object->getId(),
            Element\Service::getElementType($object)
        ])
            ->setOrderKey('date')
            ->setOrder('DESC');

        $versions = $list->load();
        $lastestVersion = empty($versions) ? null : $versions[0];
        $lastestData = $lastestVersion?->getData() ?: $object;

        // translate
        $needSave = false;

        $classDefinition = $object->getClass();
        $logTitle = "Object - $id";
        $translatedData = [];
        foreach ($fieldNeedTranslates as $field) {
            $function = 'get' . ucfirst($field);

            if (!method_exists($lastestData, $function)) {
                continue;
            }

            $fieldDef = $classDefinition->getFieldDefinition($field);

            // translate Field Collection
            if ($fieldDef instanceof Fieldcollections && !empty($this->collectionNeedTranslateList)) {
                $collection = $lastestData->{$function}();

                if (empty($collection)) {
                    continue;
                }

                $collectionItems = $collection->getItems();
                if (empty($collectionItems)) {
                    continue;
                }

                foreach ($collectionItems as $key => $collectionItem) {
                    $collectionType = $collectionItem->getType();

                    $collectionTransFields = isset($this->collectionNeedTranslateList[$collectionType]['field_need_translate']) ? $this->collectionNeedTranslateList[$collectionType]['field_need_translate'] : [];

                    if (empty($collectionTransFields)) {
                        continue;
                    }

                    foreach ($collectionTransFields as $colField) {
                        $colFunction = 'get' . ucfirst($colField);

                        if (!method_exists($collectionItem, $colFunction)) {
                            continue;
                        }

                        $colFieldContent = $collectionItem->{$colFunction}(self::DEFAULT_LANG);

                        if (!$colFieldContent) {
                            continue;
                        }

                        foreach ($translateLanguages as $language) {
                            $colTargetContent = $this->translate($language, $colFieldContent, $logTitle . " - FC - $collectionType ");

                            if (empty($colTargetContent)) {
                                continue;
                            }

                            $colFunction = 'set' . ucfirst($colField);

                            if (!method_exists($collectionItem, $colFunction)) {
                                continue;
                            }

                            $collectionItem->{$colFunction}($colTargetContent, $language);
                        }  
                    }
                }

                $lastestData->{'set' . ucfirst($field)}($collection);
                $needSave = true;
            }

            $isStringField = $fieldDef instanceof Input
                || $fieldDef instanceof Textarea
                || $fieldDef instanceof Wysiwyg;
            if (!$isStringField) {
                continue;
            }

            $content = $lastestData->{$function}(self::DEFAULT_LANG);

            if (!$content) {
                continue;
            }

            foreach ($translateLanguages as $language) {
                $targetContent = $this->translate($language, $content, $logTitle);

                if (empty($targetContent)) {
                    continue;
                }

                $translatedData[$field][$language] = $targetContent;
            }
        }

        if (!empty($translatedData)) {
            $needSave = true;
            foreach ($translatedData as $field => $value_languages) {
                $function = 'set' . ucfirst($field);

                foreach ($value_languages as $language => $value) {
                    $lastestData->{$function}($value, $language);
                }
            }
        }

        if ($needSave) {
            $lastestData->saveVersion();
        }

        return $this->sendResponse('Success!');
    }

    #[Route('/document/{id}/{language}/{sourceId}', methods:"POST")]
    public function documentAction($id, $language, $sourceId)
    {
        // valid
        $document = Document::getById($id);
        if (!($document instanceof Document\Page || $document instanceof Document\Snippet)) return $this->sendResponse('Document type must be page or snippet!');

        $propertyLanguage = $document->getProperty('language');
        if ($propertyLanguage == 'vi' || $propertyLanguage != $language) return $this->sendResponse('Invalid language!');

        $sourceDocument = Document::getById($sourceId);
        if (!($sourceDocument instanceof Document\Page || $sourceDocument instanceof Document\Snippet)) return $this->sendResponse('Source: Document type must be page or snippet!');

        $propertyLanguage = $sourceDocument->getProperty('language');
        if ($propertyLanguage != 'vi') return $this->sendResponse('Source: Invalid language!');

        // query DB
        $db = Db::get();
        // $query = "SELECT * FROM `documents_editables` WHERE `documentId` = ? AND `type` IN ('input', 'textarea', 'wysiwyg') AND `name` NOT REGEXP '^[^:]+:[0-9]+\\.[^\\.]+$'";
        $query = "SELECT * FROM `documents_editables` WHERE `documentId` = ? AND `type` IN ('input', 'textarea', 'wysiwyg')";
        $sourceData = $db->fetchAllAssociative($query, [$sourceId]);

        if (empty($sourceData)) return $this->sendResponse('No content need translate!');

        // translate
        $logTitle = "Document - $id";
        foreach ($sourceData as $sourceItem) {
            $content = $sourceItem['data'];
            if (empty($content)) {
                continue;
            }

            $targetContent = $this->translate($language, $content, $logTitle);

            if (empty($targetContent)) {
                continue;
            }

            // update to DB
            $query = "UPDATE `documents_editables` SET `data` = ? WHERE `documentId` = ? AND `type` = ? AND `name` = ?";
            $db->executeQuery($query, [$targetContent, $id, $sourceItem['type'], $sourceItem['name']]);
        }

        return $this->sendResponse('Success!');
    }
}
