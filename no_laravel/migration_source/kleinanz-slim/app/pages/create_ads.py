# 4_web_interface/app/pages/create_ads.py
"""
Streamlit Web Interface für den Kleinanzeigen-Generator.

Workflow:
1. Bild hochladen → Bild verarbeiten
2. Text generieren lassen
3. Anzeige speichern

Zustand (file_id, generierter Text) wird in session_state gehalten.
"""

import streamlit as st
from PIL import Image
from datetime import datetime, timezone
from pydantic import ValidationError
from io import BytesIO

from api_client import generate_text, store_ad, upload_images, TextServiceError
from utils import render_error_with_details
from services import get_storage, StorageError
from services.image_processing import _fix_orientation

from config import SUPPORTED_FORMATS, DEFAULT_DISCLAIMER, DEFAULT_USE_MOCK_API
from anzeigen_schema import Anzeige

# Bild-Upload und Thumbnail-Preview
MAX_FILES = 10
APPENDIX_VALUE_KEY = "custom_appendix"


def get_active_appendix() -> str:
    """Gibt den aktuellen Appendix aus session_state zurück."""
    return str(st.session_state.get(APPENDIX_VALUE_KEY, DEFAULT_DISCLAIMER) or DEFAULT_DISCLAIMER).strip()


def on_appendix_change():
    """Callback wenn sich das Appendix-Textfeld ändert - speichert in DB und füllt leere Felder mit Default."""
    new_value = (st.session_state.get("appendix_editor") or "").strip()
    active_user = st.session_state.get("username") or None

    # Wenn leer → DEFAULT_DISCLAIMER, sonst den neuen Wert
    final_value = new_value or DEFAULT_DISCLAIMER
    st.session_state[APPENDIX_VALUE_KEY] = final_value

    # Wenn das Feld leer war, zeige den Default an
    if not new_value:
        st.session_state["appendix_editor"] = final_value

    # In DB speichern wenn Nutzer existiert
    if active_user:
        try:
            storage = get_storage()
            storage.set_user_appendix(active_user, new_value or None)
        except Exception:
            pass  # Fehler im Callback still ignorieren


@st.cache_data(show_spinner=False)
def make_thumbnail(
    file_bytes: bytes, size: tuple[int, int] = (400, 400)
) -> Image.Image:
    buffer = BytesIO(file_bytes)
    img = Image.open(buffer)
    img = _fix_orientation(img)
    img.thumbnail(size)
    return img


def get_valid_uploaded_files() -> list[BytesIO] | None:
    uploaded = st.file_uploader(
        "Bilder hochladen",
        type=SUPPORTED_FORMATS,
        accept_multiple_files=True,
        key="image_uploader",
        help=(
            "Nur das erste Bild wird zur Textgenerierung genutzt.  \n"
            f"Maximal {MAX_FILES} Bilder erlaubt.  \n"
            "Achtung: Das erste Bild wird ganz unten / auf der letzten Seite angezeigt."
        ),
    )
    if uploaded:
        if len(uploaded) > MAX_FILES:
            st.warning(
                f"⚠️ Maximal {MAX_FILES} Bilder erlaubt. Nur die ersten {MAX_FILES} werden gespeichert.\n\n"
                "**Bitte überprüfe die Vorschau:**"
            )
            uploaded = uploaded[:MAX_FILES]

        # Speichern im session_state
        processed = []
        for file in uploaded:
            file_bytes = file.read()
            buffer = BytesIO(file_bytes)
            buffer.name = file.name
            buffer.type = file.type
            processed.append(buffer)

        st.session_state["uploaded_images"] = processed
        return processed

    # Wenn nichts hochgeladen wurde → evtl. vorheriger Zustand?
    if "uploaded_images" in st.session_state:
        return st.session_state["uploaded_images"]

    return None


def show_image_preview(files: list):
    st.markdown("**🖼️ Aktuelle Vorschau:**")
    cols = st.columns(2)
    for i, file in enumerate(files):
        with cols[i % 2]:
            file.seek(0)
            img = make_thumbnail(file.read())
            if i == 0:
                with st.container(border=True):
                    st.markdown(
                        ":green-background[↓ Wird zur Textgenerierung genutzt ↓]"
                    )
                    st.image(
                        img,
                        width="stretch",
                    )
            else:
                st.image(
                    img,
                    width="stretch",
                )


def process_uploaded_images(files: list, user_prompt: str, use_mock: bool):
    result = upload_images(files)
    if not result or not result.get("files"):
        st.error("❌ Keine gültigen Bilddaten erhalten.")
        return None

    files_dict = result["files"]
    st.success(
        f"✅ {len(files_dict)} {'Bild' if len(files_dict) == 1 else 'Bilder'} erfolgreich verkleinert."
    )
    st.session_state.images = files_dict
    st.session_state.session_uuid = result["uuid"]
    # exakt EIN Bild verwenden: erstes mit 'data'
    try:
        first_key = sorted(files_dict.keys())[0]
        base64_image = files_dict[first_key]["data"]
        if not base64_image:
            raise ValueError("Kein Base64-Inhalt im ersten Bild.")
    except Exception as e:
        st.error("❌ Kein verwendbares Bild gefunden.")
        with st.expander("Details"):
            st.code(str(e), language="text")
        return None

    try:
        generated = generate_text(base64_image, user_prompt, use_mock)  # dict
    except TextServiceError as e:
        st.error("❌ Textgenerierung fehlgeschlagen.")
        with st.expander("Details"):
            # Exaktes Detail vom Service / OpenAI-Fehlertext, unverfälscht
            st.code(e.detail, language="text")
        return None
    except Exception as e:
        st.error("❌ Unerwarteter Fehler bei der Textgenerierung.")
        with st.expander("Details"):
            st.code(repr(e), language="text")
        return None

    st.session_state.generated_text = generated
    st.success("✅ Text erfolgreich generiert.")
    return generated, files_dict


