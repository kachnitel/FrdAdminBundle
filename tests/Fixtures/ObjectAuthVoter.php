<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Fixtures;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Object-subject voter modeling the feature request's ContactVoter example:
 * a plain Symfony Voter — no bundle-specific interface — whose supports()
 * accepts an ObjectAuthEntity instance directly and grants or denies
 * per-instance based on its state ($kind), exactly the way an application's
 * own ContactVoter keys off Contact::$type.
 *
 * Votes false (deny) rather than abstaining for KIND_FORBIDDEN. That
 * distinction matters here: an abstain would fall through to whatever else
 * is registered for the attribute, whereas a real deny vote lets a test
 * assert this voter's own decision — not the absence of any other opinion —
 * is what produced the outcome.
 *
 * @extends Voter<string, ObjectAuthEntity>
 */
final class ObjectAuthVoter extends Voter
{
    private const SUPPORTED_ATTRIBUTES = [
        AdminEntityVoter::ADMIN_SHOW,
        AdminEntityVoter::ADMIN_NEW,
        AdminEntityVoter::ADMIN_EDIT,
        AdminEntityVoter::ADMIN_ARCHIVE,
        AdminEntityVoter::ADMIN_DELETE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof ObjectAuthEntity
            && in_array($attribute, self::SUPPORTED_ATTRIBUTES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $subject->getKind() === ObjectAuthEntity::KIND_ALLOWED;
    }
}
