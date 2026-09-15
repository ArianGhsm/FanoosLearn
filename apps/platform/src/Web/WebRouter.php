<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Identity\AuthService;
use Throwable;

/**
 * Maps a request path to a page.
 *
 * Every route is declared here, exactly once. The web server (Apache via
 * apps/platform/public/.htaccess in this deployment) sends anything that is
 * not a real file to this front controller, so an unknown path must produce
 * a real 404 page rather than silently rendering the home page -- otherwise
 * a typo in a link looks like a working page with the wrong content.
 */
final class WebRouter
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly PageRenderer $renderer,
        private readonly ?ExamService $exams = null,
    ) {
    }

    /** @return array{status:int,headers:list<string>,body:string} */
    public function handle(string $path, array $cookies, array $query = []): array
    {
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?: '/', '/');
        $viewer = $this->viewer($cookies);

        if ($path === '/') {
            // A signed-in visitor asking for the front door wants their own
            // space, not the sales pitch.
            return $viewer === null
                ? $this->page(200, (new LandingPage($this->renderer))->render())
                : $this->redirect('/app');
        }

        if ($path === '/login') {
            return $viewer === null
                ? $this->page(200, (new LoginPage($this->renderer))->render())
                : $this->redirect('/app');
        }

        if ($path === '/recovery') {
            // Always render, session or not. Someone recovering access
            // usually *does* have a session -- for the wrong account, or a
            // stale one -- and that is exactly why they were sent a link.
            // Redirecting them to /app bounced the link to the home page and
            // did nothing, which is what it did in production. Redeeming
            // replaces whatever session the browser was holding.
            return $this->page(200, (new RecoveryPage($this->renderer))->render());
        }

        if ($path === '/account') {
            return $viewer === null
                ? $this->redirect('/login')
                : $this->page(200, (new AccountPage($this->renderer))->render($viewer));
        }

        if ($path === '/app') {
            return $viewer === null
                ? $this->redirect('/login')
                : $this->page(200, (new HomePage($this->renderer))->render($viewer, isset($query['switch'])));
        }

        if ($path === '/app/exams') {
            if ($viewer === null) {
                return $this->redirect('/login');
            }
            // Every exam read is workspace-scoped; without a selected
            // workspace there is nothing to list, so send them to choose one
            // rather than render a page that can only be empty.
            return $viewer->workspaceId === null
                ? $this->redirect('/app')
                : $this->page(200, (new ExamsPage($this->renderer))->render($viewer));
        }

        if ($path === '/app/exams/mistakes') {
            if ($viewer === null) {
                return $this->redirect('/login');
            }
            return $viewer->workspaceId === null
                ? $this->redirect('/app')
                : $this->page(200, (new MistakesReviewPage($this->renderer))->render($viewer));
        }

        if (preg_match('#^/app/exams/([0-9a-f-]{36})$#', $path, $match) === 1) {
            if ($viewer === null) {
                return $this->redirect('/login');
            }
            if ($viewer->workspaceId === null) {
                return $this->redirect('/app');
            }
            return $this->page(200, (new ExamAttemptPage($this->renderer))->render(
                $viewer, $match[1], $this->assessmentIntro($viewer->userId, $viewer->workspaceId, $match[1]),
            ));
        }

        return $this->page(404, (new NotFoundPage($this->renderer))->render($viewer));
    }

    /**
     * Resolves the signed-in viewer from the session cookie, or null.
     *
     * An invalid, expired or revoked session is not an error here: it means
     * "signed out", and the caller redirects to the sign-in page. Throwing
     * would turn an ordinary expiry into a 500.
     */
    private function viewer(array $cookies): ?ViewerContext
    {
        $token = (string) ($cookies['fanoos_session'] ?? '');
        if ($token === '') {
            return null;
        }
        try {
            $session = $this->auth->authenticate($token);
            $account = $this->auth->account($session);
        } catch (Throwable) {
            return null;
        }

        $workspaceName = null;
        if ($session->selectedWorkspaceId !== null) {
            foreach ($account['workspaces'] ?? [] as $workspace) {
                if (($workspace['id'] ?? null) === $session->selectedWorkspaceId) {
                    $workspaceName = (string) ($workspace['name'] ?? '');
                    break;
                }
            }
        }

        // account() nests the person under 'user'; reading display_name from
        // the top level silently yields '' and every signed-in page greets a
        // nameless visitor. The rendering tests construct a ViewerContext
        // directly, so only a test that goes through this method catches it.
        $person = is_array($account['user'] ?? null) ? $account['user'] : [];

        return new ViewerContext(
            $session->userId,
            (string) ($person['display_name'] ?? ''),
            $session->csrfToken,
            $session->selectedWorkspaceId,
            $workspaceName === '' ? null : $workspaceName,
        );
    }

    /**
     * The one catalogue-shaped row for the exam being opened, embedded into
     * the rendered page so the runner script does not have to fetch the
     * whole catalogue just to find the one assessment it already knows the
     * id of (see ExamAttemptPage, ExamService::catalogEntry()).
     *
     * null covers every case where the client-side fallback must run
     * instead: the exam service is not configured, the id does not resolve
     * to a published assessment in this workspace, or access is denied --
     * exactly the same "not available" outcome the page already showed via
     * a failed client-side lookup, just resolved here instead. Never lets an
     * exception here turn a normal not-found/denied case into a 500.
     *
     * @return array<string, mixed>|null
     */
    private function assessmentIntro(string $userId, string $workspaceId, string $assessmentId): ?array
    {
        if ($this->exams === null) {
            return null;
        }
        try {
            return $this->exams->catalogEntry($userId, $workspaceId, $assessmentId);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{status:int,headers:list<string>,body:string} */
    private function page(int $status, string $body): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type: text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /** @return array{status:int,headers:list<string>,body:string} */
    private function redirect(string $location): array
    {
        return ['status' => 302, 'headers' => ['Location: ' . $location], 'body' => ''];
    }
}
