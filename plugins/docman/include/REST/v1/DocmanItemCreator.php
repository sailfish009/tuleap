<?php
/**
 * Copyright (c) Enalean, 2018-Present. All Rights Reserved.
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
 *
 */

namespace Tuleap\Docman\REST\v1;

use Docman_Item;
use Luracast\Restler\RestException;
use PFUser;
use Project;
use Tuleap\Docman\Metadata\CustomMetadataException;
use Tuleap\Docman\REST\v1\EmbeddedFiles\DocmanEmbeddedPOSTRepresentation;
use Tuleap\Docman\REST\v1\Files\CreatedItemFilePropertiesRepresentation;
use Tuleap\Docman\REST\v1\Files\EmptyFileToUploadFinisher;
use Tuleap\Docman\REST\v1\Files\FilePropertiesPOSTPATCHRepresentation;
use Tuleap\Docman\REST\v1\Folders\DocmanEmptyPOSTRepresentation;
use Tuleap\Docman\REST\v1\Folders\DocmanFolderPOSTRepresentation;
use Tuleap\Docman\REST\v1\Links\DocmanLinkPOSTRepresentation;
use Tuleap\Docman\REST\v1\Links\DocmanLinksValidityChecker;
use Tuleap\Docman\REST\v1\Metadata\CustomMetadataRepresentationRetriever;
use Tuleap\Docman\REST\v1\Metadata\HardcodedMetadataObsolescenceDateRetriever;
use Tuleap\Docman\REST\v1\Metadata\ItemStatusMapper;
use Tuleap\Docman\REST\v1\Wiki\DocmanWikiPOSTRepresentation;
use Tuleap\Docman\Upload\Document\DocumentOngoingUploadRetriever;
use Tuleap\Docman\Upload\Document\DocumentToUploadCreator;
use Tuleap\Docman\Upload\UploadCreationConflictException;
use Tuleap\Docman\Upload\UploadCreationFileMismatchException;
use Tuleap\Docman\Upload\UploadMaxSizeExceededException;

class DocmanItemCreator
{
    /**
     * @var \Docman_ItemFactory
     */
    private $item_factory;
    /**
     * @var DocumentOngoingUploadRetriever
     */
    private $document_ongoing_upload_retriever;
    /**
     * @var DocumentToUploadCreator
     */
    private $document_to_upload_creator;
    /**
     * @var AfterItemCreationVisitor
     */
    private $creator_visitor;
    /**
     * @var EmptyFileToUploadFinisher
     */
    private $empty_file_to_upload_finisher;
    /**
     * @var DocmanLinksValidityChecker
     */
    private $links_validity_checker;
    /**
     * @var ItemStatusMapper
     */
    private $status_mapper;
    /**
     * @var HardcodedMetadataObsolescenceDateRetriever
     */
    private $date_retriever;
    /**
     * @var CustomMetadataRepresentationRetriever
     */
    private $custom_checker;

    public function __construct(
        \Docman_ItemFactory $item_factory,
        DocumentOngoingUploadRetriever $document_ongoing_upload_retriever,
        DocumentToUploadCreator $document_to_upload_creator,
        AfterItemCreationVisitor $creator_visitor,
        EmptyFileToUploadFinisher $empty_file_to_upload_finisher,
        DocmanLinksValidityChecker $links_validity_checker,
        ItemStatusMapper $status_mapper,
        HardcodedMetadataObsolescenceDateRetriever $date_retriever,
        CustomMetadataRepresentationRetriever $custom_checker
    ) {
        $this->item_factory                      = $item_factory;
        $this->document_ongoing_upload_retriever = $document_ongoing_upload_retriever;
        $this->document_to_upload_creator        = $document_to_upload_creator;
        $this->creator_visitor                   = $creator_visitor;
        $this->empty_file_to_upload_finisher     = $empty_file_to_upload_finisher;
        $this->links_validity_checker            = $links_validity_checker;
        $this->status_mapper                     = $status_mapper;
        $this->date_retriever                    = $date_retriever;
        $this->custom_checker                    = $custom_checker;
    }

    /**
     * @throws RestException
     */
    private function checkDocumentIsNotBeingUploaded(
        Docman_Item $parent_item,
        $document_type,
        $title,
        \DateTimeImmutable $current_time
    ) {
        if ($document_type === ItemRepresentation::TYPE_FILE) {
            return;
        }

        $is_document_being_uploaded = $this->document_ongoing_upload_retriever->isThereAlreadyAnUploadOngoing(
            $parent_item,
            $title,
            $current_time
        );
        if ($is_document_being_uploaded) {
            throw new RestException(409, 'A document is already being uploaded for this item');
        }
    }

