<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;
use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Изменяющие запросы одной сессии идут по очереди: параллельная запись затёрла бы сессию устаревшим снимком.
 * GET/HEAD идут параллельно: сессию записывают, если изменили её, а иначе продлевают срок жизни.
 */
class StartBlockingSession extends StartSession
{
    /** Запись в сессию длится миллисекунды; ожидание ограничено, чтобы зависший запрос держал соседние недолго. */
    private const WAIT_SECONDS = 3;

    private const LOCK_SECONDS = 10;

    private array $startedWith = [];

    public function __construct(SessionManager $manager)
    {
        parent::__construct($manager, fn () => app(CacheFactory::class));
    }

    public function handle($request, Closure $next)
    {
        if (! $this->sessionConfigured()) {
            return $next($request);
        }

        $session = $this->getSession($request);

        if ($this->isReadOnly($request)) {
            return $this->handleStatefulRequest($request, $session, $next);
        }

        return $this->handleRequestWhileBlocking($request, $session, $next);
    }

    /** При сбое хранилища блокировок или истечении ожидания запрос выполняется как обычный. */
    protected function handleRequestWhileBlocking(Request $request, $session, Closure $next)
    {
        try {
            $lock = $this->cache($this->manager->blockDriver())
                ->lock('session:'.$session->getId(), self::LOCK_SECONDS)
                ->betweenBlockedAttemptsSleepFor(50);
            $lock->block(self::WAIT_SECONDS);
        } catch (Throwable $e) {
            report($e);
            $lock = null;
        }

        try {
            return $this->handleStatefulRequest($request, $session, $next);
        } finally {
            $lock?->release();
        }
    }

    protected function isReadOnly(Request $request): bool
    {
        return $request->isMethodSafe();
    }

    protected function startSession(Request $request, $session)
    {
        $session = parent::startSession($request, $session);
        $this->startedWith = $session->all();

        return $session;
    }

    protected function storeCurrentUrl(Request $request, $session)
    {
        // Предыдущий URL нужен только веб-формам.
    }

    protected function saveSession($request)
    {
        if (! $this->isReadOnly($request) || $this->manager->driver()->all() !== $this->startedWith) {
            parent::saveSession($request);

            return;
        }

        $this->touchSession($this->manager->driver()->getId());
    }

    /** Продлевает срок жизни сессии. */
    protected function touchSession(string $id): void
    {
        $handler = $this->manager->driver()->getHandler();

        if ($handler instanceof CacheBasedSessionHandler && $handler->getCache()->getStore() instanceof RedisStore) {
            $store = $handler->getCache()->getStore();
            $store->connection()->expire($store->getPrefix().$id, $this->getSessionLifetimeInSeconds());
        } elseif ($handler instanceof DatabaseSessionHandler) {
            DB::connection(config('session.connection'))
                ->table(config('session.table'))
                ->where('id', $id)
                ->update(['last_activity' => time()]);
        } else {
            $this->manager->driver()->save();
        }
    }
}