def auto_save_ad(text: dict, images: dict, prompt: str):
    ad_data = {
        "uuid": st.session_state.session_uuid,
        "title": text.get("title", ""),
        "description": text.get("description", ""),
        "user_owner": (st.session_state.get("username") or None),
        "price": text.get("price", 0),
        "condition": text.get("condition", "Neu"),
        "shipping": text.get("shipping", "klein"),
        "images": [img["filename"] for _, img in images.items()],
        "prompt_text": prompt,
        "metadata": {
            "quelle": "webui",
            "created_at": datetime.now(timezone.utc).isoformat(),
        },
    }

    try:
        ad = Anzeige(**ad_data)
        success, error_detail = store_ad(ad, images)
        if success:
            # Invalidate ad list cache after successful store
            st.cache_data.clear()
            st.success("💾 Anzeige gespeichert.")
            # 💡 Speichere UUID für das Ziel
            st.session_state["open_ad_uuid"] = ad.uuid
            # 👈 redirect vormerken
            st.session_state["navigate_to_list_ads"] = True
            st.rerun()
        else:
            st.error("❌ Fehler beim automatischen Speichern.")
            render_error_with_details(
                error_detail or "Keine Detailmeldung erhalten.",
                title="Fehlerdetails aus dem Datenspeicher",
            )
    except ValidationError as ve:
        st.error("❌ Fehlerhafte Felder beim Speichern.")
        st.code(ve.json(), language="json")


def reset_creation_state():
    """Clear all inputs without requiring a manual page reload."""
    keys_to_reset = [
        "uploaded_images",
        "generated_text",
        "images",
        "open_ad_uuid",
        "navigate_to_list_ads",
    ]

    for key in keys_to_reset:
        st.session_state.pop(key, None)

    # file_uploader/text_area values need explicit reset
    del st.session_state["image_uploader"]
    del st.session_state["user_prompt_area"]

    st.cache_data.clear()
    st.toast("Alle Eingaben gelöscht.", icon="🧹")
    st.rerun()


def create_ads():
    # ⏩ Redirect bei Bedarf
    if st.session_state.get("navigate_to_list_ads"):
        st.session_state["navigate_to_list_ads"] = False

        st.switch_page(st.session_state.get("list_ads_page"))

    st.set_page_config(page_title="Anzeige Erstellen")
    st.subheader("📸 Erstellen")

    # API-Schlüssel prüfen: zuerst aus session_state, dann aus Cache (DB)
    api_key = st.session_state.get("openai_api_key")

    has_api_key = bool(api_key)

    # Mock-Modus: entweder manuell aktiviert oder automatisch wenn kein API-Key
    mock_mode_active = (
        st.session_state.setdefault("use_fake_model", DEFAULT_USE_MOCK_API)
        or not has_api_key
    )

    user_prompt = st.text_area(
        "Beschreibe stichpunktartig Details wie Verkaufsgrund, Zustand oder genaue Artikel-Nummer (optional):",
        key="user_prompt_area",
        max_chars=300,
        placeholder="z.B. Artikel wurde einmal getragen, verkauft weil zu groß, ...",
        help="Um ein besseres Ergebnis zu erhalten, beschreibe deinen Artikel. Füge Details hinzu, die auf dem Bild nicht erkennbar sind.",
    )

    # Initialisiere appendix_editor mit dem richtigen Wert bevor das Widget erstellt wird
    if "appendix_editor" not in st.session_state:
        st.session_state["appendix_editor"] = get_active_appendix()

    files = get_valid_uploaded_files()
    if files:
        show_image_preview(files)

        if st.button("Generieren", width="stretch", type="primary"):
            try:
                use_mock = (
                    st.session_state.get(
                        "use_fake_model", False) or not has_api_key
                )
                result = process_uploaded_images(files, user_prompt, use_mock)

                if result:
                    text, images = result
                    auto_save_ad(text, images, user_prompt)
            except RuntimeError as exc:
                st.error("❌ Bildverarbeitung fehlgeschlagen.")
                render_error_with_details(
                    str(exc), title="Details zur Bildverarbeitung"
                )
            except Exception as e:
                st.error("❌ Unerwarteter Fehler beim Hochladen.")
                st.exception(e)

    st.divider()

    appendix_text = st.text_area(
        "Appendix - Zusätzliche Information an jede Beschreibung anhängen",
        height=120,
        key="appendix_editor",
        placeholder="z.B. Versand möglich, Selbstabholung, Kontaktinformationen...",
        help="Dieser Text wird automatisch an jede neu generierte Beschreibung angehängt. Leer lassen, um Default wiederherzustellen.",
        on_change=on_appendix_change,
    )

    # Warnungen: immer anzeigen wenn kein API-Key, zusätzlich wenn manueller Test-Modus
    if not has_api_key:
        st.warning(
            "⚠️ **Kein OpenAI API-Schlüssel gesetzt** – Die Textgenerierung verwendet das Mock-/Testmodell. "
            "Bitte gehe zur Info-Seite und hinterlege deinen API-Schlüssel für echte KI-Generierung.",
            icon="🔑",
        )
    elif st.session_state.get("use_fake_model", False):
        st.warning(
            "TEST&nbsp;MODUS: Die Textgenerierung verwendet aktuell das Mock-/Testmodell.",
            icon="⚠️",
        )


# --------- Fallback-Link auf Home, wenn nicht eingeloggt
if st.session_state.get("authentication_status") is not True:
    st.markdown('<a href="/" target="_self">🏠 Home</a>',
                unsafe_allow_html=True)
