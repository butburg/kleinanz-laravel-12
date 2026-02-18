"""
Lokale Bildverarbeitung ohne separaten FastAPI-Service.
"""

from __future__ import annotations

import base64
import uuid
from datetime import datetime, timezone
from io import BytesIO
from typing import Iterable

from PIL import Image, UnidentifiedImageError, ExifTags

from config import (
    IMAGE_MAX_SIZE,
    THUMBNAIL_MAX_WIDTH,
    JPEG_QUALITY,
    PROGRESSIVE_JPEG,
    SUPPORTED_FORMATS,
)
from utils import log_event

# Import auto_crop - lazy load to avoid import errors if YOLO not installed
try:
    from services.auto_crop import auto_crop_if_needed
    AUTO_CROP_AVAILABLE = True
except ImportError:
    AUTO_CROP_AVAILABLE = False
    log_event(
        "[image_processing] auto_crop not available (ultralytics not installed)")


class InvalidImageFormatError(Exception):
    """Nicht unterstütztes Dateiformat."""


class UnreadableImageError(Exception):
    """Datei konnte nicht als Bild interpretiert werden."""


class SaveFailedError(Exception):
    """Catch-all für unerwartete Fehler."""


def process_uploaded_images(files: Iterable) -> dict:
    """
    Verarbeitet eine Liste von Streamlit-Uploads und liefert Base64-Daten zurück.

    Args:
        files: Iterable of uploaded files

    Returns:
        dict: { "status": "success", "uuid": str, "files": {"0": {"filename": str, "data": base64, "thumbnail": base64}} }
    """
    session_uuid = (
        f"{datetime.now(timezone.utc).isoformat(timespec='seconds').replace(':', '-')}"
        f"-{uuid.uuid4().hex[:6]}"
    )
    log_event(f"[image_processing] Session gestartet: {session_uuid}")

    encoded_images: dict[str, dict[str, str]] = {}

    for idx, file in enumerate(files):
        filename = f"image_{idx}.jpg"
        try:
            file.seek(0)
            original_name = getattr(file, "name", filename)
            processed_bytes, thumbnail_bytes = process_single_image(
                file.read(), original_name
            )
            image_b64 = base64.b64encode(processed_bytes).decode("utf-8")
            thumb_b64 = base64.b64encode(thumbnail_bytes).decode("utf-8")
            encoded_images[str(idx)] = {
                "filename": filename,
                "data": image_b64,
                "thumbnail": thumb_b64,
            }
            log_event(f"[image_processing] {original_name} → {filename}")
        except (InvalidImageFormatError, UnreadableImageError):
            raise
        except Exception as exc:
            raise SaveFailedError(
                f"Unerwarteter Fehler beim Verarbeiten von {filename}: {exc}"
            ) from exc

    return {"status": "success", "uuid": session_uuid, "files": encoded_images}


def process_single_image(
    file_bytes: bytes, original_name: str
) -> tuple[bytes, bytes]:
    """
    Processes a single image and returns both processed and thumbnail versions.

    Args:
        file_bytes: Raw image bytes
        original_name: Original filename

    Returns:
        tuple[bytes, bytes]: (processed_image_bytes, thumbnail_bytes)
    """
    if not file_bytes:
        raise UnreadableImageError("Datei ist leer.")

    try:
        img = Image.open(BytesIO(file_bytes))
        img_format = (img.format or "").lower()
        log_event(
            f"[process_single_image] Datei={original_name} format={img_format} size={img.size}"
        )

        if img_format not in SUPPORTED_FORMATS:
            raise InvalidImageFormatError(
                f"Unsupported image format: {img_format or 'unknown'}"
            )

        img = _fix_orientation(img)

        # Apply auto-crop (always enabled)
        if AUTO_CROP_AVAILABLE:
            try:
                img, was_cropped = auto_crop_if_needed(img)
                if was_cropped:
                    log_event(
                        f"[process_single_image] Auto-cropped {original_name}")
            except Exception as crop_err:
                log_event(f"[auto_crop] Error during auto-crop: {crop_err}")
                # Continue without cropping if auto-crop fails

        img = img.convert("RGB")

        # Create processed version (1000px)
        processed_img = img.copy()
        processed_img.thumbnail((IMAGE_MAX_SIZE, IMAGE_MAX_SIZE))
        processed_buffer = BytesIO()
        processed_img.save(
            processed_buffer,
            "JPEG",
            quality=JPEG_QUALITY,
            optimize=True,
            progressive=PROGRESSIVE_JPEG,
        )

        # Create thumbnail version with fixed width (THUMBNAIL_MAX_WIDTH)
        thumbnail_img = img.copy()
        # Preserve aspect ratio: max width 220px, max height 4x (880px)
        thumbnail_img.thumbnail(
            (THUMBNAIL_MAX_WIDTH, THUMBNAIL_MAX_WIDTH * 4), Image.Resampling.LANCZOS
        )
        thumbnail_buffer = BytesIO()
        thumbnail_img.save(
            thumbnail_buffer,
            "JPEG",
            quality=JPEG_QUALITY,
            optimize=True,
            progressive=PROGRESSIVE_JPEG,
        )

        return processed_buffer.getvalue(), thumbnail_buffer.getvalue()
    except UnidentifiedImageError as exc:
        log_event(
            f"[process_single_image] Bild konnte nicht gelesen werden: {exc}")
        raise UnreadableImageError(
            "Invalid or unreadable image file.") from exc


def _fix_orientation(img: Image.Image) -> Image.Image:
    """Dreht Bilder basierend auf EXIF-Daten, falls vorhanden."""
    try:
        orientation_tag = next(
            (tag for tag, name in ExifTags.TAGS.items()
             if name == "Orientation"), None
        )
        if orientation_tag is None:
            return img

        exif = img._getexif()  # type: ignore[attr-defined]
        if not exif:
            return img
        orientation = exif.get(orientation_tag)
        if orientation == 3:
            return img.rotate(180, expand=True)
        if orientation == 6:
            return img.rotate(270, expand=True)
        if orientation == 8:
            return img.rotate(90, expand=True)
        return img
    except Exception as exc:
        log_event(f"[image_processing] EXIF-Rotation fehlgeschlagen: {exc}")
        return img
