<?php
/*
 * Copyright (c) Enalean, 2020-Present. All Rights Reserved.
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

declare(strict_types=1);

namespace Tuleap\MultiProjectBacklog\Aggregator\Milestone\Mirroring\Status;

use Tuleap\MultiProjectBacklog\Aggregator\Milestone\Mirroring\MilestoneMirroringException;

final class NoStatusChangesetValueException extends \RuntimeException implements MilestoneMirroringException
{
    public function __construct(int $changeset_id, int $status_field_id)
    {
        parent::__construct("Expected changeset #$changeset_id to have a value for Status field #$status_field_id, but this value was not found");
    }
}