    /**
     * @return CreatedItemRepresentation
     * @throws Metadata\HardCodedMetadataException
     * @throws \Tuleap\Docman\CannotInstantiateItemWeHaveJustCreatedInDBException
     */
    private function createDocument(
        $item_type_id,
        \DateTimeImmutable $current_time,
        Docman_Item $parent_item,
        PFUser $user,
        Project $project,
        $title,
        $description,
        array $formatted_representations,
        ?string $status,
        ?string $obsolescence_date,
        $wiki_page,
        $link_url,
        $content
    ) {

        $status_id = $this->status_mapper->getItemStatusWithParentInheritance($parent_item, $status);

        if ($item_type_id !== PLUGIN_DOCMAN_ITEM_TYPE_FOLDER) {
            $obsolescence_date_time_stamp = $this->date_retriever->getTimeStampOfDateWithoutPeriodValidity(
                $obsolescence_date,
                $current_time
            );
        } else {
            $obsolescence_date_time_stamp = (int)ItemRepresentation::OBSOLESCENCE_DATE_NONE;
        }
        $item = $this->item_factory->createWithoutOrdering(
            $title,
            $description,
            $parent_item->getId(),
            $status_id,
            $obsolescence_date_time_stamp,
            $user->getId(),
            $item_type_id,
            $wiki_page,
            $link_url
        );

        $params = [
            'group_id'           => $project->getID(),
            'parent'             => $parent_item,
            'item'               => $item,
            'user'               => $user,
            'creation_time'      => $current_time,
            'formatted_metadata' => $formatted_representations
        ];

        if ($item_type_id === PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE) {
            $params['content'] = $content;
        }

        $item->accept($this->creator_visitor, $params);
        $representation = new CreatedItemRepresentation();
        $representation->build($item->getId());

        return $representation;
    }

    /**
     * @throws Metadata\HardCodedMetadataException
     * @throws RestException
     */
    public function createFileDocument(
        Docman_Item $parent_item,
        PFUser $user,
        $title,
        $description,
        ?string $status,
        ?string $obsolescence_date,
        \DateTimeImmutable $current_time,
        FilePropertiesPOSTPATCHRepresentation $file_properties,
        ?array $formatted_metadata
    ) : CreatedItemRepresentation {
        if ($this->item_factory->doesTitleCorrespondToExistingDocument($title, $parent_item->getId())) {
            throw new RestException(400, "A file with same title already exists in the given folder.");
        }

        $status_id = $this->status_mapper->getItemStatusWithParentInheritance($parent_item, $status);

        $obsolescence_date_time_stamp = $this->date_retriever->getTimeStampOfDateWithoutPeriodValidity(
            $obsolescence_date,
            $current_time
        );

        try {
            $document_to_upload = $this->document_to_upload_creator->create(
                $parent_item,
                $user,
                $current_time,
                $title,
                $description,
                $file_properties->file_name,
                $file_properties->file_size,
                $status_id,
                $obsolescence_date_time_stamp,
                $formatted_metadata
            );

            if ($file_properties->file_size === 0) {
                $this->empty_file_to_upload_finisher->createEmptyFile($document_to_upload, $file_properties->file_name);

                $representation = new CreatedItemRepresentation();
                $representation->build($document_to_upload->getItemId());

                return $representation;
            }
        } catch (UploadCreationConflictException $exception) {
            throw new RestException(409, $exception->getMessage());
        } catch (UploadCreationFileMismatchException $exception) {
            throw new RestException(409, $exception->getMessage());
        } catch (UploadMaxSizeExceededException $exception) {
            throw new RestException(400, $exception->getMessage());
        }

        $file_properties_representation = new CreatedItemFilePropertiesRepresentation();
        $file_properties_representation->build($document_to_upload->getUploadHref());
        $representation = new CreatedItemRepresentation();
        $representation->build($document_to_upload->getItemId(), $file_properties_representation);

        return $representation;
    }

    /**
     * @throws Metadata\HardCodedMetadataException
     * @throws RestException
     * @throws \Tuleap\Docman\CannotInstantiateItemWeHaveJustCreatedInDBException
     * @throws CustomMetadataException
     */
    public function createFolder(
        Docman_Item $parent_item,
        PFUser $user,
        DocmanFolderPOSTRepresentation $representation,
        \DateTimeImmutable $current_time,
        Project $project
    ): CreatedItemRepresentation {

        if ($this->item_factory->doesTitleCorrespondToExistingFolder($representation->title, $parent_item->getId())) {
            throw new RestException(400, "A folder with same title already exists in the given folder.");
        }

        $formatted_representations = $this->custom_checker->checkAndRetrieveFormattedRepresentation($representation->metadata);

        return $this->createDocument(
            PLUGIN_DOCMAN_ITEM_TYPE_FOLDER,
            $current_time,
            $parent_item,
            $user,
            $project,
            $representation->title,
            $representation->description,
            $formatted_representations,
            $representation->status,
            ItemRepresentation::OBSOLESCENCE_DATE_NONE,
            null,
            null,
            null
        );
    }

