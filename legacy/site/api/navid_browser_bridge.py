#!/usr/bin/env python3
from __future__ import annotations

import json
import os
import re
import sys
from typing import Any, Dict, List, Optional, Tuple
from urllib.parse import urljoin, urlparse

try:
    from playwright.sync_api import Browser, BrowserContext, Page, sync_playwright
except Exception as exc:  # pragma: no cover
    print(json.dumps({
        "success": False,
        "error": "playwright_unavailable",
        "message": f"Playwright runtime is unavailable: {exc}",
    }, ensure_ascii=False))
    raise SystemExit(0)


def _stdout_json(payload: Dict[str, Any]) -> None:
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass
    sys.stdout.write(json.dumps(payload, ensure_ascii=False))


def _stdin_payload() -> Dict[str, Any]:
    raw = sys.stdin.read()
    if not raw.strip():
        return {}
    try:
        decoded = json.loads(raw)
    except Exception:
        return {}
    return decoded if isinstance(decoded, dict) else {}


def _normalize_login_url(value: Any) -> str:
    text = str(value or "").strip()
    if not text.startswith(("http://", "https://")):
        return "https://navid.tums.ac.ir/account/loginsipadservice"
    return text


def _origin(login_url: str) -> str:
    parts = urlparse(login_url)
    if not parts.scheme or not parts.netloc:
        return "https://navid.tums.ac.ir"
    return f"{parts.scheme}://{parts.netloc}"


def _absolute_url(base: str, maybe_relative: str) -> str:
    return urljoin(base.rstrip("/") + "/", maybe_relative or "")


def _launch_browser() -> Tuple[Any, Browser]:
    os.environ["HTTP_PROXY"] = ""
    os.environ["HTTPS_PROXY"] = ""
    os.environ["NO_PROXY"] = "*"
    pw = sync_playwright().start()
    args = [
        "--proxy-server=direct://",
        "--proxy-bypass-list=*",
        "--no-proxy-server",
        "--ignore-certificate-errors",
        "--disable-dev-shm-usage",
        "--disable-gpu",
    ]
    try:
        browser = pw.chromium.launch(channel="chrome", headless=True, args=args)
    except Exception:
        browser = pw.chromium.launch(headless=True, args=args)
    return pw, browser


def _new_context(browser: Browser, storage_state: Optional[Dict[str, Any]] = None) -> BrowserContext:
    kwargs: Dict[str, Any] = {
        "ignore_https_errors": True,
        "locale": "fa-IR",
        "viewport": {"width": 1365, "height": 1200},
    }
    if storage_state:
        kwargs["storage_state"] = storage_state
    return browser.new_context(**kwargs)


def _cookie_names(context: BrowserContext) -> List[str]:
    try:
        return [str(item.get("name", "")) for item in context.cookies()]
    except Exception:
        return []


def _goto_login(page: Page, login_url: str) -> None:
    page.goto(login_url, wait_until="networkidle", timeout=90000)


def _prime_same_origin(page: Page, login_url: str) -> None:
    page.goto(_absolute_url(_origin(login_url), "/favicon.ico"), wait_until="domcontentloaded", timeout=90000)


def _captcha_src_from_dom(page: Page) -> str:
    try:
        locator = page.locator("img[alt='captcha-img']").first
        if locator.count() < 1:
            return ""
        src = locator.get_attribute("src") or ""
        return src.strip()
    except Exception:
        return ""


def _token_from_dom(page: Page) -> str:
    try:
        locator = page.locator("input[name='__RequestVerificationToken']").first
        if locator.count() < 1:
            return ""
        return (locator.input_value() or "").strip()
    except Exception:
        return ""


def _base64_from_data_uri(value: str) -> str:
    if not value.startswith("data:"):
        return ""
    comma = value.find(",")
    return value[comma + 1:].strip() if comma >= 0 else ""


