<?php
/**
 * Copyright (c) Enalean, 2019. All Rights Reserved.
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

namespace Tuleap\Tracker\Workflow\PostAction\Update\Internal;

use DataAccessQueryException;
use Transition;
use Tuleap\Tracker\Workflow\PostAction\Update\PostActionCollection;

class CIBuildUpdater implements PostActionUpdater
{
    /**
     * @var CIBuildRepository
     */
    private $ci_build_repository;
    /**
     * @var CIBuildValidator
     */
    private $validator;

    public function __construct(CIBuildRepository $ci_build_repository, CIBuildValidator $validator)
    {
        $this->ci_build_repository = $ci_build_repository;
        $this->validator           = $validator;
    }

    /**
     * Update (and replace) all CI Build post actions with those included in given collection.
     * @throws DataAccessQueryException
     * @throws UnknownPostActionIdsException
     * @throws InvalidPostActionException
     */
    public function updateByTransition(PostActionCollection $actions, Transition $transition): void
    {
        $actions->validateCIBuildActions($this->validator);
        $existing_ids_collection = $this->ci_build_repository->findAllIdsByTransition($transition);
        $diff                    = $actions->compareCIBuildActionsTo($existing_ids_collection);

        $this->ci_build_repository->deleteAllByTransitionIfNotIn($transition, $diff->getUpdatedActions());

        foreach ($diff->getAddedActions() as $added_action) {
            $this->ci_build_repository->create($transition, $added_action);
        }
        foreach ($diff->getUpdatedActions() as $updated_action) {
            $this->ci_build_repository->update($updated_action);
        }
    }
}
