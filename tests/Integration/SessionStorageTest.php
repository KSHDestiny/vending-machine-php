<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\Role;
use App\Http\Session\DatabaseSessionHandler;
use App\Repositories\SessionRepository;
use Tests\Support\DatabaseTestCase;

/*
 * Sessions in the real test database: they survive a restart (a new handler), only a
 * hash of the session ID is stored, idle and absolute timeouts apply, and a user's
 * sessions can all be ended at once.
 */
final class SessionStorageTest extends DatabaseTestCase
{
    /** @var int The fake clock, in Unix seconds. */
    private int $now = 1_800_000_000;

    public function testASessionSurvivesARestartAndOnlyItsHashIsStored(): void
    {
        $user = $this->createUser();
        $data = serialize(['auth' => ['id' => $user->id, 'role' => 'user'], '_csrf' => 'token']);

        self::assertTrue($this->handler()->write('session-id-123', $data));
        $afterRestart = $this->handler();

        self::assertSame($data, $afterRestart->read('session-id-123'));
        $row = $this->database->table('sessions')->first();
        self::assertSame(hash('sha256', 'session-id-123'), $row['id'] ?? null, 'the raw session ID is never stored');
        self::assertSame($user->id, (int) ($row['user_id'] ?? 0));
        self::assertSame('', $afterRestart->read('another-id'));
    }

    public function testIdleSessionsExpireAfterTwoHours(): void
    {
        $handler = $this->handler();
        $handler->write('idle', serialize(['a' => 1]));

        $this->now += 7_199;
        self::assertNotSame('', $handler->read('idle'));
        self::assertTrue($handler->updateTimestamp('idle', serialize(['a' => 1])));

        $this->now += 7_201;
        self::assertSame('', $handler->read('idle'), 'two hours without a request');
        self::assertFalse($handler->validateId('idle'));
    }

    public function testActiveSessionsStillEndTwelveHoursAfterSignIn(): void
    {
        $handler = $this->handler();
        $handler->write('busy', serialize(['a' => 1]));

        for ($hour = 1; $hour <= 11; $hour++) {
            $this->now += 3_600;
            self::assertNotSame('', $handler->read('busy'), "hour {$hour}");
            $handler->updateTimestamp('busy', serialize(['a' => 1]));
        }
        $this->now += 3_601;

        self::assertSame('', $handler->read('busy'), 'twelve hours after it began, even though it was used every hour');
    }

    public function testLogoutAndCleanupDeleteRows(): void
    {
        $handler = $this->handler();
        $handler->write('kept', serialize([]));
        $handler->write('logged-out', serialize([]));
        $handler->write('stale', serialize([]));
        $this->now += 3_600;
        $handler->updateTimestamp('kept', serialize([]));
        $this->now += 4_000;

        self::assertTrue($handler->destroy('logged-out'));
        self::assertSame(1, $handler->gc(0), 'only the stale session is past the idle timeout');
        self::assertNotSame('', $handler->read('kept'));
        self::assertSame(1, $this->database->table('sessions')->count());
    }

    public function testAllOfAUsersSessionsCanBeEnded(): void
    {
        $buyer = $this->createUser();
        $other = $this->createUser(Role::User, 'other@example.com');
        $handler = $this->handler();
        $handler->write('phone', serialize(['auth' => ['id' => $buyer->id]]));
        $handler->write('laptop', serialize(['auth' => ['id' => $buyer->id]]));
        $handler->write('someone-else', serialize(['auth' => ['id' => $other->id]]));
        $handler->write('guest', serialize(['_csrf' => 'x']));

        self::assertSame(2, (new SessionRepository($this->database))->deleteForUser($buyer->id));

        self::assertSame('', $handler->read('phone'));
        self::assertSame('', $handler->read('laptop'));
        self::assertNotSame('', $handler->read('someone-else'));
        self::assertNotSame('', $handler->read('guest'));
    }

    /**
     * A new handler on the test database with the fake clock, as a fresh PHP process would create.
     * @return DatabaseSessionHandler
     */
    private function handler(): DatabaseSessionHandler
    {
        return new DatabaseSessionHandler(new SessionRepository($this->database), clock: fn (): int => $this->now);
    }
}
