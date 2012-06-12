<?php
/**
 * Copyright (c) STMicroelectronics 2012. All rights reserved
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

require_once('Tracker_DateReminder.class.php');
require_once('dao/Tracker_DateReminderDao.class.php');
require_once 'common/date/DateHelper.class.php';
require_once('common/include/CSRFSynchronizerToken.class.php');

class Tracker_DateReminderFactory {

    protected $tracker;

    /**
     * Constructor of the class
     *
     * @param Tracker $tracker Tracker associated to the manager
     *
     * @return Void
     */
    public function __construct(Tracker $tracker) {
        $this->tracker = $tracker;
        $this->csrf    = new CSRFSynchronizerToken(TRACKER_BASE_URL.'/?func=admin-notifications&tracker='.$this->tracker->id.'&action=new_reminder');
    }

    /**
     * Obtain the tracker associated to the manager
     *
     * @return Tracker
     */
    public function getTracker(){
        return $this->tracker;
    }

    /**
     * Retrieve all date reminders for a given tracker
     *
     * @return Array
     */
    public function getTrackerReminders() {
        $reminders          = array();
        $reminderManagerDao = $this->getDao();
        $dar = $reminderManagerDao->getDateReminders($this->tracker->getId());
        if ($dar && !$dar->isError()) {
            foreach ($dar as $row) {
                $reminders[] = $this->getInstanceFromRow($row);
            }
        }
        return $reminders;
    }

    /**
     * Add new reminder
     * 
     * @param HTTPRequest $request request object
     *
     * @return Boolean
     */
    public function addNewReminder(HTTPRequest $request) {
        $this->csrf->check();
        $reminderRenderer   = new Tracker_DateReminderRenderer($this->tracker);
        $trackerId          = $reminderRenderer->validateTrackerId($request);
        $fieldId            = $reminderRenderer->validateFieldId($request);
        $notificationType   = $reminderRenderer->validateNotificationType($request);
        //$ugroups           = $this->validateReminderUgroups($request);
        $ugroups            = join(",", $request->get('reminder_ugroup'));
        $distance           = $reminderRenderer->validateDistance($request);
        $historyDao         = new ProjectHistoryDao(CodendiDataAccess::instance());
        $historyDao->groupAddHistory("tracker_date_reminder_add", $this->tracker->getName().":".$fieldId, $this->tracker->getGroupId(), array($distance.' Day(s), Type: '.$notificationType.' Ugroup(s): '.$ugroups));
        $reminderDao = $this->getDao();
        return $reminderDao->addDateReminder($trackerId, $fieldId, $ugroups, $notificationType, $distance);
    }

    /**
     * Delete a list of date reminders
     *
     * @param Array $remindersIds List of Id of reminders
     *
     * @return Boolean
     */
    public function deleteTrackerReminders($remindersIds) {
        $historyDao = new ProjectHistoryDao(CodendiDataAccess::instance());
        $historyDao->groupAddHistory("tracker_date_reminder_delete", $this->tracker->getName(), $this->tracker->getGroupId(), $remindersIds);
        $reminderManagerDao = $this->getDao();
        return $reminderManagerDao->deleteReminders($remindersIds);
    }

    /**
     * Build a reminder instance
     *
     * @param array $row The data describing the reminder
     *
     * @return Tracker_DateReminder
     */
    public function getInstanceFromRow($row) {
        return new Tracker_DateReminder($row['reminder_id'],
                                          $row['tracker_id'],
                                          $row['field_id'],
                                          $row['ugroups'],
                                          $row['notification_type'],
                                          $row['distance'],
                                          $row['status']);
    }

    /**
     * Get the Tracker_DateReminder dao
     *
     * @return Tracker_DateReminderDao
     */
    protected function getDao() {
        return new Tracker_DateReminderDao();
    }

    /**
     * Get the reminder
     *
     * @param Integer  $reminderId    The reminder id
     *
     * @return Tracker_DateReminder
     */
    public function getReminder($reminderId) {
        if ($row = $this->getDao()->searchById($reminderId)->getRow()) {
            return $this->getInstanceFromRow($row);
        }
        return null;
    }

    /** Get artifacts that will send notification for a reminder
     *
     * @param Tracker_DateReminder $reminder Reminder on which the notification is based on
     *
     * @return Array
     */
    public function getArtifactsByreminder(Tracker_DateReminder $reminder) {
        $artifacts = array();
        $date      = DateHelper::getDistantDateFromToday($reminder->getDistance(), $reminder->getNotificationType());
        // @TODO: Include "last update date" & "submitted on" as types of date fields
        $dao = new Tracker_FormElement_Field_Value_DateDao();
        $dar = $dao->getArtifactsByFieldAndValue($reminder->getFieldId(), $date);
        if ($dar && !$dar->isError()) {
            $artifactFactory = Tracker_ArtifactFactory();
            foreach ($dar as $row) {
                $artifacts[] = $artifactFactory->getArtifactById($row['artifact_id']);
            }
        }
        return $artifacts;
    }

    /**
     * Get an instance of UserManager
     *
     * @return UserManager
     */
    public function getUserManager() {
        return UserManager::instance();
    }

}

?>