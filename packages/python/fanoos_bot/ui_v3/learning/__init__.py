"""FANOOS bot V3 learning/commerce presentation surface.

This package is source-only and provider-neutral. Integration binds its semantic
screens to bot-01/core and its intents to bot-04/providers/current application
routing without moving business authority into presentation code.
"""

from .intents import LearningIntent
from .screens import (
    ProtectedDeliveryState,
    assessment_detail_screen,
    assessment_hub_screen,
    commerce_hub_screen,
    domain_state_screen,
    form_detail_screen,
    forms_hub_screen,
    order_access_detail_screen,
    protected_delivery_screen,
    resource_detail_screen,
    resource_hub_screen,
)

__all__ = [
    "LearningIntent",
    "ProtectedDeliveryState",
    "resource_hub_screen",
    "resource_detail_screen",
    "protected_delivery_screen",
    "assessment_hub_screen",
    "assessment_detail_screen",
    "commerce_hub_screen",
    "order_access_detail_screen",
    "forms_hub_screen",
    "form_detail_screen",
    "domain_state_screen",
]
