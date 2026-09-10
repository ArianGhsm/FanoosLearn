from __future__ import annotations

from dataclasses import replace
from typing import Any

from .application import ApplicationConfig, BotApplication as BaseBotApplication
from .callbacks import CallbackCodec
from .formatting import format_human_number, is_uuid
from .localization import platform_label
from .models import ActionResult, Screen as RuntimeScreen
from .ui_v3.academic import course_detail_screen, course_list_screen, course_unavailable_screen
from .ui_v3.core import (
    Action,
    ActionRow,
    CallbackIntent,
    ListItem,
    Pagination,
    Screen as CoreScreen,
    Section,
    Severity,
)
from .ui_v3.core.account import linked_account_screen, unlink_confirmation_screen, unlink_success_screen
from .ui_v3.core.actions import account_action, help_action, home_action, workspace_action
from .ui_v3.core.onboarding import linked_no_workspace_screen
from .ui_v3.core.workspace import WorkspaceOption, workspace_list_screen
from .ui_v3.learning import assessment_hub_screen, commerce_hub_screen, order_access_detail_screen, resource_detail_screen
from .ui_v3.wiring import core_to_runtime, decode_v3_intent, dispatch_v3_intent, legacy_to_core

COURSE_PAGE_SIZE = 8
WORKSPACE_PAGE_SIZE = 5
_OWNER_ALLOWED_KINDS = {
    "management",
    "deployment_confirmation",
    "deployment_status",
    "deployment_cancelled",
    "deployment_empty",
}


