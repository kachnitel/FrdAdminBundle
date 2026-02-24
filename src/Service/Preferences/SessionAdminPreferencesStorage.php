<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Service\Preferences;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Session-based storage for admin preferences.
 *
 * This is the default implementation provided by the bundle.
 * Preferences are stored in the user's session and reset when the session expires.
 *
 * For persistent preferences, consuming applications should implement
 * AdminPreferencesStorageInterface and alias it in their services configuration.
 */
class SessionAdminPreferencesStorage implements AdminPreferencesStorageInterface
{
    private const SESSION_KEY_PREFIX = 'kachnitel_admin.pref.';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $session = $this->requestStack->getSession();
        return $session->get(self::SESSION_KEY_PREFIX . $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY_PREFIX . $key, $value);
    }
}
