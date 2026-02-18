"""
Konfiguration für die monolithische Streamlit-App.
Streamlit Cloud liefert Secrets über `.streamlit/secrets.toml`, lokal
können weiterhin normale Environment-Variablen genutzt werden.
"""

from __future__ import annotations

import os
from pathlib import Path

try:
    import streamlit as st

    _SECRETS = st.secrets
except Exception:  # pragma: no cover - st.secrets nur im Streamlit-Runner vorhanden
    _SECRETS = {}


def _mask(secret: str | None, show_first: int = 6) -> str:
    if not secret:
        return ""
    return secret[:show_first] + "••••"


def _get_setting(key: str, default=None):
    """Liest Settings priorisiert aus Streamlit-Secrets, sonst aus ENV."""
    try:
        value = _SECRETS[key]
    except Exception:
        value = os.getenv(key, default)
    return value if value is not None else default


def _to_bool(value, default=False):
    if isinstance(value, bool):
        return value
    if value is None:
        return default
    return str(value).strip().lower() == "true"


def _to_int(value, default: int) -> int:
    try:
        return int(str(value))
    except (TypeError, ValueError):
        return default


def _to_float(value, default: float) -> float:
    try:
        return float(str(value))
    except (TypeError, ValueError):
        return default


BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = Path(_get_setting("DATA_DIR", BASE_DIR.parent / "data"))
DATA_DIR.mkdir(parents=True, exist_ok=True)


def _is_local_database(url: str | None) -> bool:
    if not url:
        return False
    value = str(url).strip()
    lower_value = value.lower()
    # Pfade ohne Schema gelten als lokal (z. B. ./data/ads.db)
    if "://" not in value:
        return True
    # sqlite:/// oder file:/// verweisen auf lokale Dateien
    if lower_value.startswith("sqlite:///") or lower_value.startswith("sqlite:////"):
        return True
    if lower_value.startswith("file://"):
        return True
    return False


def _debug_database_url(url: str | None) -> str:
    if not url:
        return ""
    if _is_local_database(url):
        return str(url)
    return "[remote connection hidden]"


# === Feature Flags ===
DEFAULT_USE_MOCK_API = _to_bool(_get_setting("DEFAULT_USE_MOCK_API", "false"))

# === Anzeigen-Feldvalidierung ===
AD_TITLE_MAX_LENGTH = _to_int(_get_setting("AD_TITLE_MAX_LENGTH", 80), 80)
AD_DESCRIPTION_MIN_LENGTH = _to_int(
    _get_setting("AD_DESCRIPTION_MIN_LENGTH", 50), 50)
AD_DESCRIPTION_MAX_LENGTH = _to_int(
    _get_setting("AD_DESCRIPTION_MAX_LENGTH", 1000), 1000
)
AD_PROMPT_MAX_LENGTH = _to_int(
    _get_setting("AD_PROMPT_MAX_LENGTH", 1000), 1000)
AD_USER_OWNER_MAX_LENGTH = _to_int(
    _get_setting("AD_USER_OWNER_MAX_LENGTH", 255), 255)
AD_IMAGES_MIN = _to_int(_get_setting("AD_IMAGES_MIN", 1), 1)
AD_IMAGES_MAX = _to_int(_get_setting("AD_IMAGES_MAX", 10), 10)

# === Bildverarbeitung ===
IMAGE_MAX_SIZE = _to_int(_get_setting("MAX_IMAGE_SIZE", 1000), 1000)
JPEG_QUALITY = _to_int(_get_setting("JPEG_QUALITY", 85), 85)
PROGRESSIVE_JPEG = _to_bool(_get_setting("PROGRESSIVE_JPEG", "true"), True)

# === Auto-Crop (Clothing Detection) ===
# Auto-crop is always enabled - required for optimal image processing
AUTO_CROP_DETECTION_THRESHOLD = _to_float(
    _get_setting("AUTO_CROP_DETECTION_THRESHOLD", 0.2), 0.2
)
AUTO_CROP_CLOSEUP_THRESHOLD = _to_float(
    _get_setting("AUTO_CROP_CLOSEUP_THRESHOLD", 0.70), 0.70
)
AUTO_CROP_MARGIN_PERCENT = _to_int(
    _get_setting("AUTO_CROP_MARGIN_PERCENT", 2), 2
)
AUTO_CROP_MODEL = (BASE_DIR.parent / "models" / "yolov8n-fashionpedia-1.onnx").as_posix()

# === Galerie / Thumbnails ===
GALLERY_CONTAINER_HEIGHT = _to_int(
    _get_setting("GALLERY_CONTAINER_HEIGHT", 380), 380)
THUMBNAIL_MAX_WIDTH = _to_int(_get_setting("THUMBNAIL_MAX_WIDTH", 220), 220)
_raw_formats = _get_setting("SUPPORTED_FORMATS", "jpg,jpeg,png,avif")
if isinstance(_raw_formats, (list, tuple, set)):
    SUPPORTED_FORMATS = {
        str(ext).strip().lower() for ext in _raw_formats if str(ext).strip()
    }
else:
    SUPPORTED_FORMATS = {
        ext.strip().lower() for ext in str(_raw_formats).split(",") if ext.strip()
    }

# === OpenAI / Text-Service ===
OPENAI_API_KEY = str(_get_setting("OPENAI_API_KEY", "") or "")
OPENAI_MODEL = str(_get_setting(
    "OPENAI_MODEL", "gpt-4o-mini") or "gpt-4o-mini")
TEMPERATURE = _to_float(_get_setting("TEMPERATURE", 0.7), 0.7)
MAX_TOKENS = _to_int(_get_setting("MAX_TOKENS", 1000), 1000)
OPENAI_TIMEOUT = _to_int(_get_setting("TIMEOUT_SECONDS", 30), 30)
PROMPT_APPENDIX = (
    "Abholung bevorzugt, Versand möglich.\n"
    "Privatverkauf. Ich schließe jegliche Sachmangelhaftung aus."
)
DEFAULT_DISCLAIMER = str(
    _get_setting("DEFAULT_DISCLAIMER", PROMPT_APPENDIX) or PROMPT_APPENDIX
)

# === Datenhaltung ===
DATABASE_URL = _get_setting("DATABASE_URL")
if not DATABASE_URL:
    DATABASE_URL = f"sqlite:///{(DATA_DIR / 'ads.db').as_posix()}"

# === Auth / Cookies ===
AUTH_COOKIE_NAME = str(
    _get_setting("AUTH_COOKIE_NAME", "anzeigen_cookie") or "anzeigen_cookie"
)
AUTH_COOKIE_SECRET = str(
    _get_setting(
        "AUTH_COOKIE_SECRET",
        _get_setting("COOKIE_SECRET_KEY",
                     "dev-insecure-cookie-secret-key-1234567"),
    )
    or "dev-insecure-cookie-secret-key-1234567"
)

DEBUG_INFO = {
    "OPENAI_API_KEY(masked)": _mask(OPENAI_API_KEY),
    "OPENAI_MODEL": OPENAI_MODEL,
    "DATABASE_URL": _debug_database_url(DATABASE_URL),
    "DATABASE_IS_LOCAL": _is_local_database(DATABASE_URL),
    "DATA_DIR": str(DATA_DIR),
    "USE_MOCK_API": DEFAULT_USE_MOCK_API,
    "AUTH_COOKIE_NAME": AUTH_COOKIE_NAME,
}
