<?php

namespace Starfruit\TranslatorBundle\Controller\Admin;

use Symfony\Component\Routing\Annotation\Route;
use Pimcore\Model\Version;
use Pimcore\Model\Element;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\Input;
use Pimcore\Model\DataObject\ClassDefinition\Data\Textarea;
use Pimcore\Model\DataObject\ClassDefinition\Data\Wysiwyg;
use Pimcore\Model\DataObject\ClassDefinition\Data\Fieldcollections;

#[Route('/translator2')]
class Translator2Controller extends Base2Controller
{
    private function getAllVersion($object)
    {
        $list = new Version\Listing();
        $list->setLoadAutoSave(true);
        $list->setCondition('cid = ? AND ctype = ?', [
            $object->getId(),
            Element\Service::getElementType($object)
        ])
            ->setOrderKey('date')
            ->setOrder('DESC');

        $versions = $list->load();

        return $versions;
    }

    private function returnInvalidField()
    {
        return $this->sendResponse('Null value or no config or invalid field!'); 
    }

    #[Route('/object-can-translate/{id}', methods:"POST")]
    public function canTranslateAction($id)
    {
        $classNeedTranslateList = $this->getClassNeedTranslateList();
        if (empty($classNeedTranslateList)) return $this->sendResponse([]);

        $object = DataObject::getById((int) $id);
        $validObject = $object && !($object instanceof DataObject\Folder);

        if (!$validObject) return $this->sendResponse([]);

        $canTranslate = array_key_exists($object->getClassname(), $classNeedTranslateList);
        if ($canTranslate) {
            return $this->sendResponse($classNeedTranslateList[$object->getClassname()]['field_need_translate']);
        }

        return $this->sendResponse([]);
    }

    #[Route('/object/{id}/{language}/{field}', methods:"POST")]
    public function objectWithLanguageFieldAction($id, $language, $field)
    {
        if ($language == 'vi') return $this->sendResponse('Invalid language!');

        $object = DataObject::getById((int) $id);
        $validObject = $object && !($object instanceof DataObject\Folder);
        if (!$validObject) return $this->sendResponse('Invalid object!');

        $functionGet = 'get' . ucfirst($field);
        $functionSet = 'set' . ucfirst($field);
        if (!method_exists($object, $functionGet) || !method_exists($object, $functionSet)) return $this->returnInvalidField();

        $versions = $this->getAllVersion($object);
        $lastestVersion = empty($versions) ? null : $versions[0];
        $lastestData = $lastestVersion?->getData() ?: $object;

        $logTitle = "Object - $id - $field";
        $classDefinition = $object->getClass();
        $fieldDef = $classDefinition->getFieldDefinition($field);

        $result = [];
        if ($fieldDef instanceof Fieldcollections) {
            $collectionNeedTranslateList = $this->getCollectionNeedTranslateList();
            if (!empty($collectionNeedTranslateList)) {
                $collection = $lastestData->{$functionGet}();
                if (empty($collection)) return $this->returnInvalidField();
                $collectionItems = $collection->getItems();
                if (empty($collectionItems)) return $this->returnInvalidField();

                foreach ($collectionItems as $key => $collectionItem) {
                    $collectionType = $collectionItem->getType();
                    $collectionTransFields = isset($collectionNeedTranslateList[$collectionType]['field_need_translate']) ? $collectionNeedTranslateList[$collectionType]['field_need_translate'] : [];
                    if (empty($collectionTransFields)) continue;

                    foreach ($collectionTransFields as $colField) {
                        $colFunction = 'get' . ucfirst($colField);
                        if (!method_exists($collectionItem, $colFunction)) continue;

                        $colFieldContent = $collectionItem->{$colFunction}(self::DEFAULT_LANG);
                        if (!$colFieldContent) continue;

                        $colTargetContent = $this->translate($language, $colFieldContent, $logTitle . " - FC - $collectionType ");
                        if (empty($colTargetContent)) continue;

                        $colFunction = 'set' . ucfirst($colField);
                        if (!method_exists($collectionItem, $colFunction)) continue;

                        $result[$key][$colField][$language] = $colTargetContent;
                    }
                }
            }
        } else {
            $isStringField = $fieldDef instanceof Input
                || $fieldDef instanceof Textarea
                || $fieldDef instanceof Wysiwyg;
            if (!$isStringField) {
                return $this->returnInvalidField();
            }

            $content = $lastestData->{$functionGet}(self::DEFAULT_LANG);
            if (!$content) {
                return $this->returnInvalidField();
            }

            $targetContent = $this->translate($language, $content, $logTitle);
            if (empty($targetContent)) {
                return $this->sendResponse("Null after translating");
            }

            $result = $targetContent;
        }

        $response["language"] = $language;
        $response["value"] = $result;

        return $this->sendResponse($response);
    }

    private function mergeTranslateData($mergeObject, $data)
    {
        foreach ($data as $field => $lang_value) {
            foreach ($lang_value as $lang => $value) {
                if (is_string($value)) {
                    $mergeObject->{'set' . ucfirst($field)}($value, $lang);
                } else {
                    // field-collection
                    if (is_array($value)) {
                        $collection = $mergeObject->{'get' . ucfirst($field)}();
                        $collectionItems = $collection->getItems();
                        foreach ($collectionItems as $key => $collectionItem) {
                            if (isset($value[$key])) {
                                $collect_data = $value[$key];
                                foreach ($collect_data as $collection_field => $collect_lang_value) {
                                    foreach ($collect_lang_value as $collect_lang => $collect_value) {
                                        $collectionItem->{'set' . ucfirst($collection_field)}($collect_value, $collect_lang);
                                    }
                                }
                            }
                        }

                        $mergeObject->{'set' . ucfirst($field)}($collection);
                    }
                }
            }
        }
    }

    #[Route('/object-merge/{id}', methods:"POST")]
    public function objectMergeAction($id)
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        if (empty($data)) return $this->sendResponse('Invalid data!');

        $object = DataObject::getById((int) $id);
        $validObject = $object && !($object instanceof DataObject\Folder);
        if (!$validObject) return $this->sendResponse('Invalid object!');

        $versions = $this->getAllVersion($object);
        if (!empty($versions)) {
            $lastestVersion = $versions[0];
            $lastestData = $lastestVersion?->getData();

            $this->mergeTranslateData($lastestData, $data);

            $lastestVersion->setData($lastestData);
            $lastestVersion->save();
        } else {
            $this->mergeTranslateData($object, $data);
            $object->saveVersion();
        }

        return $this->sendResponse('Success!');
    }
}