    /**
     * @throws Metadata\HardCodedMetadataException
     * @throws RestException
     * @throws \Tuleap\Docman\CannotInstantiateItemWeHaveJustCreatedInDBException
     * @throws CustomMetadataException
     */
    public function createEmpty(
        Docman_Item $parent_item,
        PFUser $user,
        DocmanEmptyPOSTRepresentation $representation,
        \DateTimeImmutable $current_time,
        Project $project
    ): CreatedItemRepresentation {
        if ($this->item_factory->doesTitleCorrespondToExistingDocument($representation->title, $parent_item->getId())) {
            throw new RestException(400, "A document with same title already exists in the given folder.");
        }

        $formatted_representations = $this->custom_checker->checkAndRetrieveFormattedRepresentation($representation->metadata);

        $this->checkDocumentIsNotBeingUploaded(
            $parent_item,
            PLUGIN_DOCMAN_ITEM_TYPE_EMPTY,
            $representation->title,
            $current_time
        );

        return $this->createDocument(
            PLUGIN_DOCMAN_ITEM_TYPE_EMPTY,
            $current_time,
            $parent_item,
            $user,
            $project,
            $representation->title,
            $representation->description,
            $formatted_representations,
            $representation->status,
            $representation->obsolescence_date,
            null,
            null,
            null
        );
    }

    /**
     * @throws Metadata\HardCodedMetadataException
     * @throws RestException
     * @throws \Tuleap\Docman\CannotInstantiateItemWeHaveJustCreatedInDBException
     * @throws CustomMetadataException
     */
    public function createWiki(
        Docman_Item $parent_item,
        PFUser $user,
        DocmanWikiPOSTRepresentation $representation,
        \DateTimeImmutable $current_time,
        Project $project
    ): CreatedItemRepresentation {
        if ($this->item_factory->doesTitleCorrespondToExistingDocument($representation->title, $parent_item->getId())) {
            throw new RestException(400, "A document with same title already exists in the given folder.");
        }

        $formatted_representations = $this->custom_checker->checkAndRetrieveFormattedRepresentation($representation->metadata);

        if (! $project->usesWiki()) {
            throw new RestException(
                400,
                sprintf('The wiki service of the project: "%s" is not available', $project->getUnixName())
            );
        }

        $this->checkDocumentIsNotBeingUploaded(
            $parent_item,
            PLUGIN_DOCMAN_ITEM_TYPE_WIKI,
            $representation->title,
            $current_time
        );

        return $this->createDocument(
            PLUGIN_DOCMAN_ITEM_TYPE_WIKI,
            $current_time,
            $parent_item,
            $user,
            $project,
            $representation->title,
            $representation->description,
            $formatted_representations,
            $representation->status,
            $representation->obsolescence_date,
            $representation->wiki_properties->page_name,
            null,
            null
        );
    }

    /**
     * @return CreatedItemRepresentation
     * @throws Metadata\HardCodedMetadataException
     * @throws RestException
     * @throws \Tuleap\Docman\CannotInstantiateItemWeHaveJustCreatedInDBException
     * @throws CustomMetadataException
     */
    public function createEmbedded(
        Docman_Item $parent_item,
        PFUser $user,
        DocmanEmbeddedPOSTRepresentation $representation,
        \DateTimeImmutable $current_time,
        Project $project
    ): CreatedItemRepresentation {
        if ($this->item_factory->doesTitleCorrespondToExistingDocument($representation->title, $parent_item->getId())) {
            throw new RestException(400, "A document with same title already exists in the given folder.");
        }

        $formatted_representations = $this->custom_checker->checkAndRetrieveFormattedRepresentation($representation->metadata);

        $this->checkDocumentIsNotBeingUploaded(
            $parent_item,
            PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE,
            $representation->title,
            $current_time
        );

        return $this->createDocument(
            PLUGIN_DOCMAN_ITEM_TYPE_EMBEDDEDFILE,
            $current_time,
            $parent_item,
            $user,
            $project,
            $representation->title,
            $representation->description,
            $formatted_representations,
            $representation->status,
            $representation->obsolescence_date,
            null,
            null,
            $representation->embedded_properties->content
        );
    }

    /**
     * @return CreatedItemRepresentation
     * @throws Metadata\HardCodedMetadataException
     * @throws RestException
     * @throws \Tuleap\Docman\CannotInstantiateItemWeHaveJustCreatedInDBException
     * @throws CustomMetadataException
     */
    public function createLink(
        Docman_Item $parent_item,
        PFUser $user,
        DocmanLinkPOSTRepresentation $representation,
        \DateTimeImmutable $current_time,
        Project $project
    ): CreatedItemRepresentation {
        if ($this->item_factory->doesTitleCorrespondToExistingDocument($representation->title, $parent_item->getId())) {
            throw new RestException(400, "A document with same title already exists in the given folder.");
        }

        $formatted_representations = $this->custom_checker->checkAndRetrieveFormattedRepresentation($representation->metadata);

        $link_url = $representation->link_properties->link_url;
        $this->links_validity_checker->checkLinkValidity($link_url);

        $this->checkDocumentIsNotBeingUploaded(
            $parent_item,
            PLUGIN_DOCMAN_ITEM_TYPE_LINK,
            $representation->title,
            $current_time
        );

        return $this->createDocument(
            PLUGIN_DOCMAN_ITEM_TYPE_LINK,
            $current_time,
            $parent_item,
            $user,
            $project,
            $representation->title,
            $representation->description,
            $formatted_representations,
            $representation->status,
            $representation->obsolescence_date,
            null,
            $link_url,
            null
        );
    }
}
