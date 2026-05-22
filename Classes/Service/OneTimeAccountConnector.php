<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Connects to FE user accounts and sessions data created by the "onetimeaccount" extension.
 */
class OneTimeAccountConnector implements SingletonInterface
{
    private function getFrontendUserAuthenticationFromRequest(
        ServerRequestInterface $request
    ): FrontendUserAuthentication {
        $frontEndUserAuthentication = $request->getAttribute('frontend.user');
        \assert($frontEndUserAuthentication instanceof FrontendUserAuthentication);

        return $frontEndUserAuthentication;
    }

    /**
     * Returns the user UID of a FE user created by the "onetimeaccount" extension (without a FE login session).
     *
     * @return positive-int|null
     */
    public function getOneTimeAccountUserUid(ServerRequestInterface $request): ?int
    {
        $uid = $this
            ->getFrontendUserAuthenticationFromRequest($request)
            ->getSessionData('onetimeaccountUserUid');
        if (!\is_int($uid) || $uid <= 0) {
            return null;
        }

        return $uid;
    }

    /**
     * Destroys any onetimeaccount sessions (without login).
     *
     * If a onetimeaccount user UID is available in the session, it will be deleted.
     */
    public function destroyOneTimeSession(ServerRequestInterface $request): void
    {
        if (\is_int($this->getOneTimeAccountUserUid($request))) {
            $this
                ->getFrontendUserAuthenticationFromRequest($request)
                ->setAndSaveSessionData('onetimeaccountUserUid', null);
        }
    }
}
