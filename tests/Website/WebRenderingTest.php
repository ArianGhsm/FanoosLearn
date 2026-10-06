<?php

declare(strict_types=1);

namespace Fanoos\Tests\Website;

use Fanoos\Platform\Web\AssetVersioner;
use Fanoos\Platform\Web\LandingPage;
use Fanoos\Platform\Web\LoginPage;
use Fanoos\Platform\Web\RegisterPage;
use Fanoos\Platform\Web\PaymentReturnPage;
use Fanoos\Platform\Web\HomePage;
use Fanoos\Platform\Web\AccountPage;
use Fanoos\Platform\Web\ExamAttemptPage;
use Fanoos\Platform\Web\CustomPracticePage;
use Fanoos\Platform\Web\ExamsPage;
use Fanoos\Platform\Web\NotFoundPage;
use Fanoos\Platform\Web\PageRenderer;
use Fanoos\Platform\Web\PointsPage;
use Fanoos\Platform\Web\ProgressPage;
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
        $this->signUpAsksForNoPhoneAndLeadsBothWays($renderer);
        $this->paymentReturnSaysWhatTheServerDecided($renderer);
        $this->homeGreetsWithTheProfileWhenThereIsOne($renderer);
        $this->everyPageIsRightToLeftPersian($renderer);
        $this->examPagesCarryTheWorkspaceButNoQuestionContent($renderer);
        $this->everySignedInPageOffersAWayOut($renderer);
        $this->recoveryPageChecksTheLinkClientSideAndCarriesNoToken($renderer);
        $this->noPageReferencesTheRemovedDisplayFace($renderer);
        $this->theSiteIsInstallableWithoutCachingData($renderer);

        return $this->assertions;
    }

    /** The manifest and worker make the site installable; the worker never caches the API or pages. */
    private function theSiteIsInstallableWithoutCachingData(PageRenderer $renderer): void
    {
        $manifest = json_decode(\Fanoos\Platform\Web\Pwa::manifest()['body'], true, 16, JSON_THROW_ON_ERROR);
        $this->assert($manifest['start_url'] === '/app' && $manifest['display'] === 'standalone' && count($manifest['icons']) >= 2, 'The manifest is not installable.');
        foreach ($manifest['icons'] as $icon) {
            $this->assert(is_file($this->root . '/apps/platform/public' . $icon['src']), "Manifest icon {$icon['src']} is missing.");
        }
        $worker = \Fanoos\Platform\Web\Pwa::worker();
        $this->assert(in_array('Service-Worker-Allowed: /', $worker['headers'], true), 'The worker cannot control the whole site.');
        $this->assert(!str_contains($worker['body'], '/api/') && str_contains($worker['body'], "startsWith('/assets/')"), 'The worker must cache versioned assets only.');
        $html = (new LandingPage($renderer))->render();
        $this->assert(str_contains($html, 'rel="manifest" href="/pwa/manifest"') && str_contains($html, '/assets/web/foundation/pwa.js'), 'Pages do not link the manifest and register the worker.');
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
        foreach ([(new LandingPage($renderer))->render(), (new LoginPage($renderer))->render(), (new RegisterPage($renderer))->render()] as $html) {
            $this->assert(!str_contains($html, 'fanoos-csrf'), 'A signed-out page must not carry a CSRF token.');
            $this->assert(!str_contains($html, 'f-header'), 'A signed-out page must not render the signed-in chrome.');
        }
    }

    /**
     * The owner's decision: sign-up must not depend on an SMS, so the form
     * has no phone field at all. And the two doors lead to each other.
     */
    private function signUpAsksForNoPhoneAndLeadsBothWays(PageRenderer $renderer): void
    {
        $register = (new RegisterPage($renderer))->render();
        $this->assert(str_contains($register, 'id="register-form"'), 'The sign-up page must carry its form.');
        foreach (['name="username"', 'name="password"', 'name="first_name"', 'name="last_name"', 'discipline-options'] as $needed) {
            $this->assert(str_contains($register, $needed), "The sign-up form is missing {$needed}.");
        }
        $this->assert(!preg_match('/type="tel"|name="phone/', $register), 'Sign-up must not ask for a phone number.');
        $this->assert(str_contains($register, 'href="/login"'), 'Sign-up must link to sign-in.');
        $this->assert(str_contains((new LoginPage($renderer))->render(), 'href="/register"'), 'Sign-in must link to sign-up.');
        $this->assert(str_contains((new LandingPage($renderer))->render(), 'href="/register"'), 'The landing page must offer sign-up.');
    }

    /**
     * The return page states the server's verdict, and a payment that could
     * not be checked yet is "being checked" -- never "failed", which would
     * tell someone whose money was taken that it was not.
     */
    private function paymentReturnSaysWhatTheServerDecided(PageRenderer $renderer): void
    {
        $page = new PaymentReturnPage($renderer);
        $this->assert(str_contains($page->render(null, PaymentReturnPage::PAID), 'پرداخت انجام شد'), 'A paid order must say so.');
        $pending = $page->render(null, PaymentReturnPage::PENDING);
        $this->assert(str_contains($pending, 'در حال بررسی') && !str_contains($pending, 'پرداخت انجام نشد'), 'An unchecked payment must not be called failed.');
        $this->assert(!str_contains($pending, 'fanoos-csrf'), 'A signed-out return page must carry no CSRF token.');
        $signedIn = $page->render(new ViewerContext('u1', 'آرین', 'c', 'w1', 'بانک'), PaymentReturnPage::FAILED);
        $this->assert(str_contains($signedIn, 'href="/app/store"'), 'A signed-in payer must be offered the store again.');
    }

    private function homeGreetsWithTheProfileWhenThereIsOne(PageRenderer $renderer): void
    {
        $viewer = new ViewerContext('u1', 'نام نمایشی', 'c', 'w1', 'بانک');
        $html = (new HomePage($renderer))->render($viewer, false, [
            'first_name' => '<b>سارا</b>', 'discipline_name' => 'رشته‌ی آزمایشی', 'institution_name' => null,
        ], true);
        $this->assert(str_contains($html, '&lt;b&gt;سارا'), 'The greeting must use the profile first name, escaped.');
        $this->assert(str_contains($html, 'رشته‌ی آزمایشی'), 'The greeting must name the field of study.');
        $this->assert(str_contains($html, 'حسابت ساخته شد'), 'A just-created account must be welcomed.');
        $this->assert(str_contains($html, 'id="home-courses"'), 'Home must have the course grid when a workspace is selected.');
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
        $this->assert(str_contains($html, 'href="/app/exams/custom"'), 'Home must offer the custom practice builder.');
        $custom = (new CustomPracticePage($renderer))->render($viewer);
        $this->assert(
            str_contains($custom, 'id="custom-form"') && str_contains($custom, '/assets/web/pages/custom-practice.js'),
            'The custom practice page must render its builder and load its script.',
        );
        $this->assert(str_contains($html, 'href="/app/progress"'), 'The navigation must offer the progress dashboard once a workspace is chosen.');
        $progress = (new ProgressPage($renderer))->render($viewer);
        $this->assert(
            str_contains($progress, 'id="progress-board"') && str_contains($progress, '/assets/web/pages/progress.js')
                && str_contains($progress, 'href="/app/progress" aria-current="page"'),
            'The progress page must render its board, load its script and mark its navigation item.',
        );
        $this->assert(str_contains($progress, 'href="/app/points"'), 'The progress page must link to the points page.');
        $points = (new PointsPage($renderer))->render($viewer);
        $this->assert(
            str_contains($points, 'id="points-board"') && str_contains($points, '/assets/web/pages/points.js') && str_contains($points, 'id="goal-ring"'),
            'The points page must render its board and goal ring and load its script.',
        );
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
        $this->assert(!in_array('products', array_column($with->navigation(), 'key'), true), 'Only a catalog manager is shown the products page.');
        $manager = new ViewerContext('u1', 'آرین', 'c', 'w1', 'کلاس من', true);
        $this->assert(in_array('products', array_column($manager->navigation(), 'key'), true), 'A catalog manager must be shown the products page.');
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
        $withBots = (new AccountPage($renderer))->render(new ViewerContext('u1', 'آرین', 'c', 'w1', 'کلاس'), ['bale' => 'fanooslearnbot', 'telegram' => '<bad name>']);
        $this->assert(str_contains($withBots, 'data-bale-bot="fanooslearnbot"'), 'The account page must know the Bale bot to open.');
        $this->assert(!str_contains($withBots, 'data-telegram-bot'), 'A malformed bot username must not reach a link.');
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
