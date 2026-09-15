<?php

declare(strict_types=1);

namespace Fanoos\Tests\Website;

use Fanoos\Platform\Web\AssetVersioner;
use Fanoos\Platform\Web\LandingPage;
use Fanoos\Platform\Web\LoginPage;
use Fanoos\Platform\Web\HomePage;
use Fanoos\Platform\Web\AccountPage;
use Fanoos\Platform\Web\ExamAttemptPage;
use Fanoos\Platform\Web\ExamsPage;
use Fanoos\Platform\Web\NotFoundPage;
use Fanoos\Platform\Web\PageRenderer;
use Fanoos\Platform\Web\RecoveryPage;
use Fanoos\Platform\Web\ViewerContext;
use RuntimeException;

/**
 * Rendering tests for the website's pages.
 *
 * These assert what a visitor's browser actually receives. The layer this
 * replaced was covered only by regex greps over its own source, which is why
 * 703KB of it could sit in the repository with nothing proving any of it
 * worked.
 *
 * No database: pages take a ViewerContext, so what they render for a given
 * viewer is testable without standing up a session.
 */
final class WebRenderingTest
{
    private int $assertions = 0;

    public function __construct(private readonly string $root)
    {
    }

    public function run(): int
    {
        $renderer = new PageRenderer(new AssetVersioner($this->root . '/apps/platform/public'));

        $this->landingIsPublicAndClaimsNothingItCannotBack($renderer);
        $this->signedOutPagesCarryNoCsrfToken($renderer);
        $this->signedInPagesCarryTheCsrfTokenAndTheChrome($renderer);
        $this->navigationHidesWorkspaceAreasUntilOneIsSelected($renderer);
        $this->displayNamesAreEscapedNotInterpolated($renderer);
        $this->homeShowsTheChooserWhenAskedToSwitch($renderer);
        $this->notFoundSaysTheAddressIsWrong($renderer);
        $this->everyPageIsRightToLeftPersian($renderer);
        $this->examPagesCarryTheWorkspaceButNoQuestionContent($renderer);
        $this->everySignedInPageOffersAWayOut($renderer);
        $this->recoveryPageChecksTheLinkClientSideAndCarriesNoToken($renderer);
        $this->noPageReferencesTheRemovedDisplayFace($renderer);

        return $this->assertions;
    }

    private function landingIsPublicAndClaimsNothingItCannotBack(PageRenderer $renderer): void
    {
        $html = (new LandingPage($renderer))->render();

        $this->assert(str_contains($html, 'ورود به فانوس'), 'Landing page must offer a way in.');
        // The public page must not invent social proof or name a tenant --
        // FANOOS serves any cohort at any university, and a landing page that
        // says otherwise is both untrue and a product-scope regression.
        foreach (['دندانپزشکی', 'هزار دانشجو', 'میلیون', '۱۴۰۲'] as $forbidden) {
            $this->assert(!str_contains($html, $forbidden), "Landing page must not claim or name: {$forbidden}");
        }
    }

    private function signedOutPagesCarryNoCsrfToken(PageRenderer $renderer): void
    {
        foreach ([(new LandingPage($renderer))->render(), (new LoginPage($renderer))->render()] as $html) {
            $this->assert(!str_contains($html, 'fanoos-csrf'), 'A signed-out page must not carry a CSRF token.');
            $this->assert(!str_contains($html, 'f-header'), 'A signed-out page must not render the signed-in chrome.');
        }
    }

    private function signedInPagesCarryTheCsrfTokenAndTheChrome(PageRenderer $renderer): void
    {
        $viewer = new ViewerContext('u1', 'آرین', 'csrf-abc123', 'w1', 'دندانپزشکی ۱۴۰۲');
        $html = (new HomePage($renderer))->render($viewer);

        $this->assert(
            str_contains($html, '<meta name="fanoos-csrf" content="csrf-abc123">'),
            'A signed-in page must embed the CSRF token for its scripts.',
        );
        $this->assert(str_contains($html, 'f-header'), 'A signed-in page must render the shared chrome.');
        $this->assert(str_contains($html, 'دندانپزشکی ۱۴۰۲'), 'The chrome must name the selected workspace.');
    }

