<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * Everything a rendered page is allowed to know about who is looking at it.
 *
 * Deliberately narrow: a display name, the selected workspace, the CSRF
 * token, and the permission keys the viewer holds in that workspace -- used
 * only to decide what to *show* (a composer, a review button). No role
 * names, no membership list. The backend decides what is *allowed*, on every
 * request, and hiding a link has never been a security control.
 */
final class ViewerContext
{
    public function __construct(
        public readonly string $userId,
        public readonly string $displayName,
        public readonly string $csrfToken,
        public readonly ?string $workspaceId,
        public readonly ?string $workspaceName,
        // Only decides whether the products link is shown; the API checks
        // commerce.manage_catalog on every request regardless.
        public readonly bool $canManageCatalog = false,
        // Only decides whether the reports queue is linked; the API checks exam.review.
        public readonly bool $canReviewContent = false,
        /** @var list<string> */
        public readonly array $permissionKeys = [],
    ) {
    }

    /** Whether to show something that needs this permission; never a check that guards it. */
    public function can(string $permissionKey): bool
    {
        return in_array($permissionKey, $this->permissionKeys, true);
    }

    /**
     * The primary navigation. Areas that need a selected workspace are absent
     * until one is chosen, because a link that can only fail is worse than no
     * link -- the visitor cannot tell a missing feature from a broken one.
     *
     * @return list<array{key:string,href:string,label:string,icon:string}>
     */
    public function navigation(): array
    {
        $items = [
            ['key' => 'home', 'href' => '/app', 'label' => 'خانه', 'icon' => 'home'],
        ];
        if ($this->workspaceId !== null) {
            $items[] = ['key' => 'bank', 'href' => '/app/bank', 'label' => 'بانک سؤال', 'icon' => 'bank'];
            $items[] = ['key' => 'exams', 'href' => '/app/exams', 'label' => 'آزمون‌ها', 'icon' => 'exams'];
            $items[] = ['key' => 'progress', 'href' => '/app/progress', 'label' => 'پیشرفت', 'icon' => 'progress'];
            $items[] = ['key' => 'store', 'href' => '/app/store', 'label' => 'فروشگاه', 'icon' => 'store'];
            if ($this->canReviewContent) {
                $items[] = ['key' => 'reports', 'href' => '/app/admin/reports', 'label' => 'گزارش‌های اشکال', 'icon' => 'flag'];
            }
            if ($this->canManageCatalog) {
                $items[] = ['key' => 'products', 'href' => '/app/admin/products', 'label' => 'محصولات و قیمت', 'icon' => 'tag'];
            }
        }
        // On a phone the header collapses and this bar is the only chrome, so
        // the account -- and with it the only way to sign out -- has to be
        // reachable from here rather than only from the header.
        $items[] = ['key' => 'account', 'href' => '/account', 'label' => 'حساب', 'icon' => 'account'];

        return $items;
    }
}
