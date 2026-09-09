from __future__ import annotations

from .application import ApplicationConfig, BotApplication as BaseBotApplication
from .callbacks import CallbackCodec
from .formatting import format_human_number, truncate_text
from .models import ActionResult, Button
from .presentation import semantic_screen
from .product_ui import course_catalog

COURSE_PAGE_SIZE = 12


class BotApplication(BaseBotApplication):
    """Final cross-channel application surface over canonical backend facts."""

    def _courses(self, subject: str, workspace_id: str):
        projection, _, _ = self._today_projection(subject, workspace_id)
        rows = [row for row in (projection.get("courses") or []) if isinstance(row, dict)]
        return course_catalog(rows)

    def courses(self, subject: str, page: int = 0):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            courses = self._courses(subject, workspace_id)
            if not courses:
                rows = self._web_row() + self._nav_rows()
                return ActionResult(
                    semantic_screen(
                        "📚 درس‌ها",
                        "course_list_empty",
                        intro="درسی برای این فضای آموزشی پیدا نشد.",
                        footer="فهرست درس‌ها مستقیماً از دادهٔ canonical فضای آموزشی دریافت می‌شود.",
                        rows=rows,
                    )
                )

            if isinstance(page, bool) or not isinstance(page, int) or page < 0:
                return self._expired_route()
            start = page * COURSE_PAGE_SIZE
            if start >= len(courses):
                return self._expired_route()
            visible = courses[start : start + COURSE_PAGE_SIZE]
            rows = tuple(
                (Button(truncate_text(course["title"], 36), self._cb("course", course["course_id"])),)
                for course in visible
            )
            pager: list[Button] = []
            if page > 0:
                prev_ref = self._route_callback(
                    subject, "courses_page", "coursep", {"page": page - 1}
                )
                pager.append(Button("‹ قبلی", prev_ref))
            if start + COURSE_PAGE_SIZE < len(courses):
                next_ref = self._route_callback(
                    subject, "courses_page", "coursep", {"page": page + 1}
                )
                pager.append(Button("بعدی ›", next_ref))
            if pager:
                rows += (tuple(pager),)
            rows += self._nav_rows()
            total_pages = (len(courses) + COURSE_PAGE_SIZE - 1) // COURSE_PAGE_SIZE
            return ActionResult(
                semantic_screen(
                    "📚 درس‌ها",
                    "course_list",
                    intro="یک درس را برای دیدن برنامه، منابع و نمرات همان درس انتخاب کنید.",
                    list_items=tuple(
                        f"{course['title']}" + (f" · {course['code']}" if course.get("code") else "")
                        for course in visible
                    ),
                    pagination=(
                        f"صفحه {format_human_number(page + 1)} از {format_human_number(total_pages)}"
                    ),
                    footer="فهرست درس‌ها مستقیماً از projection canonical فضای آموزشی دریافت می‌شود.",
                    rows=rows,
                    edit=page > 0,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def callback(self, subject: str, private: bool, value: str):
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
        return super().callback(subject, private, value)


__all__ = ["ApplicationConfig", "BotApplication"]
