"""
Interne Service-Fassade für die Streamlit-App.
Die frühere HTTP-Kommunikation mit Docker-Services wird hierdurch ersetzt.
"""

from anzeigen_schema import Anzeige
from utils import log_event
from services import (
    process_uploaded_images,
    generate_listing_text,
    get_storage,
    StorageError,
    InvalidImageFormatError,
    UnreadableImageError,
    SaveFailedError,
    TextGenerationError,
)


def upload_images(files):
    """
    Processes multiple images locally (auto-crop always enabled).

    Args:
        files: List of uploaded files
    """
    try:
        data = process_uploaded_images(files)
        log_event(
            f"[upload_images] Erfolgreich verarbeitet: Ordner={data['uuid']} mit {len(data['files'])} Dateien"
        )
        return data
    except (InvalidImageFormatError, UnreadableImageError, SaveFailedError) as exc:
        log_event(f"[upload_images] Fehler: {exc}")
        raise RuntimeError(str(exc)) from exc


class TextServiceError(Exception):
    def __init__(self, status_code: int, detail: str):
        self.status_code = status_code
        self.detail = detail
        super().__init__(detail)


def generate_text(
    image: str, prompt_text: str | None = None, use_mock_api: bool = False
) -> dict:
    """
    Erzeugt Anzeigentext ohne Netzwerk-Request.
    """
    try:
        generated = generate_listing_text(image, prompt_text, use_mock_api)
        log_event("[generate_text] Beschreibung generiert.")
        return generated
    except TextGenerationError as exc:
        raise TextServiceError(500, str(exc)) from exc


def store_ad(ad: Anzeige, images: dict) -> tuple[bool, str | None]:
    """
    Speichert eine Anzeige über den lokalen Storage.
    """
    try:
        get_storage().save_ad_data(ad, images or {})
        log_event(f"[store_ad] Anzeige gespeichert für UUID={ad.uuid}")
        return True, None
    except StorageError as exc:
        log_event(f"[store_ad] Fehler: {exc}")
        return False, str(exc)


def update_ad(ad: Anzeige, images: dict[str, str] = {}) -> tuple[bool, str | None]:
    """
    Upsert für bestehende Anzeigen. Bilder werden nur ersetzt, wenn welche angegeben sind.
    """
    try:
        get_storage().save_ad_data(ad, images or {})
        return True, None
    except StorageError as exc:
        log_event(f"[update_ad] Fehler: {exc}")
        return False, str(exc)


def delete_ad(uuid: str) -> tuple[bool, str | None]:
    """
    Löscht eine Anzeige aus dem lokalen Storage.
    """
    try:
        get_storage().delete_ad(uuid)
        return True, None
    except StorageError as exc:
        log_event(f"[delete_ad] {uuid}: {exc}")
        return False, str(exc)


def fetch_all_ads_full(
    user_owner: str | None = None,
) -> tuple[list[tuple[Anzeige, dict[str, str], dict[str, str]]], str | None]:
    """
    Lädt alle Anzeigen inklusive Bilder und Thumbnails (base64) in einem Schritt.

    Returns:
        tuple: (list[(Anzeige, processed_images, thumbnails)], error_message)
    Caching is handled in the calling Streamlit pages via @st.cache_data wrapper.
    """
    try:
        return get_storage().list_all_ads(user_owner or None), None
    except StorageError as exc:
        log_event(f"[fetch_all_ads_full] Fehler: {exc}")
        return [], str(exc)