    private function navigationHidesWorkspaceAreasUntilOneIsSelected(PageRenderer $renderer): void
    {
        $without = new ViewerContext('u1', 'آرین', 'c', null, null);
        $with = new ViewerContext('u1', 'آرین', 'c', 'w1', 'کلاس من');

        // Asserts the intent, not a count: workspace-scoped areas stay hidden
        // until there is a workspace, while areas that need none -- the
        // account, and with it signing out -- are always reachable.
        $keysWithout = array_column($without->navigation(), 'key');
        $this->assert(!in_array('exams', $keysWithout, true), 'A workspace-scoped area must be hidden before a workspace is chosen.');
        $this->assert(in_array('account', $keysWithout, true), 'The account area must be reachable even before a workspace is chosen.');
        $this->assert(
            !str_contains((new HomePage($renderer))->render($without), '/app/exams'),
            'A workspace-scoped area must not be linked before a workspace is selected: the link could only fail.',
        );
        $this->assert(
            str_contains((new HomePage($renderer))->render($with), '/app/exams'),
            'Workspace-scoped areas must appear once a workspace is selected.',
        );
    }

    private function displayNamesAreEscapedNotInterpolated(PageRenderer $renderer): void
    {
        // A display name comes from user input and reaches every page's
        // chrome. If it were interpolated raw, one member of a class could
        // run script in every classmate's browser.
        $viewer = new ViewerContext('u1', '<script>alert(1)</script>', 'c', 'w1', '<img src=x onerror=1>');
        $html = (new HomePage($renderer))->render($viewer);

        $this->assert(!str_contains($html, '<script>alert(1)</script>'), 'Display name must be escaped.');
        $this->assert(!str_contains($html, '<img src=x'), 'Workspace name must be escaped.');
        $this->assert(str_contains($html, '&lt;script&gt;'), 'Display name must appear, escaped.');
    }

    private function homeShowsTheChooserWhenAskedToSwitch(PageRenderer $renderer): void
    {
        $viewer = new ViewerContext('u1', 'آرین', 'c', 'w1', 'کلاس من');

        $this->assert(
            !str_contains((new HomePage($renderer))->render($viewer), 'workspace-list'),
            'A viewer with a workspace lands on their areas, not the chooser.',
        );
        $this->assert(
            str_contains((new HomePage($renderer))->render($viewer, true), 'workspace-list'),
            'Asking to switch must actually reach the chooser.',
        );
    }

    private function notFoundSaysTheAddressIsWrong(PageRenderer $renderer): void
    {
        $html = (new NotFoundPage($renderer))->render(null);

        $this->assert(str_contains($html, 'وجود ندارد'), 'A 404 must say the address does not exist.');
    }

    private function everyPageIsRightToLeftPersian(PageRenderer $renderer): void
    {
        $viewer = new ViewerContext('u1', 'آرین', 'c', 'w1', 'کلاس من');
        $pages = [
            (new LandingPage($renderer))->render(),
            (new LoginPage($renderer))->render(),
            (new HomePage($renderer))->render($viewer),
            (new NotFoundPage($renderer))->render($viewer),
        ];

        foreach ($pages as $html) {
            $this->assert(str_contains($html, '<html lang="fa" dir="rtl">'), 'Every page must declare Persian RTL.');
            $this->assert(str_contains($html, 'id="main"'), 'Every page must have the skip-link target.');
            // Assets are behind an immutable release symlink, so an unversioned
            // URL hands a returning visitor a file from a release that is gone.
            $this->assert(
                !preg_match('/href="\/assets\/[^"?]+"/', $html),
                'Every stylesheet URL must carry a cache-busting version.',
            );
        }
    }

    private function examPagesCarryTheWorkspaceButNoQuestionContent(PageRenderer $renderer): void
    {
        $viewer = new ViewerContext('u1', 'آرین', 'csrf-x', 'ws-9', 'کلاس من');
        $catalogue = (new ExamsPage($renderer))->render($viewer);
        $attempt = (new ExamAttemptPage($renderer))->render($viewer, '11111111-2222-4333-8444-555555555555');

        foreach ([$catalogue, $attempt] as $html) {
            $this->assert(
                str_contains($html, '<meta name="fanoos-workspace" content="ws-9">'),
                'Exam pages must carry the selected workspace so scripts never take it from the URL.',
            );
        }
        $this->assert(
            str_contains($attempt, 'data-assessment="11111111-2222-4333-8444-555555555555"'),
            'The runner page must carry the assessment it is for.',
        );
        // The whole point of serving questions one at a time is that a paper is
        // never delivered in one response. Rendering any of it into this
        // document would put it straight back.
        foreach (['choices', 'prompt', 'answer', 'explanation'] as $leak) {
            $this->assert(!str_contains($attempt, '"' . $leak . '"'), "The runner document must not carry question data: {$leak}");
        }
    }

