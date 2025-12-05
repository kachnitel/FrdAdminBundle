<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Functional;

use Kachnitel\AdminBundle\Security\AdminEntityVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Test voter that always grants access for functional tests.
 * This allows testing component functionality without fighting authentication in test environment.
 *
 * @extends Voter<string, string>
 */
class TestAdminEntityVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            AdminEntityVoter::ADMIN_INDEX,
            AdminEntityVoter::ADMIN_SHOW,
            AdminEntityVoter::ADMIN_NEW,
            AdminEntityVoter::ADMIN_EDIT,
            AdminEntityVoter::ADMIN_DELETE,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        // Always grant access in tests
        return true;
    }
}