class BotApplication(BaseBotApplication):
    """Canonical business application with one V3 presentation/intent integration layer."""

    def _selected(self, subject: str):
        """Never turn a single membership into implicit selection authority."""
        projection = self._workspace_projection(subject)
        return projection, projection.get("selected_workspace_id")

    def _v3_result(self, screen: CoreScreen, **metadata: Any) -> ActionResult:
        return ActionResult(screen, metadata=dict(metadata))

    def _workspace_options(self, projection: dict, page: int = 0) -> tuple[WorkspaceOption, ...]:
        selected = str(projection.get("selected_workspace_id") or "")
        workspaces = [item for item in projection.get("workspaces") or [] if isinstance(item, dict)]
        start = max(0, page) * WORKSPACE_PAGE_SIZE
        options = []
        for workspace in workspaces[start : start + WORKSPACE_PAGE_SIZE]:
            workspace_id = str(workspace.get("id") or "")
            if not is_uuid(workspace_id):
                continue
            options.append(
                WorkspaceOption(
                    workspace_id=workspace_id,
                    label=self._workspace_label(workspace),
                    selected=workspace_id == selected,
                )
            )
        return tuple(options)

    def _no_workspace_screen(self) -> CoreScreen:
        if self.config.web_base_url:
            return linked_no_workspace_screen(self.config.web_base_url)
        return CoreScreen(
            identifier="onboarding.linked_no_workspace",
            title="🏠 فانوس",
            intro="حساب شما متصل است ✅\nهنوز فضای آموزشی فعالی برای این حساب ندارید.",
            severity=Severity.INFO,
            sections=(Section(body="عضویت فضای آموزشی فقط از دادهٔ رسمی فانوس خوانده می‌شود."),),
            action_rows=(
                ActionRow((workspace_action(),)),
                ActionRow((account_action(), help_action())),
                ActionRow((home_action(),)),
            ),
        )

    def home(self, subject: str, notice: str = ""):
        try:
            projection, selected = self._selected(subject)
            workspaces = [item for item in projection.get("workspaces") or [] if isinstance(item, dict)]
            if not workspaces:
                return self._v3_result(self._no_workspace_screen())
            if not selected:
                # Membership exists but selection does not: selection remains an
                # explicit user action and no first-workspace authority is invented.
                options = self._workspace_options(projection)
                if options:
                    return self._v3_result(workspace_list_screen(options))
            return super().home(subject, notice)
        except Exception as exc:
            return self._error(exc)

    def workspaces(self, subject: str):
        try:
            projection = self._workspace_projection(subject)
            workspaces = [item for item in projection.get("workspaces") or [] if isinstance(item, dict)]
            if not workspaces:
                return self._v3_result(self._no_workspace_screen())
            options = self._workspace_options(projection)
            if not options:
                return super().workspaces(subject)
            return self._v3_result(workspace_list_screen(options))
        except Exception as exc:
            return self._error(exc)

    def select_workspace(self, subject: str, workspace_id: str):
        if not is_uuid(workspace_id):
            return self._expired_route()
        try:
            # Canonical backend rechecks linked user + current membership.
            self.backend.select_workspace(self.platform, subject, workspace_id)
            projection = self._workspace_projection(subject)
            label = self._selected_workspace_label(projection, workspace_id)
            result = self.home(subject, f"فضای آموزشی «{label}» فعال شد.")
            return result
        except Exception as exc:
            return self._error(exc)

    def account(self, subject: str):
        if not self.config.web_base_url:
            return super().account(subject)
        try:
            projection, selected = self._selected(subject)
            return self._v3_result(
                linked_account_screen(
                    platform_label=platform_label(self.platform),
                    website_url=self.config.web_base_url,
                    active_workspace_label=self._selected_workspace_label(projection, selected) if selected else None,
                    workspace_count_label=format_human_number(len(projection.get("workspaces") or [])),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def unlink_confirm(self, subject: str):
        return self._v3_result(unlink_confirmation_screen(platform_label=platform_label(self.platform)))

    def unlink(self, subject: str):
        if not self.config.web_base_url:
            return super().unlink(subject)
        try:
            self.backend.unlink(self.platform, subject)
            return self._v3_result(unlink_success_screen(self.config.web_base_url))
        except Exception as exc:
            return self._error(exc)

    def _course_page_ref(self, subject: str, page: int) -> str:
        return self.state.create_route(
            self.platform,
            subject,
            "v3_intent",
            {"name": "academic.courses.page", "params": {"page": str(page)}},
        )

    def courses(self, subject: str, page: int = 0):
        try:
            projection, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            if isinstance(page, bool) or not isinstance(page, int) or page < 0:
                return self._expired_route()
            courses = self._courses(subject, workspace_id)
            if not courses:
                return self._v3_result(
                    course_list_screen((), workspace_label=self._selected_workspace_label(projection, workspace_id))
                )
            start = page * COURSE_PAGE_SIZE
            if start >= len(courses):
                return self._expired_route()
            previous_ref = self._course_page_ref(subject, page - 1) if page > 0 else None
            next_ref = self._course_page_ref(subject, page + 1) if start + COURSE_PAGE_SIZE < len(courses) else None
            return self._v3_result(
                course_list_screen(
                    courses[start : start + COURSE_PAGE_SIZE],
                    page=page + 1,
                    previous_ref=previous_ref,
                    next_ref=next_ref,
                    workspace_label=self._selected_workspace_label(projection, workspace_id),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def course_detail(self, subject: str, course_id: str):
        if not is_uuid(course_id):
            return self._expired_route()
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = next(
                (item for item in self._courses(subject, workspace_id) if str(item.get("course_id") or "") == course_id),
                None,
            )
            if not course:
                return self._v3_result(course_unavailable_screen())
            return self._v3_result(
                course_detail_screen(
                    course,
                    supported_actions=("schedule", "resources", "assessments", "grades", "announcements"),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def resource_detail(self, subject: str, resource_id: str):
        if not is_uuid(resource_id):
            return self._expired_route()
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            item = self._find_resource(subject, workspace_id, resource_id)
            if not item:
                return super().resource_detail(subject, resource_id)
            return self._v3_result(
                resource_detail_screen(item, web_url=self.config.web_base_url or None)
            )
        except Exception as exc:
            return self._error(exc)

    def assessments(self, subject: str):
        try:
            _, _, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            return self._v3_result(
                assessment_hub_screen(None, web_url=self.config.web_base_url or None)
            )
        except Exception as exc:
            return self._error(exc)

    def payments(self, subject: str):
        try:
            _, selected = self._selected(subject)
            if not selected:
                return super().payments(subject)
            return self._v3_result(
                commerce_hub_screen(
                    order_summary=None,
                    access_summary=None,
                    catalog=None,
                    web_url=self.config.web_base_url or None,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def order_status(self, subject: str, order_id: str):
        try:
            _, workspace_id = self._selected(subject)
            if not workspace_id:
                return super().order_status(subject, order_id)
            order = self.backend.order_status(self.platform, subject, workspace_id, order_id)
            entitlement = order.get("entitlement") if isinstance(order.get("entitlement"), dict) else {}
            return self._v3_result(
                order_access_detail_screen(
                    order,
                    payment_status=order.get("payment_status"),
                    entitlement_status=entitlement.get("status"),
                    payment_url=order.get("payment_url"),
                    web_url=self.config.web_base_url or None,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def callback(self, subject: str, private: bool, value: str):
        decoded = decode_v3_intent(self, subject, value)
        if decoded is not None:
            name, params = decoded
            if name == "academic.courses.page":
                try:
                    page = int(params.get("page", ""))
                except (TypeError, ValueError):
                    return self._expired_route()
                return self.courses(subject, page)
            return dispatch_v3_intent(self, subject, private, name, params)

        try:
            action, ref = CallbackCodec.decode(value)
        except Exception:
            return super().callback(subject, private, value)
        if action == "coursep" and ref:
            payload = self._route_payload(subject, ref, "courses_page")
            page = (payload or {}).get("page")
            if isinstance(page, int) and not isinstance(page, bool) and page >= 0:
                return self.courses(subject, page)
            return self._expired_route()
        if action == "help":
            return self.help()
        return super().callback(subject, private, value)

    def prepare_result(self, subject: str, private: bool, result: ActionResult) -> ActionResult:
        """Attach the canonical V3 Screen without replaying the business action."""
        source = result.screen
        if isinstance(source, CoreScreen):
            runtime_screen = core_to_runtime(self, subject, source)
            screen_id = runtime_screen.v3.identifier
        elif isinstance(source, RuntimeScreen):
            # Protected legacy screens can carry the actual protected payload in
            # screen.text. Leave that exact payload intact; bot-04 still renders
            # it through its V3 provider adapter and never downgrades protection.
            if source.protect_content:
                runtime_screen = source
                screen_id = source.presentation.semantic_kind if source.presentation else "protected"
            else:
                runtime_screen = core_to_runtime(self, subject, legacy_to_core(source))
                screen_id = runtime_screen.v3.identifier
        else:
            return result

        metadata = dict(result.metadata or {})
        metadata["v3_screen_id"] = screen_id
        legacy_kind = source.presentation.semantic_kind if isinstance(source, RuntimeScreen) and source.presentation else ""
        if (
            self.platform == "telegram"
            and private
            and legacy_kind in _OWNER_ALLOWED_KINDS
        ):
            # These source screens are reachable only after the canonical owner
            # backend checks in BaseBotApplication. This metadata does not grant
            # permission; it passes that already-verified fact to presentation.
            metadata["canonical_permissions"] = ("deployment.manage",)
        return replace(result, screen=runtime_screen, metadata=metadata)


__all__ = ["ApplicationConfig", "BotApplication"]
