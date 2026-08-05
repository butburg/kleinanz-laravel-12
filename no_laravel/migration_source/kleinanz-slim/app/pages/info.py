# 4_web_interface/app/pages/info.py
"""
Info & Service: zentrale Infos und Debug-Optionen für die monolithische App.
"""

import streamlit as st
from config import DEBUG_INFO, DEFAULT_USE_MOCK_API
from services import StorageError, get_storage


def _show_debug_dialog():
    @st.dialog("🔧 Debug-Informationen")
    def _open():
        st.caption(
            "Werte werden aus ENV → config.py ermittelt. Secrets sind maskiert.")
        st.json(DEBUG_INFO, expanded=True)

    _open()


def _get_database_status():
    try:
        storage = get_storage()
    except StorageError as exc:
        return False, str(exc)
    except Exception as exc:
        return False, f"Unerwarteter Fehler ({exc.__class__.__name__})."
    try:
        return storage.ping()
    except StorageError as exc:
        return False, str(exc)
    except Exception as exc:
        return False, f"Ping fehlgeschlagen ({exc.__class__.__name__})."


def render_changelog():
    with st.expander("🗒️ Changelog – Version 0.8 (Februar 2026)"):
        st.markdown(
            """
- **Version 0.8 (Februar 2026)**

    - Bildverarbeitung: Auto-Crop für Kleidungsartikel mit YOLO-Objekterkennung – Bilder werden automatisch auf das Kleidungsstück zugeschnitten.
    - Vorschau-Fix: EXIF-Orientierung wird jetzt auch in der Live-Vorschau korrekt angewendet (bisher nur nach dem Speichern).

- **Version 0.7 (Dezember 2025)**

    - UI: Anzeigen-Layout überarbeitet – Galerie oberhalb der Textdetails.
    - Validierung: Zentrale Längenlimits in `config.py` eingeführt (Titel, Beschreibung, Prompt, Bilder), genutzt durch `anzeigen_schema.py` und im Edit-Dialog mit client-/serverseitigen Checks.
    - Sicherheit: Bearbeiten-Dialog bereinigt – **Nutzer-Auswahl entfernt** (kein Ändern von `user_owner`), **Prompt nicht mehr editierbar** (read-only).
    - API/Config: Fixes für OpenAI-Model/Key und generelle Konfigurationswerte; kleinere Typo-/UI-Fixes.

- **Version 0.6 (Dezember 2025)**

    - Neue Datenbank-Statusanzeige auf der Info-Seite (online/offline) mit verständlichen Kurzmeldungen.
    - Deutlich verbesserte Fehlermeldungen bei Datenbankproblemen (Initialisierung, Speichern, Laden, Löschen, Ping).
    - Debug-Dialog zeigt die Datenbank-Konfiguration nur noch in maskierter Form und kennzeichnet lokale vs. Remote-Verbindungen.
    - Einheitliches Favicon/Icon für App und Unterseiten.
    - Buy-Me-a-Coffee-Link öffnet jetzt in einem neuen Tab.
    - Bessere Performance und geringere Betriebskosten durch den Wechsel von AWS zur Streamlit Cloud (streamlit.io).
    - Einführung einer einfachen Nutzerverwaltung: Anzeigen erhalten optional einen Besitzer (`user_owner`), können in der Liste nach Nutzer gefiltert werden und der Besitzer lässt sich im Bearbeiten-Dialog anpassen.

- **Version 0.5 (August 2025)**

    - Startseite überarbeitet (Header, Kurzbeschreibung, Emojis)
    - Unterstützungsbereich mit Buy-Me-a-Coffee hinzugefügt
    - Kostenhinweis („eine Kugel Eis“) ergänzt
"""
        )


