<?php
/**
 * Copyright (c) Enalean, 2019 - present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Tuleap\Docman\REST\v1\Metadata;

use Docman_Metadata;
use Docman_MetadataListOfValuesElement;
use Tuleap\Docman\Metadata\ListOfValuesElement\MetadataListOfValuesElementListBuilder;

class CustomMetadataCollectionBuilder
{
    /**
     * @var \Docman_MetadataFactory
     */
    private $metadata_factory;
    /**
     * @var MetadataListOfValuesElementListBuilder
     */
    private $list_of_value_builder;

    public function __construct(\Docman_MetadataFactory $metadata_factory, MetadataListOfValuesElementListBuilder $list_of_value_builder)
    {
        $this->metadata_factory      = $metadata_factory;
        $this->list_of_value_builder = $list_of_value_builder;
    }

    public function build(): CustomMetadataCollection
    {
        $metadata_representations = [];

        $metadata_list = $this->metadata_factory->getRealMetadataList();
        /**
         * @var Docman_Metadata $metadata
         */
        foreach ($metadata_list as $metadata) {
            $representation = new DocmanMetadataRepresentation();
            $representation->build(
                $metadata->getLabel(),
                $metadata->getName(),
                $metadata->getDescription(),
                (int)$metadata->getType(),
                $metadata->isRequired(),
                $metadata->isMultipleValuesAllowed(),
                $metadata->isUsed(),
                $this->getListOfPossibleValues($metadata)
            );
            $metadata_representations[] = $representation;
        }

        return CustomMetadataCollection::build($metadata_representations);
    }

    private function getListOfPossibleValues(Docman_Metadata $metadata): ?array
    {
        if ((int)$metadata->getType() !== PLUGIN_DOCMAN_METADATA_TYPE_LIST) {
            return null;
        }

        $list_of_values = $this->list_of_value_builder->build((int)$metadata->getId(), false);

        $possible_values_representation = [];
        /**
         * @var Docman_MetadataListOfValuesElement $value
         */
        foreach ($list_of_values as $value) {
            $representation = new DocmanMetadataListValueRepresentation();
            $representation->build((int)$value->getId(), $this->getNoneMetadataValue($value));

            $possible_values_representation[] = $representation;
        }

        return $possible_values_representation;
    }

    private function getNoneMetadataValue(Docman_MetadataListOfValuesElement $metadata): string
    {
        if ((int)$metadata->getId() === PLUGIN_DOCMAN_ITEM_STATUS_NONE) {
            return $GLOBALS['Language']->getText('plugin_docman', 'love_special_none_name_key');
        }

        return $metadata->getName();
    }
}
