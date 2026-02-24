<?php

declare(strict_types=1);

namespace Kachnitel\AdminBundle\Tests\Unit\Service\Preferences;

use Kachnitel\AdminBundle\Service\Preferences\SessionAdminPreferencesStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class SessionAdminPreferencesStorageTest extends TestCase
{
    private RequestStack $requestStack;
    private Session $session;
    private SessionAdminPreferencesStorage $storage;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->requestStack = new RequestStack();

        $request = new Request();
        $request->setSession($this->session);
        $this->requestStack->push($request);

        $this->storage = new SessionAdminPreferencesStorage($this->requestStack);
    }

    /**
     * @test
     */
    public function getReturnsDefaultValueWhenKeyNotSet(): void
    {
        $result = $this->storage->get('nonexistent.key', 'default');

        $this->assertSame('default', $result);
    }

    /**
     * @test
     */
    public function getReturnsNullByDefaultWhenKeyNotSet(): void
    {
        $result = $this->storage->get('nonexistent.key');

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function setAndGetPersistsValue(): void
    {
        $this->storage->set('test.key', ['value1', 'value2']);

        $result = $this->storage->get('test.key');

        $this->assertSame(['value1', 'value2'], $result);
    }

    /**
     * @test
     */
    public function setOverwritesExistingValue(): void
    {
        $this->storage->set('test.key', 'first');
        $this->storage->set('test.key', 'second');

        $result = $this->storage->get('test.key');

        $this->assertSame('second', $result);
    }

    /**
     * @test
     */
    public function differentKeysAreIndependent(): void
    {
        $this->storage->set('column_visibility.Product', ['id', 'name']);
        $this->storage->set('column_visibility.Order', ['status']);

        $this->assertSame(['id', 'name'], $this->storage->get('column_visibility.Product'));
        $this->assertSame(['status'], $this->storage->get('column_visibility.Order'));
    }

    /**
     * @test
     */
    public function supportsVariousDataTypes(): void
    {
        $this->storage->set('string', 'value');
        $this->storage->set('int', 42);
        $this->storage->set('array', ['a', 'b', 'c']);
        $this->storage->set('bool', true);

        $this->assertSame('value', $this->storage->get('string'));
        $this->assertSame(42, $this->storage->get('int'));
        $this->assertSame(['a', 'b', 'c'], $this->storage->get('array'));
        $this->assertTrue($this->storage->get('bool'));
    }
}
