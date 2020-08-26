<?php
/**
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

namespace Tuleap\HelpDropdown;

use PFUser;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tuleap\Sanitizer\URISanitizer;

class HelpDropdownPresenterBuilder
{
    /**
     * @var EventDispatcherInterface
     */
    private $event_dispatcher;
    /**
     * @var ReleaseNoteManager
     */
    private $release_note_manager;
    /**
     * @var URISanitizer
     */
    private $uri_sanitizer;

    public function __construct(
        ReleaseNoteManager $release_note_manager,
        EventDispatcherInterface $event_dispatcher,
        URISanitizer $uri_sanitizer
    ) {
        $this->event_dispatcher     = $event_dispatcher;
        $this->uri_sanitizer        = $uri_sanitizer;
        $this->release_note_manager = $release_note_manager;
    }

    public function build(PFUser $current_user, string $tuleap_version): HelpDropdownPresenter
    {
        $documentation = "/doc/" . urlencode($current_user->getShortLocale()) . "/";

        $main_items = [
            HelpLinkPresenter::build(
                dgettext(
                    'tuleap-core',
                    'Get help'
                ),
                "/help/",
                "fa-life-saver",
                $this->uri_sanitizer
            ),
            HelpLinkPresenter::build(
                dgettext(
                    'tuleap-core',
                    'Documentation'
                ),
                $documentation,
                "fa-book",
                $this->uri_sanitizer
            )
        ];

        $release_note = $this->getReleaseNoteLink($tuleap_version);

        if ($current_user->isAnonymous()) {
            $has_release_note_been_seen = true;
        } else {
            $has_release_note_been_seen = (bool) $current_user->getPreference("has_release_note_been_seen");
        }

        $explorer_endpoint_event = $this->event_dispatcher->dispatch(new \Tuleap\REST\ExplorerEndpointAvailableEvent());

        return new HelpDropdownPresenter(
            $main_items,
            $explorer_endpoint_event->getEndpointURL(),
            $release_note,
            $has_release_note_been_seen
        );
    }

    private function getReleaseNoteLink(string $tuleap_version): HelpLinkPresenter
    {
        $release_note_link = $this->release_note_manager->getReleaseNoteLink($tuleap_version);

        return HelpLinkPresenter::build(
            dgettext(
                'tuleap-core',
                'Release Note'
            ),
            $release_note_link,
            "fa-star",
            $this->uri_sanitizer
        );
    }
}
