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

namespace Tuleap\OAuth2Server\Grant\AuthorizationCode;

use DateInterval;
use Tuleap\Authentication\SplitToken\SplitToken;
use Tuleap\Authentication\SplitToken\SplitTokenFormatter;
use Tuleap\Authentication\SplitToken\SplitTokenVerificationString;
use Tuleap\Authentication\SplitToken\SplitTokenVerificationStringHasher;
use Tuleap\Cryptography\ConcealedString;
use Tuleap\OAuth2Server\App\OAuth2App;

class OAuth2AuthorizationCodeCreator
{
    /**
     * @var SplitTokenFormatter
     */
    private $authorization_code_formatter;
    /**
     * @var SplitTokenVerificationStringHasher
     */
    private $hasher;
    /**
     * @var OAuth2AuthorizationCodeDAO
     */
    private $authorization_code_dao;
    /**
     * @var DateInterval
     */
    private $access_token_expiration_delay;

    public function __construct(
        SplitTokenFormatter $authorization_code_formatter,
        SplitTokenVerificationStringHasher $hasher,
        OAuth2AuthorizationCodeDAO $authorization_code_dao,
        DateInterval $access_token_expiration_delay
    ) {
        $this->authorization_code_formatter  = $authorization_code_formatter;
        $this->hasher                        = $hasher;
        $this->authorization_code_dao        = $authorization_code_dao;
        $this->access_token_expiration_delay = $access_token_expiration_delay;
    }

    public function createAuthorizationCodeIdentifier(\DateTimeImmutable $current_time, OAuth2App $app, \PFUser $user): ConcealedString
    {
        $verification_string = SplitTokenVerificationString::generateNewSplitTokenVerificationString();
        $expiration_date     = $current_time->add($this->access_token_expiration_delay);

        $authorization_code_id = $this->authorization_code_dao->create(
            $app->getId(),
            (int) $user->getId(),
            $this->hasher->computeHash($verification_string),
            $expiration_date->getTimestamp()
        );

        return $this->authorization_code_formatter->getIdentifier(new SplitToken($authorization_code_id, $verification_string));
    }
}