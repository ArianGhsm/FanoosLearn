from __future__ import annotations

from dataclasses import dataclass

from .contracts import CallbackIntent


@dataclass(frozen=True)
class Route:
    """Disposable presentation route, never an authorization context."""

    name: str
    label: str
    params: tuple[tuple[str, str], ...] = ()

    def __post_init__(self) -> None:
        CallbackIntent("nav.open", (("route", self.name),) + self.params)
        if not self.label.strip():
            raise ValueError("route label is required")


HOME_ROUTE = Route("home", "خانه")


@dataclass(frozen=True)
class NavigationState:
    """Bounded route stack for presentation continuity only.

    Never place role, permission, membership, entitlement, payment result,
    exam result, grade truth, or deployment authorization in this object.
    """

    stack: tuple[Route, ...] = (HOME_ROUTE,)
    page: int = 1
    cancel_target: Route | None = None
    max_depth: int = 8

    def __post_init__(self) -> None:
        if not self.stack or self.stack[0].name != "home":
            raise ValueError("navigation stack must start at home")
        if self.page < 1 or not 2 <= self.max_depth <= 12:
            raise ValueError("invalid navigation bounds")
        if len(self.stack) > self.max_depth:
            raise ValueError("navigation stack exceeds max depth")

    @property
    def current(self) -> Route:
        return self.stack[-1]

    @property
    def previous(self) -> Route:
        return self.stack[-2] if len(self.stack) > 1 else HOME_ROUTE

    def push(self, route: Route, *, cancel_target: Route | None = None) -> "NavigationState":
        next_stack = self.stack + (route,)
        if len(next_stack) > self.max_depth:
            next_stack = (HOME_ROUTE,) + next_stack[-(self.max_depth - 1):]
        return NavigationState(next_stack, 1, cancel_target, self.max_depth)

    def back(self) -> "NavigationState":
        if len(self.stack) <= 1:
            return self.home()
        return NavigationState(self.stack[:-1], 1, None, self.max_depth)

    def home(self) -> "NavigationState":
        return NavigationState((HOME_ROUTE,), 1, None, self.max_depth)

    def with_page(self, page: int) -> "NavigationState":
        return NavigationState(self.stack, page, self.cancel_target, self.max_depth)

    def cancel(self) -> "NavigationState":
        if self.cancel_target is None:
            return self.back()
        if self.cancel_target.name == "home":
            return self.home()
        base = self.stack[:-1] if len(self.stack) > 1 else self.stack
        return NavigationState(base + (self.cancel_target,), 1, None, self.max_depth)


def home_intent() -> CallbackIntent:
    return CallbackIntent("home")


def back_intent() -> CallbackIntent:
    return CallbackIntent("back")


def cancel_intent() -> CallbackIntent:
    return CallbackIntent("cancel")


def open_route_intent(route: Route) -> CallbackIntent:
    return CallbackIntent("nav.open", (("route", route.name),) + route.params)


def page_intent(page: int, *, route_ref: str | None = None) -> CallbackIntent:
    if page < 1:
        raise ValueError("page must be positive")
    return CallbackIntent("nav.page", (("p", str(page)),), route_ref=route_ref)