def info():
    # Sichtbarer Seitentitel/Name aktualisiert
    st.set_page_config(page_title="Info & Service")

    # -------- OpenAI API Key Eingabe
    st.subheader("🔑 OpenAI API-Schlüssel")
    st.caption("Gib hier deinen eigenen OpenAI API-Schlüssel ein. ")

    current_user_email = st.session_state.get("username")
    storage = None
    storage = get_storage()
    current_api_key = st.session_state.get("openai_api_key") or ""

    col_api_input, col_api_btn = st.columns(
        [3, 1], vertical_alignment="bottom")
    with col_api_input:
        # Nach dem Speichern: Input-Feld leeren und keinen Wert anzeigen
        input_value = st.session_state.get("_api_key_temp", "")
        api_key_input = st.text_input(
            "API-Schlüssel",
            value=input_value,
            type="password",
            key="openai_api_key_input",
            help="Deinen API-Schlüssel findest du unter platform.openai.com",
        )

    with col_api_btn:
        # Button ist nur aktiviert, wenn das Input-Feld nicht leer ist
        is_input_empty = not (api_key_input or "").strip()
        if st.button(
            "💾 Speichern",
            key="save_api_key_btn",
            use_container_width=True,
            disabled=is_input_empty,
        ):
            cleaned_key = (api_key_input or "").strip()
            if current_user_email and storage:
                try:
                    storage.set_user_openai_key(
                        current_user_email, cleaned_key or None)
                    # Invalidate API key cache after update so next load gets fresh value
                    st.cache_data.clear()
                except StorageError as exc:
                    st.error(f"Konnte API-Schlüssel nicht speichern: {exc}")
                except Exception as exc:
                    st.error(
                        "Unerwarteter Fehler beim Speichern des API-Schlüssels.")
                    st.exception(exc)
            if cleaned_key:
                st.session_state["openai_api_key"] = cleaned_key
                st.session_state["_api_key_temp"] = ""
                st.toast("API-Schlüssel gespeichert.", icon="✅")
            else:
                st.session_state.pop("openai_api_key", None)
                st.session_state["_api_key_temp"] = ""
                st.toast("API-Schlüssel entfernt.", icon="🗑️")
            st.rerun()

    if current_api_key:
        st.success(f"✓ API-Schlüssel gesetzt ({current_api_key[:3]}...)")
    else:
        st.info("ℹ️ Bitte hier deinen OpenAI API-Schlüssel eingeben.")

    st.divider()

    # -------- Header / Intro
    st.subheader("ℹ️ Info & Service")

    st.caption("Schneller Anzeigen erstellen – einfach, schlank und kostenlos.")
    st.markdown(
        """
    **So funktioniert’s in 3 Sekunden:**

    📸 **Foto hochladen** – von deinem Artikel.
    🧠 KI analysiert das Bild und 📝 schreibt dir den
    fertigen Anzeigentext, plus ✨ Titel  und 💰 Preis als Vorschlag. **Prüfen, anpassen, kopieren, fertig.** Ohne kompliziertes Setup.
            """
    )

    st.divider()

    # -------- Unterstützen
    left, right = st.columns([2, 1])
    with left:
        st.subheader("💛 Unterstütze den Entwickler")
    with right:
        st.html(
            """<div>
<a href="https://ko-fi.com/butburg" style="cursor: pointer; display: inline-block; padding: 8px 16px; background-color: #29abe0; color: #ffffff; border-radius: 6px; text-decoration: none; font-family: sans-serif; font-size: 14px;" target="_blank" rel="noopener">
    ☕ Support me on Ko-fi
</a>
</div>"""
        )
    st.write(
        "Der Betrieb dieser Anwendung kostet den Entwickler etwa **eine Kugel Eis 🍦 pro Monat (ca. 3 €)**. "
        "Wenn dir das Tool gefällt, kannst du mit einem kleinen Beitrag die laufenden Kosten unterstützen."
    )
    st.caption("Mehr Seiten und Tools aus dem Weedy Universe findest du hier:")
    st.link_button(
        "Weedy Universe – weitere Projekte",
        "https://ko-fi.com/butburg",
    )

    st.divider()

    # -------- Footer: Logout (falls verfügbar)
    authenticator = st.session_state.get("authenticator", None)
    if authenticator:
        st.subheader("🔐 Logout")
        if st.button("Logout"):
            pages = [st.Page(info, title="Login")]
            st.navigation(pages=pages, position="top")
            authenticator.logout(location="unrendered")

    st.divider()

    # Changelog (Expander)
    render_changelog()

    st.divider()

    # -------- Test & Debug Block
    st.subheader("🧪 Test & Debug")
    st.session_state.setdefault("use_fake_model", DEFAULT_USE_MOCK_API)
    with st.container(border=True):
        cols = st.columns([2, 1.2])
        with cols[0]:
            st.checkbox(
                "Test-Modus aktivieren (Erstellung der KI-Artikelbeschreibung simulieren)",
                key="use_fake_model",
                help=(
                    "Wenn aktiviert, wird ein Fake-OpenAI-Modell verwendet und ein Beispiel-Text generiert. "
                    "Ideal zum Testen – es entstehen keine OpenAI-Kosten."
                ),
            )
        with cols[1]:
            if st.button("🔧 Debug öffnen", width="stretch"):
                _show_debug_dialog()

        db_online, db_message = _get_database_status()
        if db_online:
            st.success(f"Datenbank online – {db_message}")
        else:
            st.warning(f"Datenbank offline – {db_message}")


# --------- Fallback-Link auf Home, wenn nicht eingeloggt
if st.session_state.get("authentication_status") is not True:
    st.markdown('<a href="/" target="_self">🏠 Home</a>',
                unsafe_allow_html=True)
