"""Provider-neutral FANOOS Rebuild V3 core bot presentation model.

This package owns presentation contracts and core screens only. It performs no
backend I/O and stores no authorization or business truth.
"""

from .account import linked_account_screen, unlink_confirmation_screen, unlink_success_screen
from .actions import *
from .contracts import (
    Action,
    ActionRow,
    Breadcrumb,
    CallbackIntent,
    Context,
    EditPolicy,
    Fact,
    ListItem,
    Pagination,
    ProtectContent,
    Screen,
    Section,
    Severity,
    rows,
)
from .home import HomeSlot, SlotState, active_home_screen, more_menu_screen
from .navigation import (
    HOME_ROUTE,
    NavigationState,
    Route,
    back_intent,
    cancel_intent,
    home_intent,
    open_route_intent,
    page_intent,
)
from .onboarding import (
    getting_started_screen,
    link_challenge_expired_screen,
    linked_multiple_workspaces_screen,
    linked_no_workspace_screen,
    linked_one_workspace_screen,
    onboarding_service_unavailable_screen,
    unlinked_account_screen,
)
from .states import empty_screen, error_screen, success_screen, unavailable_screen
from .workspace import (
    WorkspaceOption,
    no_workspace_screen,
    workspace_list_screen,
    workspace_switch_success_screen,
)

__all__ = [
    "Action",
    "ActionRow",
    "Breadcrumb",
    "CallbackIntent",
    "Context",
    "EditPolicy",
    "Fact",
    "HomeSlot",
    "HOME_ROUTE",
    "ListItem",
    "NavigationState",
    "Pagination",
    "ProtectContent",
    "Route",
    "Screen",
    "Section",
    "Severity",
    "SlotState",
    "WorkspaceOption",
    "active_home_screen",
    "back_intent",
    "cancel_intent",
    "empty_screen",
    "error_screen",
    "getting_started_screen",
    "home_intent",
    "link_challenge_expired_screen",
    "linked_account_screen",
    "linked_multiple_workspaces_screen",
    "linked_no_workspace_screen",
    "linked_one_workspace_screen",
    "more_menu_screen",
    "no_workspace_screen",
    "onboarding_service_unavailable_screen",
    "open_route_intent",
    "page_intent",
    "rows",
    "success_screen",
    "unavailable_screen",
    "unlink_confirmation_screen",
    "unlink_success_screen",
    "unlinked_account_screen",
    "workspace_list_screen",
    "workspace_switch_success_screen",
]
