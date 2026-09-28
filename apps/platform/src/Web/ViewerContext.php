<?php

declare(strict_types=1);

namespace Fanoos\Platform\Web;

/**
 * Everything a rendered page is allowed to know about who is looking at it.
 *
 * Deliberately narrow: a display name, the selected workspace, and the CSRF
 * token. No permissions, no role names, no membership list. The navigation
 * here decides what to *show*; the backend decides what is *allowed*, on
 * every request, and hiding a link has never been a security control.
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
    ) {
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
            ['key' => 'home', 'href' => '/app', 'label' => 'خانه', 'icon' => '🏠'],
        ];
        if ($this->workspaceId !== null) {
            $items[] = ['key' => 'exams', 'href' => '/app/exams', 'label' => 'آزمون‌ها', 'icon' => '📝'];
            $items[] = ['key' => 'progress', 'href' => '/app/progress', 'label' => 'پیشرفت', 'icon' => '📈'];
            $items[] = ['key' => 'store', 'href' => '/app/store', 'label' => 'فروشگاه', 'icon' => '🛒'];
            if ($this->canManageCatalog) {
                $items[] = ['key' => 'products', 'href' => '/app/admin/products', 'label' => 'محصولات و قیمت', 'icon' => '🏷'];
            }
        }
        // On a phone the header collapses and this bar is the only chrome, so
        // the account -- and with it the only way to sign out -- has to be
        // reachable from here rather than only from the header.
        $items[] = ['key' => 'account', 'href' => '/account', 'label' => 'حساب', 'icon' => '👤'];

        return $items;
    }
}
