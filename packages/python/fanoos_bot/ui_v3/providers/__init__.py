"""FANOOS Rebuild V3 provider-native Telegram/Bale presentation layer.

This package is intentionally transport-wiring neutral. A later integration
worker connects these render plans to the existing Telegram/Bale runtimes while
preserving canonical backend authority and replay/idempotency invariants.
"""

from .bale import BaleRenderPlan, BaleV3Renderer
from .contract import (
    CallbackAckIntent,
    ProviderAction,
    ProviderContext,
    ProviderContractError,
    ProviderFact,
    ProviderScreen,
    ProviderSection,
    adapt_screen,
)
from .examples import ProviderExample, rendered_examples, representative_examples
from .policy import (
    BALE_CAPABILITIES,
    TELEGRAM_CAPABILITIES,
    DensityAssessment,
    MessageDensityPolicy,
    ProviderCapabilities,
    ProviderRenderError,
    pack_actions,
)
from .telegram import TelegramRenderPlan, TelegramV3Renderer

__all__ = [
    "BALE_CAPABILITIES",
    "TELEGRAM_CAPABILITIES",
    "BaleRenderPlan",
    "BaleV3Renderer",
    "CallbackAckIntent",
    "DensityAssessment",
    "MessageDensityPolicy",
    "ProviderAction",
    "ProviderCapabilities",
    "ProviderContext",
    "ProviderContractError",
    "ProviderExample",
    "ProviderFact",
    "ProviderRenderError",
    "ProviderScreen",
    "ProviderSection",
    "TelegramRenderPlan",
    "TelegramV3Renderer",
    "adapt_screen",
    "pack_actions",
    "rendered_examples",
    "representative_examples",
]
