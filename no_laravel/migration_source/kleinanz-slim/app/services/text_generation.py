"""
Textgenerierung direkt innerhalb der Streamlit-App.
"""

from __future__ import annotations

import base64
import json
import re
from pathlib import Path
from typing import Any

from openai import OpenAI
from pydantic import BaseModel, ValidationError

from config import (
    OPENAI_API_KEY,
    OPENAI_MODEL,
    TEMPERATURE,
    MAX_TOKENS,
    OPENAI_TIMEOUT,
    DEFAULT_DISCLAIMER,
)

try:
    import streamlit as st
except Exception:  # pragma: no cover - streamlit nicht in allen Umgebungen verfügbar
    st = None  # type: ignore
from utils import log_event


class GeneratedText(BaseModel):
    title: str
    description: str
    condition: str
    price: int
    shipping: str


class TextGenerationError(RuntimeError):
    """Fehler bei der Textgenerierung."""


PROMPT_DIR = Path(__file__).resolve().parent / "prompts"
SYSTEM_PROMPT = (PROMPT_DIR / "system_prompt.txt").read_text(encoding="utf-8")
AD_EXAMPLES = (PROMPT_DIR / "ad_examples.txt").read_text(encoding="utf-8").strip()


def generate_listing_text(
    image_base64: str, prompt_text: str | None = None, use_mock_api: bool = False
) -> dict:
    try:
        image_bytes = base64.b64decode(image_base64)
        if not image_bytes or len(image_bytes) < 64:
            raise ValueError("Empty or too small image payload")
    except Exception as exc:
        raise TextGenerationError(f"Invalid base64 image: {exc}") from exc

    messages = [
        {"role": "system", "content": SYSTEM_PROMPT},
        {
            "role": "user",
            "content": [
                {"type": "text", "text": build_user_instruction(prompt_text)},
                {
                    "type": "image_url",
                    "image_url": {"url": f"data:image/jpeg;base64,{image_base64}"},
                },
            ],
        },
    ]
    log_debug_request(messages)

    if use_mock_api:
        raw_data = load_mock_response()
        log_event("[text_generation] Verwende gespeicherte Mock-Antwort.")
    else:
        raw_data = _call_openai(messages)

    try:
        description = (raw_data.get("description") or "").strip()
        appendix = _get_active_disclaimer()
        raw_data["description"] = (
            description + ("\n\n" if description else "") + appendix
        ).strip()
        validated = GeneratedText(**raw_data)
        return validated.model_dump()
    except ValidationError as exc:
        raise TextGenerationError(f"Ungültiger Modell-Output: {exc}") from exc


def _call_openai(messages: list[dict[str, Any]]) -> dict:
    # Versuche zuerst den benutzerdefinierten API-Key aus session_state zu nutzen
    api_key = None
    if st is not None:
        try:
            api_key = st.session_state.get("openai_api_key")  # type: ignore[attr-defined]
        except Exception:
            pass
    
    # Fallback auf den globalen API-Key aus config
    if not api_key:
        api_key = OPENAI_API_KEY
    
    if not api_key:
        raise TextGenerationError(
            "Es ist kein OPENAI_API_KEY gesetzt. Aktiviere den Testmodus oder hinterlege einen Schlüssel."
        )

    client = OpenAI(api_key=api_key)
    try:
        response = client.chat.completions.create(
            model=OPENAI_MODEL,
            messages=messages,
            temperature=TEMPERATURE,
            max_tokens=MAX_TOKENS,
            timeout=OPENAI_TIMEOUT,
        )
    except Exception as exc:
        log_event(f"[text_generation] OpenAI-Aufruf fehlgeschlagen: {repr(exc)}")
        raise TextGenerationError(f"OpenAI-Request fehlgeschlagen: {exc}") from exc

    try:
        choice0 = response.choices[0]
        raw_text = (
            getattr(choice0.message, "content", "")
            if hasattr(choice0, "message")
            else ""
        )
    except Exception as exc:
        raise TextGenerationError(
            f"Unerwartete OpenAI-Antwortstruktur: {repr(exc)}"
        ) from exc

    snippet = (raw_text or "")[:2000]
    log_event(f"[text_generation] OpenAI-Rohtext (gekürzt):\n{snippet}")

    if not raw_text or not raw_text.strip():
        raise TextGenerationError("Leere Antwort vom Modell erhalten.")

    try:
        return json.loads(raw_text)
    except json.JSONDecodeError:
        match = re.search(r"\{", raw_text)
        if match:
            last = raw_text.rfind("}")
            candidate = raw_text[match.start() : last + 1 if last != -1 else None]
            try:
                log_event("[text_generation] JSON via Heuristik extrahiert.")
                return json.loads(candidate)
            except Exception as exc:
                raise TextGenerationError(
                    f"Antwort konnte nicht geparst werden: {exc}"
                ) from exc
        raise TextGenerationError("Antwort enthielt kein JSON-Objekt.")


def build_user_instruction(prompt: str | None = None) -> str:
    parts = [
        f"Hier sind Beispiel-Titel und -Beschreibung im gewünschten Stil:\n\n{AD_EXAMPLES}\n\n",
        "Bitte erstelle eine neue Anzeige für den folgenden Gegenstand im Bild.\n\n",
    ]
    if prompt and prompt.strip():
        parts.append(
            f'Zusatzinformationen des Nutzers (weil z.B. nicht erkennbar im Bild):\n\n"{prompt.strip()}"'
        )
    return "".join(parts)


def load_mock_response() -> dict:
    raw_text = """
{
  "title": "Beispielprodukt - Test",
  "description": "Dies ist nur ein Beispiel, da nicht *Artikelbeschreibung automatisch erstellen* ausgewählt wurde.\\n\\nWeitere Angebote sind über mein Profil einsehbar.",
  "condition": "Neu",
  "price": 10,
  "shipping": "mittel"
}
"""
    return json.loads(raw_text)


def log_debug_request(messages: list[dict[str, Any]]) -> None:
    try:
        redacted = json.loads(json.dumps(messages))
        for msg in redacted:
            if isinstance(msg.get("content"), list):
                for part in msg["content"]:
                    if part.get("type") == "image_url":
                        part["image_url"]["url"] = "[BASE64-IMAGE-REDACTED]"
        log_event(
            "[text_generation] Request:\n"
            + json.dumps(
                {
                    "model": OPENAI_MODEL,
                    "messages": redacted,
                    "temperature": TEMPERATURE,
                    "max_tokens": MAX_TOKENS,
                },
                indent=2,
            )
        )
    except Exception as exc:
        log_event(f"[text_generation] Fehler beim Debug-Logging: {exc}")


def _get_active_disclaimer() -> str:
    """Liest den Appendix aus der Session, falls vorhanden."""
    if st is None:  # pragma: no cover - kein Streamlit-Kontext verfügbar
        return DEFAULT_DISCLAIMER
    try:
        value = st.session_state.get("custom_appendix")  # type: ignore[attr-defined]
    except Exception:
        value = None
    if isinstance(value, str) and value.strip():
        return value.strip()
    return DEFAULT_DISCLAIMER
