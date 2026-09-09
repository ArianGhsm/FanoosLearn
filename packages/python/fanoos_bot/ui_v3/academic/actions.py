from __future__ import annotations

"""Semantic action identifiers owned by the V3 academic bot workstream.

The identifiers are provider-neutral. Telegram/Bale adapters may encode them into
provider callbacks, but the visible label and the routing identifier are separate
concerns. Canonical object IDs belong only in action payloads and must never be
rendered as user-facing labels.
"""

HOME = "core.home"

COURSES = "academic.courses"
COURSES_PAGE = "academic.courses.page"
COURSE_OPEN = "academic.course.open"
COURSE_SCHEDULE = "academic.course.schedule"
COURSE_RESOURCES = "academic.course.resources"
COURSE_ASSESSMENTS = "academic.course.assessments"
COURSE_GRADES = "academic.course.grades"
COURSE_ANNOUNCEMENTS = "academic.course.announcements"
COURSE_SESSIONS = "academic.course.sessions"

SCHEDULE = "academic.schedule"
SCHEDULE_TODAY = "academic.schedule.today"
SCHEDULE_TOMORROW = "academic.schedule.tomorrow"
SCHEDULE_UPCOMING = "academic.schedule.upcoming"
SCHEDULE_PAGE = "academic.schedule.page"
SCHEDULE_EVENT_OPEN = "academic.schedule.event.open"

GRADES = "academic.grades"
GRADES_PAGE = "academic.grades.page"
COURSE_GRADE_OPEN = "academic.grades.course.open"

ANNOUNCEMENTS = "academic.announcements"
ANNOUNCEMENTS_PAGE = "academic.announcements.page"
ANNOUNCEMENT_OPEN = "academic.announcement.open"
ANNOUNCEMENT_LINK_OPEN = "academic.announcement.link.open"

NOTIFICATIONS = "academic.notifications"

RETRY = "core.retry"

COURSE_CONTEXT_ACTIONS: dict[str, str] = {
    "schedule": COURSE_SCHEDULE,
    "resources": COURSE_RESOURCES,
    "assessments": COURSE_ASSESSMENTS,
    "grades": COURSE_GRADES,
    "announcements": COURSE_ANNOUNCEMENTS,
    "sessions": COURSE_SESSIONS,
}

__all__ = [
    "HOME",
    "COURSES",
    "COURSES_PAGE",
    "COURSE_OPEN",
    "COURSE_SCHEDULE",
    "COURSE_RESOURCES",
    "COURSE_ASSESSMENTS",
    "COURSE_GRADES",
    "COURSE_ANNOUNCEMENTS",
    "COURSE_SESSIONS",
    "SCHEDULE",
    "SCHEDULE_TODAY",
    "SCHEDULE_TOMORROW",
    "SCHEDULE_UPCOMING",
    "SCHEDULE_PAGE",
    "SCHEDULE_EVENT_OPEN",
    "GRADES",
    "GRADES_PAGE",
    "COURSE_GRADE_OPEN",
    "ANNOUNCEMENTS",
    "ANNOUNCEMENTS_PAGE",
    "ANNOUNCEMENT_OPEN",
    "ANNOUNCEMENT_LINK_OPEN",
    "NOTIFICATIONS",
    "RETRY",
    "COURSE_CONTEXT_ACTIONS",
]
