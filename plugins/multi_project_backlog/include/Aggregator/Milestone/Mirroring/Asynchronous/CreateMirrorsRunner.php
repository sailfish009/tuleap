<?php
/*
 * Copyright (c) Enalean, 2020 - Present. All Rights Reserved.
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

namespace Tuleap\MultiProjectBacklog\Aggregator\Milestone\Mirroring\Asynchronous;

use BackendLogger;
use Exception;
use PFUser;
use Tracker_Artifact;
use Tracker_Artifact_Changeset;
use Tracker_Artifact_ChangesetFactoryBuilder;
use Tracker_ArtifactFactory;
use Tuleap\Queue\QueueFactory;
use Tuleap\Queue\Worker;
use Tuleap\Queue\WorkerEvent;
use UserManager;

class CreateMirrorsRunner
{

    private const TOPIC = 'tuleap.tracker.artifact.creation';
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var QueueFactory
     */
    private $queue_factory;
    /**
     * @var Tracker_ArtifactFactory
     */
    private $artifact_factory;
    /**
     * @var UserManager
     */
    private $user_manager;
    /**
     * @var PendingArtifactCreationDao
     */
    private $pending_artifact_creation_dao;
    /**
     * @var \Tracker_Artifact_ChangesetFactory
     */
    private $changeset_factory;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        QueueFactory $queue_factory,
        Tracker_ArtifactFactory $artifact_factory,
        UserManager $user_manager,
        PendingArtifactCreationDao $pending_artifact_creation_dao,
        \Tracker_Artifact_ChangesetFactory $changeset_factory
    ) {
        $this->logger                        = $logger;
        $this->queue_factory                 = $queue_factory;
        $this->artifact_factory              = $artifact_factory;
        $this->user_manager                  = $user_manager;
        $this->pending_artifact_creation_dao = $pending_artifact_creation_dao;
        $this->changeset_factory             = $changeset_factory;
    }

    public static function build(): self
    {
        $logger = BackendLogger::getDefaultLogger("multi_project_backlog_syslog");

        return new self(
            $logger,
            new QueueFactory($logger),
            Tracker_ArtifactFactory::instance(),
            UserManager::instance(),
            new PendingArtifactCreationDao(),
            Tracker_Artifact_ChangesetFactoryBuilder::build()
        );
    }

    /**
     * @throw MilestoneMirroringException
     */
    public function addListener(WorkerEvent $event): void
    {
        if ((string) $event->getEventName() === self::TOPIC) {
            $message = $event->getPayload();

            $pending_artifact = $this->pending_artifact_creation_dao->getPendingArtifactById(
                $message['artifact_id'],
                $message['user_id']
            );

            if ($pending_artifact === null) {
                throw new PendingArtifactNotFoundException($message['artifact_id'], $message['user_id']);
            }

            $artifact = $this->artifact_factory->getArtifactById($pending_artifact['aggregator_artifact_id']);
            if (! $artifact) {
                throw new PendingArtifactNotFoundException($pending_artifact['aggregator_artifact_id'], $pending_artifact['user_id']);
            }

            $user     = $this->user_manager->getUserById($pending_artifact['user_id']);
            if (! $user) {
                throw new PendingArtifactUserNotFoundException($pending_artifact['aggregator_artifact_id'], $pending_artifact['user_id']);
            }

            $changeset = $this->changeset_factory->getChangeset($artifact, $pending_artifact['changeset_id']);
            if (! $changeset) {
                throw new PendingArtifactChangesetNotFoundException($pending_artifact['aggregator_artifact_id'], $pending_artifact['changeset_id']);
            }

            $this->processArtifactCreation($artifact, $user, $changeset);
        }
    }

    private function processArtifactCreation(Tracker_Artifact $artifact, PFUser $user, Tracker_Artifact_Changeset $changeset): void
    {
        $task = CreateMirrorsTask::build();
        $task->createMirrors($artifact, $user, $changeset);
    }

    public function executeMirrorsCreation(Tracker_Artifact $artifact, PFUser $user, Tracker_Artifact_Changeset $changeset): void
    {
        try {
            $queue = $this->queue_factory->getPersistentQueue(Worker::EVENT_QUEUE_NAME, QueueFactory::REDIS);
            $queue->pushSinglePersistentMessage(
                self::TOPIC,
                [
                    'artifact_id' => (int) $artifact->getId(),
                    'user_id'     => (int) $user->getId(),
                ]
            );
        } catch (Exception $exception) {
            $this->logger->error("Unable to queue artifact mirrors creation for artifact #{$artifact->getId()}");
            $this->processArtifactCreation($artifact, $user, $changeset);
        }
    }
}
