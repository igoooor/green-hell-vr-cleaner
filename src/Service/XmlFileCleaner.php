<?php

namespace App\Service;

use DOMDocument;
use DOMXPath;

class XmlFileCleaner
{
    public function process(string $filePath, bool $removeStonehengeObjects): string
    {
        $xml = simplexml_load_file($filePath);

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $spoiledObjectIds = [];
        $spoiledComponentIds = [];

        foreach ($xml->xpath('//CONSUMABLES_LIST/CONSUMABLE[STATE="SPOILED"]') as $consumable) {
            $objectId = (string)$consumable->OBJECT_ID;
            if ($objectId) {
                $spoiledObjectIds[] = $objectId;
            }
            $componentId = (string)$consumable->COMPONENT_ID;
            if ($componentId) {
                $spoiledComponentIds[] = $componentId;
            }
        }

        $xpathObj = new DOMXPath($dom);
        $this->removeObjectIds($xpathObj, $spoiledObjectIds);

        if ($removeStonehengeObjects) {
            $this->removeStoneHengeObjects($xpathObj);
        }

        $this->removeEmptyTextNodes($dom);

        $xmlContent = $dom->saveXML();
        $processedFilePath = dirname($filePath).'/Updated_Save_1.Xml';

        $bom = "\xEF\xBB\xBF";
        file_put_contents($processedFilePath, $bom.$xmlContent);

        return $processedFilePath;
    }

    private function removeObjectIdsFromPath(DOMXPath $xpathObj, string $xpath, array $spoiledIds): void
    {
        foreach ($spoiledIds as $spoiledId) {
            foreach ($xpathObj->query($xpath.'/OBJECT_ID[text()="'.$spoiledId.'"]') as $objectNode) {
                $objectNode->parentNode->removeChild($objectNode);
            }
        }
    }

    private function removeEmptyTextNodes(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//text()[normalize-space(.) = ""]') as $emptyTextNode) {
            $emptyTextNode->parentNode->removeChild($emptyTextNode);
        }
    }

    private function removeStoneHengeObjects(DOMXPath $xpathObj): void
    {
        $objectIds = [];
        foreach ($xpathObj->query(
            '//SCENE_DATA_LIST/SCENE_DATA[NAME="STONEHENGE"]/OBJECTS_ID_LIST/OBJECT_ID'
        ) as $objectIdNode) {
            // extract OBJECT_ID
            $objectIds[] = $objectIdNode->nodeValue;
            //$objectIdNode->parentNode->removeChild($objectIdNode);
        }

        $this->removeObjectIds($xpathObj, $objectIds);
    }

    private function removeObjectIds(DOMXPath $xpathObj, array $objectIds): DOMXPath
    {
        $this->removeObjectIdsFromPath($xpathObj, '//SCENE_DATA_LIST/SCENE_DATA/OBJECTS_ID_LIST', $objectIds);

        foreach ($objectIds as $objectId) {
            /** @var \DOMElement $objectNode */
            foreach ($xpathObj->query('//OBJECTS_LIST/OBJECT[OBJECT_ID="'.$objectId.'"]') as $objectNode) {
                $objectNode->parentNode->removeChild($objectNode);
            }

            foreach ($xpathObj->query('//DRINKABLES_LIST/DRINKABLE[OBJECT_ID="'.$objectId.'"]') as $drinkableNode) {
                $drinkableNode->parentNode->removeChild($drinkableNode);
            }

            foreach ($xpathObj->query('//DAMAGABLES_LIST/DAMAGABLE[OBJECT_ID="'.$objectId.'"]') as $damagableNode) {
                $damagableNode->parentNode->removeChild($damagableNode);
            }

            foreach ($xpathObj->query('//PLANTS_LIST/PLANT[OBJECT_ID="'.$objectId.'"]') as $plantNode) {
                $plantNode->parentNode->removeChild($plantNode);
            }

            foreach ($xpathObj->query('//CAMPFIRES_LIST/CAMPFIRE[OBJECT_ID="'.$objectId.'"]') as $campfireNode) {
                $campfireNode->parentNode->removeChild($campfireNode);
            }

            foreach ($xpathObj->query('//FLAMMABLESS_LIST/FLAMMABLE[OBJECT_ID="'.$objectId.'"]') as $flammableNode) {
                $flammableNode->parentNode->removeChild($flammableNode);
            }

            /** @var \DOMElement $slotNode */
            foreach ($xpathObj->query('//SLOTS_LIST/SLOTS[OBJECT_ID="'.$objectId.'"]') as $slotNode) {
                foreach ($slotNode->childNodes as $item) {
                    if ($item->nodeName === 'HELD_ITEM_ID' && $item->nodeValue !== "0") {
                        $this->removeObjectIds($xpathObj, [$item->nodeValue]);
                    }
                }
                $slotNode->parentNode->removeChild($slotNode);
            }

            foreach ($xpathObj->query('//CONSUMABLES_LIST/CONSUMABLE[OBJECT_ID="'.$objectId.'"]') as $consumableNode) {
                $consumableNode->parentNode->removeChild($consumableNode);
            }
        }

        return $xpathObj;
    }
}
