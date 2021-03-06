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

namespace Tuleap\MultiProjectBacklog\Aggregator\Milestone;

class SynchronizedFieldCollectionBuilder
{
    /**
     * @var SynchronizedFieldsGatherer
     */
    private $fields_gatherer;

    public function __construct(SynchronizedFieldsGatherer $fields_gatherer)
    {
        $this->fields_gatherer = $fields_gatherer;
    }

    /**
     * @throws SynchronizedFieldRetrievalException
     */
    public function buildFromMilestoneTrackers(
        MilestoneTrackerCollection $milestone_tracker_collection
    ): SynchronizedFieldCollection {
        $fields = [];
        foreach ($milestone_tracker_collection->getMilestoneTrackers() as $milestone_tracker) {
            $gathered_fields = $this->fields_gatherer->gather($milestone_tracker);
            foreach ($gathered_fields->toArrayOfFields() as $gathered_field) {
                $fields[] = $gathered_field;
            }
        }
        return new SynchronizedFieldCollection($fields);
    }
}
