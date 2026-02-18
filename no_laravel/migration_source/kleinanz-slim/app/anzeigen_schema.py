# 4_web_interface/app/anzeigen_schema.py

from typing import List, Literal, Optional
from pydantic import BaseModel, Field
from datetime import datetime, timezone
from config import (
    AD_TITLE_MAX_LENGTH,
    AD_DESCRIPTION_MIN_LENGTH,
    AD_DESCRIPTION_MAX_LENGTH,
    AD_PROMPT_MAX_LENGTH,
    AD_USER_OWNER_MAX_LENGTH,
    AD_IMAGES_MIN,
    AD_IMAGES_MAX,
)


class Anzeige(BaseModel):
    uuid: str
    title: str = Field(..., max_length=AD_TITLE_MAX_LENGTH)
    description: str = Field(..., min_length=AD_DESCRIPTION_MIN_LENGTH, max_length=AD_DESCRIPTION_MAX_LENGTH)
    price: int  # Ganze Euro-Beträge
    user_owner: Optional[str] = Field(default=None, max_length=AD_USER_OWNER_MAX_LENGTH)
    condition: Literal["Neu", "Sehr gut", "Gut", "In Ordnung", "Defekt"]
    shipping: Literal["klein", "mittel"]
    status: Literal["Entwurf", "Archiviert", "Online"] = "Entwurf"
    images: List[str] = Field(..., min_items=AD_IMAGES_MIN, max_items=AD_IMAGES_MAX)
    metadata: Optional[dict] = Field(default_factory=lambda: {"created_at": datetime.now(timezone.utc).isoformat()})
    prompt_text: Optional[str] = Field(None, max_length=AD_PROMPT_MAX_LENGTH)

class AnzeigeMetadata(BaseModel):
    quelle: str = "webui"
    created_at: str = Field(default_factory=lambda: datetime.now(timezone.utc).isoformat())
