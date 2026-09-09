"""Provider-neutral action intents for FANOOS bot V3 learning surfaces.

These values describe user intent only. They are not Telegram/Bale callback payloads,
API routes, authorization claims, payment proof, or protected-delivery capability
values. Integration owns binding them to bot-01 routing and bot-04 provider actions.
"""

from __future__ import annotations

from enum import StrEnum


class LearningIntent(StrEnum):
    # Learning/resource navigation.
    RESOURCES_OPEN = "learning.resources.open"
    RESOURCES_RECENT = "learning.resources.recent"
    RESOURCES_FILTER_COURSE = "learning.resources.filter.course"
    RESOURCES_FILTER_TYPE = "learning.resources.filter.type"
    RESOURCES_PAGE_PREVIOUS = "learning.resources.page.previous"
    RESOURCES_PAGE_NEXT = "learning.resources.page.next"
    RESOURCE_OPEN = "learning.resource.open"
    RESOURCE_DELIVER = "learning.resource.deliver"

    # Protected-content state transitions. Provider code must never reinterpret
    # these as authorization; backend delivery/derivative contracts stay canonical.
    PROTECTED_CHECK = "learning.protected.check"
    PROTECTED_REFRESH = "learning.protected.refresh"
    PROTECTED_RETRY = "learning.protected.retry"
    PROTECTED_OPEN_RESOURCE = "learning.protected.resource"

    # Assessments. Native attempt/scoring actions are deliberately absent until
    # a bot-safe canonical assessment contract exists.
    ASSESSMENTS_OPEN = "learning.assessments.open"
    ASSESSMENTS_FILTER_COURSE = "learning.assessments.filter.course"
    ASSESSMENTS_FILTER_STATE = "learning.assessments.filter.state"
    ASSESSMENT_OPEN = "learning.assessment.open"
    ASSESSMENT_OPEN_WEB = "learning.assessment.open_web"

    # Commerce/access. No raw product-id purchase intent is exposed as primary UX.
    COMMERCE_OPEN = "learning.commerce.open"
    COMMERCE_OPEN_WEB = "learning.commerce.open_web"
    ORDER_OPEN = "learning.order.open"
    ORDER_REFRESH = "learning.order.refresh"
    PAYMENT_OPEN_WEB = "learning.payment.open_web"
    PAYMENT_RETRY_WEB = "learning.payment.retry_web"

    # Forms/secondary services. Submission remains a Website handoff unless a
    # future bot-safe canonical contract is integrated explicitly.
    FORMS_OPEN = "learning.forms.open"
    FORM_OPEN = "learning.form.open"
    FORM_OPEN_WEB = "learning.form.open_web"

    # Shared exits. bot-01 may map these to its canonical shell intents.
    BACK = "core.back"
    HOME = "core.home"
