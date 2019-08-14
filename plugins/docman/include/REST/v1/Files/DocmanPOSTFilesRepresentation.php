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

namespace Tuleap\Docman\REST\v1\Files;

use Tuleap\Docman\REST\v1\CopyItem\CanContainACopyRepresentation;
use Tuleap\Docman\REST\v1\CopyItem\DocmanCopyItemRepresentation;
use Tuleap\Docman\REST\v1\ItemRepresentation;
use Tuleap\Docman\REST\v1\Metadata\ItemStatusMapper;

class DocmanPOSTFilesRepresentation implements CanContainACopyRepresentation
{
    private const REQUIRED_NON_COPY_PROPERTIES = ['title', 'file_properties'];

    /**
     * @var string Item title {@from body} {@required false} Mandatory if copy is not set
     */
    public $title;
    /**
     * @var string Item description {@from body} {@required false}
     */
    public $description = '';
    /**
     * @var FilePropertiesPOSTPATCHRepresentation File properties must be set when creating a new file {@from body}
     *      {@required false} {@type \Tuleap\Docman\REST\v1\Files\FilePropertiesPOSTPATCHRepresentation} Mandatory if copy is not set
     */
    public $file_properties;
    /**
     * @var string | null Item status {@from body} {@required false} {@choice none,draft,approved,rejected}
     */
    public $status;

    /**
     * @var string | null Obsolescence date {@from body} {@required false}
     */
    public $obsolescence_date = ItemRepresentation::OBSOLESCENCE_DATE_NONE;
    /**
     * @var array | null {@required false} {@type \Tuleap\Docman\REST\v1\Metadata\POSTCustomMetadataRepresentation}
     */
    public $metadata;
    /**
     * @var DocmanCopyItemRepresentation {@required false} {@type \Tuleap\Docman\REST\v1\CopyItem\DocmanCopyItemRepresentation} Mandatory if others parameters are not set
     */
    public $copy;

    public static function getNonCopyRequiredObjectProperties() : array
    {
        return self::REQUIRED_NON_COPY_PROPERTIES;
    }
}
