<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\LoginCooldown;
use App\Http\Session\Session;
use PHPUnit\Framework\TestCase;
use Tests\Support\ArraySessionStore;

/*
 * The web login cooldown kept in the visitor's session: the 5th wrong password in a row
 * starts a 30-second wait, which then ends on its own; signing in clears the count.
 */
final class LoginCooldownTest extends TestCase
{
    private int $now = 1_800_000_000;

    private LoginCooldown $cooldown;

    protected function setUp(): void
    {
        $this->cooldown = new LoginCooldown(new Session(new ArraySessionStore()), clock: fn (): int => $this->now);
    }

    public function testTheFifthFailureStartsAThirtySecondWait(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->cooldown->failed();
            self::assertSame(0, $this->cooldown->secondsLeft(), "after {$i} failures");
        }

        $this->cooldown->failed();
        self::assertSame(30, $this->cooldown->secondsLeft());

        $this->now += 12;
        self::assertSame(18, $this->cooldown->secondsLeft());

        $this->now += 18;
        self::assertSame(0, $this->cooldown->secondsLeft(), 'the wait ends on its own');
    }

    public function testCountingStartsAgainAfterAWaitOrASuccess(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->cooldown->failed();
        }
        $this->now += 30;
        for ($i = 1; $i <= 4; $i++) {
            $this->cooldown->failed();
        }
        self::assertSame(0, $this->cooldown->secondsLeft(), 'a new round of five after the wait');

        $this->cooldown->succeeded();
        for ($i = 1; $i <= 4; $i++) {
            $this->cooldown->failed();
        }
        self::assertSame(0, $this->cooldown->secondsLeft(), 'signing in reset the count');
    }
}
