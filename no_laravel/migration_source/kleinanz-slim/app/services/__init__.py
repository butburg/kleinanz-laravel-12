"""
Zentrale Service-Schnittstellen für die Streamlit-App.
Kapselt Bildverarbeitung, Textgenerierung und Datenspeicherung.
"""

from functools import lru_cache

from .image_processing import (
    process_uploaded_images,
    InvalidImageFormatError,
    UnreadableImageError,
    SaveFailedError,
)
from .text_generation import generate_listing_text, TextGenerationError
from .storage import AdStorage, StorageError
from config import DATABASE_URL


@lru_cache(maxsize=1)
def get_storage() -> AdStorage:
    """Singleton für den Datenbankzugriff."""
    return AdStorage(DATABASE_URL)


__all__ = [
    "process_uploaded_images",
    "InvalidImageFormatError",
    "UnreadableImageError",
    "SaveFailedError",
    "generate_listing_text",
    "TextGenerationError",
    "get_storage",
    "StorageError",
]
