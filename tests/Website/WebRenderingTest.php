<?php

declare(strict_types=1);

namespace Fanoos\Tests\Website;

use Fanoos\Platform\Web\AssetVersioner;
use Fanoos\Platform\Web\LandingPage;
use Fanoos\Platform\Web\LoginPage;
use Fanoos\Platform\Web\HomePage;
use Fanoos\Platform\Web\NotFoundPage;
use Fanoos\Platform\Web\PageRenderer;
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

        $this->assert(count($without->navigation()) === 1, 'Only home is reachable before a workspace is chosen.');
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

    private function assert(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