    private function recoveryPageChecksTheLinkClientSideAndCarriesNoToken(PageRenderer $renderer): void
    {
        $html = (new RecoveryPage($renderer))->render();

        $this->assert(!str_contains($html, 'fanoos-csrf'), 'The recovery page is reached signed-out and must carry no CSRF token.');
        $this->assert(str_contains($html, 'id="recovery-checking"'), 'The recovery page must show a checking state while it redeems the link.');
        $this->assert(str_contains($html, 'id="recovery-failed"') && str_contains($html, 'recovery-failed-text'), 'The recovery page must have a failure state for an invalid/used/expired link.');
        $this->assert(str_contains($html, 'id="recovery-form"') && str_contains($html, 'name="new_password"'), 'The recovery page must offer a way to set a new password.');
        // The server never sees the query string's token here (it is read
        // and sent by the client script only), so the rendered document
        // itself must never carry one either.
        $this->assert(!preg_match('/[?&]token=/', $html), 'The rendered recovery page must not embed a token.');
    }

    /**
     * The owner dropped the two-family split (docs/product decision): one
     * face, Vazirmatn, everywhere including headings. AbarHigh's files were
     * deleted; this asserts nothing in the shipped stylesheets can still
     * name it, which is the only way `var(--font-display)` could resolve to
     * a font that is no longer served.
     */
    private function noPageReferencesTheRemovedDisplayFace(PageRenderer $renderer): void
    {
        foreach (['/assets/web/foundation/type.css', '/assets/web/pages/runner.css'] as $sheet) {
            $contents = file_get_contents($this->root . '/apps/platform/public' . $sheet);
            $this->assert(is_string($contents), "Stylesheet is missing: {$sheet}");
            $this->assert(!str_contains((string) $contents, 'AbarHigh'), "Removed display face is still referenced in {$sheet}.");
            $this->assert(!str_contains((string) $contents, '--font-display'), "Removed --font-display token is still referenced in {$sheet}.");
        }
        $this->assert(!is_dir($this->root . '/apps/platform/public/assets/fonts/abarhigh'), 'AbarHigh font files were not removed.');
        $this->assert(is_dir($this->root . '/apps/platform/public/assets/fonts/yekanbakh'), 'Yekan Bakh must stay -- it is the protected-media worker\'s watermark font, unrelated to this change.');
    }

    /**
     * Signing out has to be reachable, from every signed-in page, on every
     * screen size. Its absence is a security problem rather than a missing
     * convenience: on a borrowed or shared device it is the only way to end
     * a session. The site shipped without one, and the header's only button
     * pointed at a route that did not exist.
     */
    private function everySignedInPageOffersAWayOut(PageRenderer $renderer): void
    {
        $viewer = new ViewerContext('u1', 'آرین', 'csrf', 'w1', 'کلاس من');

        $navKeys = array_column($viewer->navigation(), 'key');
        $this->assert(in_array('account', $navKeys, true), 'The account area must be in the navigation, which is the only chrome on a phone.');

        foreach ([(new HomePage($renderer))->render($viewer), (new ExamsPage($renderer))->render($viewer)] as $html) {
            $this->assert(str_contains($html, 'href="/account"'), 'Every signed-in page must link to the account area.');
        }

        $account = (new AccountPage($renderer))->render($viewer);
        $this->assert(str_contains($account, 'id="signout"'), 'The account page must offer a way to sign out.');
        $this->assert(str_contains($account, 'id="password-form"'), 'The account page must let the owner change their own password.');
        $this->assert(str_contains($account, 'href="/app?switch=1"'), 'The account page must let the viewer change workspace.');
    }


    private function assert(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