def _normalize_captcha(raw: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", (raw or "").upper())


def _solve_captcha(image_b64: str) -> str:
    if not image_b64:
        return ""
    try:
        import base64
        import ddddocr  # type: ignore
    except Exception:
        return ""
    try:
        data = base64.b64decode(image_b64)
    except Exception:
        return ""
    try:
        ocr = ddddocr.DdddOcr(show_ad=False)
        return _normalize_captcha(ocr.classification(data))
    except Exception:
        return ""


def _fetch_json(
    page: Page,
    url: str,
    method: str = "GET",
    json_body: Optional[Dict[str, Any]] = None,
    headers: Optional[Dict[str, str]] = None,
) -> Dict[str, Any]:
    payload = page.evaluate(
        """
        async ({ url, method, jsonBody, headers }) => {
          const finalHeaders = Object.assign({ Accept: "application/json, text/plain, */*" }, headers || {});
          const options = {
            method,
            credentials: "include",
            headers: finalHeaders,
          };
          if (jsonBody !== null) {
            options.headers["Content-Type"] = "application/json;charset=UTF-8";
            options.body = JSON.stringify(jsonBody);
          }
          const resp = await fetch(url, options);
          const text = await resp.text();
          return {
            status: resp.status,
            url: resp.url,
            redirected: resp.redirected,
            headers: Object.fromEntries(resp.headers.entries()),
            text,
          };
        }
        """,
        {
            "url": url,
            "method": method.upper(),
            "jsonBody": json_body,
            "headers": headers or {},
        },
    )
    return payload if isinstance(payload, dict) else {}


def _submit_login(page: Page, login_url: str, token: str, username: str, password: str, captcha_code: str) -> Dict[str, Any]:
    payload = page.evaluate(
        """
        async ({ token, username, password, captchaCode }) => {
          const fd = new FormData();
          fd.append("ReturnUrl", "/");
          fd.append("Username", username);
          fd.append("Password", password);
          fd.append("CaptchaCode", captchaCode);
          fd.append("__RequestVerificationToken", token);
          const resp = await fetch("/account/submitloginsipadservice", {
            method: "POST",
            credentials: "include",
            body: fd,
            headers: { Accept: "application/json, text/plain, */*" },
          });
          return {
            status: resp.status,
            url: resp.url,
            headers: Object.fromEntries(resp.headers.entries()),
            text: await resp.text(),
          };
        }
        """,
        {
            "token": token,
            "username": username,
            "password": password,
            "captchaCode": captcha_code,
        },
    )
    result = payload if isinstance(payload, dict) else {}
    decoded = None
    try:
        decoded = json.loads(result.get("text", ""))
    except Exception:
        decoded = None
    if not isinstance(decoded, dict):
        return {
            "success": False,
            "error": "login_response_invalid",
            "message": "پاسخ ورود نوید معتبر نبود.",
            "code": "",
            "nextUrl": "",
        }
    return {
        "success": bool(decoded.get("success")),
        "error": "",
        "message": str(decoded.get("message") or ""),
        "code": str(((decoded.get("customResult") or {}).get("code")) or ""),
        "nextUrl": str(((decoded.get("customResult") or {}).get("url")) or ""),
        "raw": decoded,
    }


def _refresh_captcha(page: Page) -> Dict[str, Any]:
    response = _fetch_json(page, "/account/generate", method="POST")
    try:
        decoded = json.loads(response.get("text", ""))
    except Exception:
        decoded = None
    if not isinstance(decoded, dict) or not decoded.get("success"):
        return {
            "success": False,
            "error": "captcha_generate_failed",
            "message": "دریافت کپچای نوید انجام نشد.",
            "captchaDataUri": "",
        }
    custom = decoded.get("customResult") or {}
    image_b64 = str(custom.get("data") or "")
    content_type = str(custom.get("contentType") or "image/png")
    return {
        "success": True,
        "captchaDataUri": f"data:{content_type};base64,{image_b64}" if image_b64 else "",
        "captchaBase64": image_b64,
    }


def _build_challenge_payload(page: Page, login_url: str, refresh: bool = False) -> Dict[str, Any]:
    token = _token_from_dom(page)
    src = _captcha_src_from_dom(page)
    image_b64 = _base64_from_data_uri(src)
    captcha_data_uri = src if src.startswith("data:") else ""
    if refresh or not image_b64:
        refreshed = _refresh_captcha(page)
        if not refreshed.get("success"):
            return {
                "success": False,
                "error": refreshed.get("error") or "captcha_generate_failed",
                "message": refreshed.get("message") or "دریافت کپچای نوید انجام نشد.",
            }
        captcha_data_uri = str(refreshed.get("captchaDataUri") or "")
        image_b64 = str(refreshed.get("captchaBase64") or "")
    if not token:
        return {
            "success": False,
            "error": "token_not_found",
            "message": "توکن امنیتی نوید پیدا نشد.",
        }
    return {
        "success": True,
        "challengeState": {
            "loginUrl": login_url,
            "requestVerificationToken": token,
        },
        "captchaDataUri": captcha_data_uri,
        "captchaBase64": image_b64,
    }


def _dashboard_response(page: Page) -> Dict[str, Any]:
    response = _fetch_json(
        page,
        "/dashboard/getcourseslist",
        method="POST",
        json_body={"searchContent": "", "TermId": None},
        headers={"X-Requested-With": "XMLHttpRequest"},
    )
    headers = {str(k).lower(): str(v) for k, v in (response.get("headers") or {}).items()}
    responded_json = headers.get("x-responded-json", "")
    if responded_json:
        try:
            parsed = json.loads(responded_json)
        except Exception:
            parsed = {}
        if isinstance(parsed, dict) and int(parsed.get("status") or 0) == 401:
            return {
                "success": False,
                "error": "unauthorized",
                "message": "نشست نوید معتبر نیست.",
            }
    text = str(response.get("text") or "")
    if not text.strip():
        return {
            "success": False,
            "error": "dashboard_empty",
            "message": "پاسخ داشبورد نوید خالی بود.",
        }
    try:
        decoded = json.loads(text)
    except Exception:
        return {
            "success": False,
            "error": "dashboard_invalid",
            "message": "پاسخ داشبورد نوید معتبر نبود.",
        }
    if not isinstance(decoded, dict) or not decoded.get("success"):
        return {
            "success": False,
            "error": "dashboard_failed",
            "message": str((decoded or {}).get("message") or "خواندن داشبورد نوید انجام نشد."),
        }
    return {
        "success": True,
        "payload": decoded.get("customResult") or {},
    }


def _extract_courses(dashboard_payload: Dict[str, Any], login_url: str) -> List[Dict[str, Any]]:
    active_courses = dashboard_payload.get("activeCourses") or []
    if not isinstance(active_courses, list):
        return []
    origin = _origin(login_url)
    courses: Dict[str, Dict[str, Any]] = {}
    for entry in active_courses:
        if not isinstance(entry, dict):
            continue
        instance = entry.get("courseInstance") if isinstance(entry.get("courseInstance"), dict) else {}
        course_template_id = int(
            entry.get("activeCourseTemplateId")
            or instance.get("activeCourseTemplateId")
            or entry.get("courseTemplateId")
            or instance.get("courseTemplateId")
            or 0
        )
        if course_template_id <= 0:
            continue
        course_instance_id = int(
            entry.get("courseInstanceId")
            or instance.get("id")
            or instance.get("courseInstanceId")
            or 0
        )
        title = str(
            entry.get("courseTitle")
            or instance.get("courseTitle")
            or instance.get("title")
            or ""
        ).strip()
        term_title = str(entry.get("termTitle") or instance.get("termTitle") or "").strip()
        period_title = str(entry.get("periodTitle") or instance.get("periodTitle") or "").strip()
        courses[str(course_template_id)] = {
            "courseTemplateId": course_template_id,
            "courseTitle": title or f"درس {course_template_id}",
            "courseInstanceId": course_instance_id,
            "courseUrl": _absolute_url(origin, f"/coursetemplate/details/{course_template_id}/1/"),
            "activeCourseTemplateCount": int(entry.get("activeCourseTemplateCount") or instance.get("activeCourseTemplateCount") or 0),
            "termTitle": term_title,
            "periodTitle": period_title,
        }
    return [courses[key] for key in sorted(courses.keys(), key=lambda value: int(value))]


def _course_assignments(page: Page, course_template_id: int) -> Dict[str, Any]:
    response = _fetch_json(
        page,
        f"/Assignment/CourseAssignmentList?courseTemplateId={course_template_id}&isTeacherView=false&sessionId=",
        method="GET",
        headers={"X-Requested-With": "XMLHttpRequest"},
    )
    headers = {str(k).lower(): str(v) for k, v in (response.get("headers") or {}).items()}
    responded_json = headers.get("x-responded-json", "")
    if responded_json:
        try:
            parsed = json.loads(responded_json)
        except Exception:
            parsed = {}
        if isinstance(parsed, dict) and int(parsed.get("status") or 0) == 401:
            return {
                "success": False,
                "error": "unauthorized",
                "message": "نشست نوید هنگام دریافت تکالیف منقضی شد.",
            }
    text = str(response.get("text") or "")
    try:
        decoded = json.loads(text)
    except Exception:
        return {
            "success": False,
            "error": "assignment_invalid",
            "message": "پاسخ فهرست تکالیف معتبر نبود.",
        }
    if not isinstance(decoded, dict) or not decoded.get("success"):
        return {
            "success": False,
            "error": "assignment_failed",
            "message": str((decoded or {}).get("message") or "خواندن فهرست تکالیف انجام نشد."),
        }
    custom_result = decoded.get("customResult")
    return {
        "success": True,
        "assignments": custom_result if isinstance(custom_result, list) else [],
    }


def _success_payload(context: BrowserContext, login_url: str, dashboard_payload: Dict[str, Any]) -> Dict[str, Any]:
    courses = _extract_courses(dashboard_payload, login_url)
    page = context.new_page()
    _prime_same_origin(page, login_url)
    assignments_by_course: Dict[str, List[Dict[str, Any]]] = {}
    assignment_errors: Dict[str, Dict[str, Any]] = {}
    unauthorized = False
    for course in courses:
        course_template_id = int(course.get("courseTemplateId") or 0)
        if course_template_id <= 0:
            continue
        result = _course_assignments(page, course_template_id)
        key = str(course_template_id)
        if not result.get("success"):
            assignment_errors[key] = {
                "error": str(result.get("error") or "assignment_failed"),
                "message": str(result.get("message") or "خواندن فهرست تکالیف انجام نشد."),
            }
            if result.get("error") == "unauthorized":
                unauthorized = True
                break
            continue
        assignments_by_course[key] = result.get("assignments") if isinstance(result.get("assignments"), list) else []
    page.close()
    if unauthorized:
        return {
            "success": False,
            "error": "assignment_unauthorized",
            "message": "نشست نوید هنگام دریافت تکالیف منقضی شد.",
            "storageState": context.storage_state(),
        }
    return {
        "success": True,
        "storageState": context.storage_state(),
        "dashboardPayload": dashboard_payload,
        "courses": courses,
        "assignmentsByCourse": assignments_by_course,
        "assignmentErrors": assignment_errors,
        "sessionCookieNames": _cookie_names(context),
    }


def _sync_action(payload: Dict[str, Any]) -> Dict[str, Any]:
    login_url = _normalize_login_url(payload.get("loginUrl"))
    username = str(payload.get("username") or "").strip()
    password = str(payload.get("password") or "").strip()
    captcha_strategy = str(payload.get("captchaStrategy") or "python_ocr").strip() or "python_ocr"
    storage_state = payload.get("storageState") if isinstance(payload.get("storageState"), dict) else None

    pw, browser = _launch_browser()
    try:
        context = _new_context(browser, storage_state=storage_state)
        page = context.new_page()
        _prime_same_origin(page, login_url)
        dashboard = _dashboard_response(page)
        if dashboard.get("success"):
            page.close()
            return _success_payload(context, login_url, dashboard.get("payload") or {})
        page.close()

        if not username or not password:
            return {
                "success": False,
                "error": "credentials_missing",
                "message": "اطلاعات ورود نوید ذخیره نشده است.",
            }

        page = context.new_page()
        _goto_login(page, login_url)

        if captcha_strategy != "python_ocr":
            challenge = _build_challenge_payload(page, login_url)
            challenge["storageState"] = context.storage_state()
            return {
                **challenge,
                "success": False,
                "error": "manual_challenge_required",
                "message": "برای ادامه ورود نوید، کپچای دستی لازم است.",
            }

        attempts = 0
        last_solver_message = ""
        while attempts < 5:
            attempts += 1
            challenge = _build_challenge_payload(page, login_url, refresh=(attempts > 1))
            if not challenge.get("success"):
                challenge["storageState"] = context.storage_state()
                return {
                    "success": False,
                    "error": str(challenge.get("error") or "challenge_prepare_failed"),
                    "message": str(challenge.get("message") or "آماده‌سازی کپچای نوید انجام نشد."),
                    **challenge,
                }
            captcha_base64 = str(challenge.get("captchaBase64") or "")
            solved = _solve_captcha(captcha_base64)
            if len(solved) < 4:
                last_solver_message = "حل خودکار کپچا در دسترس نیست یا نتیجه معتبر نداد."
                break
            submit = _submit_login(
                page,
                login_url,
                str((challenge.get("challengeState") or {}).get("requestVerificationToken") or ""),
                username,
                password,
                solved,
            )
            if submit.get("success"):
                next_url = str(submit.get("nextUrl") or "")
                if next_url:
                    try:
                        page.goto(_absolute_url(_origin(login_url), next_url), wait_until="domcontentloaded", timeout=90000)
                    except Exception:
                        pass
                break
            code = str(submit.get("code") or "")
            if code == "FailLogin":
                return {
                    "success": False,
                    "error": "credentials_invalid",
                    "message": str(submit.get("message") or "نام کاربری یا رمز نوید نادرست است."),
                }
            if code != "FailCaptcha":
                return {
                    "success": False,
                    "error": "login_failed",
                    "message": str(submit.get("message") or "ورود نوید انجام نشد."),
                }
        else:
            last_solver_message = "ورود خودکار نوید پس از چند تلاش کپچا موفق نشد."

        dashboard = _dashboard_response(page)
        if dashboard.get("success"):
            page.close()
            return _success_payload(context, login_url, dashboard.get("payload") or {})

        challenge = _build_challenge_payload(page, login_url, refresh=True)
        challenge["storageState"] = context.storage_state()
        return {
            **challenge,
            "success": False,
            "error": "manual_challenge_required",
            "message": last_solver_message or "برای ادامه ورود نوید، کپچای دستی لازم است.",
        }
    finally:
        browser.close()
        pw.stop()


def _create_challenge_action(payload: Dict[str, Any]) -> Dict[str, Any]:
    login_url = _normalize_login_url(payload.get("loginUrl"))
    pw, browser = _launch_browser()
    try:
        context = _new_context(browser)
        page = context.new_page()
        _goto_login(page, login_url)
        challenge = _build_challenge_payload(page, login_url)
        challenge["storageState"] = context.storage_state()
        return challenge
    finally:
        browser.close()
        pw.stop()


def _complete_challenge_action(payload: Dict[str, Any]) -> Dict[str, Any]:
    login_url = _normalize_login_url(payload.get("loginUrl"))
    username = str(payload.get("username") or "").strip()
    password = str(payload.get("password") or "").strip()
    captcha_code = _normalize_captcha(str(payload.get("captchaCode") or ""))
    challenge_state = payload.get("challengeState") if isinstance(payload.get("challengeState"), dict) else {}
    storage_state = payload.get("storageState") if isinstance(payload.get("storageState"), dict) else None
    token = str(challenge_state.get("requestVerificationToken") or "").strip()

    if not username or not password:
        return {
            "success": False,
            "error": "credentials_missing",
            "message": "اطلاعات ورود نوید ذخیره نشده است.",
        }
    if len(captcha_code) < 4:
        return {
            "success": False,
            "error": "captcha_invalid",
            "message": "کد کپچا نامعتبر است.",
        }
    if not token or not storage_state:
        return {
            "success": False,
            "error": "challenge_missing",
            "message": "چالش کپچای فعال معتبر نیست.",
        }

    pw, browser = _launch_browser()
    try:
        context = _new_context(browser, storage_state=storage_state)
        page = context.new_page()
        _prime_same_origin(page, login_url)
        submit = _submit_login(page, login_url, token, username, password, captcha_code)
        if not submit.get("success"):
            code = str(submit.get("code") or "")
            if code == "FailLogin":
                return {
                    "success": False,
                    "error": "credentials_invalid",
                    "message": str(submit.get("message") or "نام کاربری یا رمز نوید نادرست است."),
                }
            refreshed = _fetch_json(page, "/account/generate", method="POST")
            try:
                refreshed_json = json.loads(refreshed.get("text", ""))
            except Exception:
                refreshed_json = {}
            custom = refreshed_json.get("customResult") if isinstance(refreshed_json, dict) else {}
            captcha_base64 = str((custom or {}).get("data") or "")
            content_type = str((custom or {}).get("contentType") or "image/png")
            next_token = token
            try:
                page2 = context.new_page()
                _goto_login(page2, login_url)
                next_token = _token_from_dom(page2) or next_token
                page2.close()
            except Exception:
                pass
            return {
                "success": False,
                "error": "captcha_invalid",
                "message": str(submit.get("message") or "کد کپچا اشتباه است."),
                "captchaDataUri": f"data:{content_type};base64,{captcha_base64}" if captcha_base64 else "",
                "challengeState": {
                    "loginUrl": login_url,
                    "requestVerificationToken": next_token,
                },
                "storageState": context.storage_state(),
            }
        next_url = str(submit.get("nextUrl") or "")
        if next_url:
            try:
                page.goto(_absolute_url(_origin(login_url), next_url), wait_until="domcontentloaded", timeout=90000)
            except Exception:
                pass
        dashboard = _dashboard_response(page)
        if not dashboard.get("success"):
            return {
                "success": False,
                "error": str(dashboard.get("error") or "dashboard_failed"),
                "message": str(dashboard.get("message") or "خواندن داشبورد نوید انجام نشد."),
                "storageState": context.storage_state(),
            }
        return _success_payload(context, login_url, dashboard.get("payload") or {})
    finally:
        browser.close()
        pw.stop()


def main() -> None:
    payload = _stdin_payload()
    action = str(payload.get("action") or "").strip().lower()
    try:
        if action == "sync":
            result = _sync_action(payload)
        elif action == "create_challenge":
            result = _create_challenge_action(payload)
        elif action == "complete_challenge":
            result = _complete_challenge_action(payload)
        else:
            result = {
                "success": False,
                "error": "invalid_action",
                "message": "درخواست helper نوید نامعتبر است.",
            }
    except Exception as exc:  # pragma: no cover
        result = {
            "success": False,
            "error": "bridge_exception",
            "message": f"Navid browser helper failed: {exc}",
        }
    _stdout_json(result)


if __name__ == "__main__":
    main()
