from __future__ import annotations

from .application import ApplicationConfig, BotApplication as BaseBotApplication
from .formatting import truncate_text
from .models import ActionResult, Button
from .presentation import semantic_screen
from .product_ui import course_catalog


class BotApplication(BaseBotApplication):
    """Final cross-channel application surface.

    Course identity comes only from the canonical academic projection returned
    by the backend. Bot-local state remains navigation state and is never a
    shadow course authority.
    """

    def _courses(self, subject: str, workspace_id: str):
        projection, _, _ = self._today_projection(subject, workspace_id)
        rows = [row for row in (projection.get("courses") or []) if isinstance(row, dict)]
        return course_catalog(rows)

    def courses(self, subject: str):
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
            rows = tuple(
                (Button(truncate_text(course["title"], 36), self._cb("course", course["course_id"])),)
                for course in courses[:30]
            )
            rows += self._nav_rows()
            return ActionResult(
                semantic_screen(
                    "📚 درس‌ها",
                    "course_list",
                    intro="یک درس را برای دیدن برنامه، منابع و نمرات همان درس انتخاب کنید.",
                    list_items=tuple(
                        f"{course['title']}" + (f" · {course['code']}" if course.get("code") else "")
                        for course in courses[:30]
                    ),
                    footer=(
                        "فهرست درس‌ها مستقیماً از projection canonical فضای آموزشی دریافت می‌شود."
                        if len(courses) <= 30
                        else "۳۰ درس اول projection canonical نمایش داده شده است."
                    ),
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)


__all__ = ["ApplicationConfig", "BotApplication"]
