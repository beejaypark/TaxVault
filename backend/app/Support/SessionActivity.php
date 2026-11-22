<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;

final class SessionActivity
{
    public function touch(Session $session): void
    {
        Cache::put($this->key($session), true, now()->addMinutes((int) config('session.lifetime')));
    }

    public function active(Session $session): bool
    {
        return Cache::has($this->key($session));
    }

    public function renew(Session $session): bool
    {
        return Cache::lock($this->key($session).':lock', 5)->block(2, function () use ($session): bool {
            if (! $this->active($session)) {
                return false;
            }

            $this->touch($session);

            return true;
        });
    }

    public function forget(Session $session): void
    {
        Cache::lock($this->key($session).':lock', 5)->block(2, fn () => Cache::forget($this->key($session)));
    }

    private function key(Session $session): string
    {
        return 'session-activity:'.hash('sha256', $session->getId());
    }
}
