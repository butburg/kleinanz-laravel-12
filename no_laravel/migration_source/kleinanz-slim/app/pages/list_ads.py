# 4_web_interface/app/pages/list_ads.py
import base64
from PIL import Image
import streamlit as st
from st_copy import copy_button
from api_client import fetch_all_ads_full, delete_ad, update_ad
from io import BytesIO
from utils import render_error_with_details
from datetime import datetime, timezone, timedelta
from anzeigen_schema import Anzeige

# Nutzer-Auswahl entfernt: keine Services-Imports nötig
from config import (
    AD_TITLE_MAX_LENGTH,
    AD_DESCRIPTION_MIN_LENGTH,
    AD_DESCRIPTION_MAX_LENGTH,
    GALLERY_CONTAINER_HEIGHT,
    THUMBNAIL_MAX_WIDTH,
)


def render_flash():
    # kurze Toasts
    if msg := st.session_state.pop("flash_toast", None):
        icon, text = msg  # ("✅", "Anzeige gelöscht.") etc.
        st.toast(text, icon=icon)

    # Fehlerblock ohne Trace
    if msg := st.session_state.pop("flash_error", None):
        # hier msg ist einfach ein String
        st.error(msg)


def get_status_color(status: str) -> str:
    """Returns the markdown color code for a given status."""
    status_colors = {
        "Entwurf": "orange",
        "Archiviert": "gray",
        "Online": "green",
    }
    return status_colors.get(status, "orange")  # default to orange


def get_expiry_date(ad_data) -> datetime | None:
    """Berechnet das Ablaufdatum einer Anzeige (60 Tage nach last_online_at)."""
    metadata = ad_data.metadata or {}
    last_online_str = metadata.get('last_online_at')
    if last_online_str:
        try:
            last_online = datetime.fromisoformat(last_online_str)
            return last_online + timedelta(days=60)
        except (ValueError, TypeError):
            return None
    return None


def is_expired(ad_data) -> bool:
    """Prüft, ob eine Anzeige abgelaufen ist (60 Tage nach last_online_at überschritten)."""
    expiry = get_expiry_date(ad_data)
    if expiry:
        return datetime.now(timezone.utc) >= expiry
    return False


def format_expiry_date(ad_data) -> str | None:
    """Formatiert das Ablaufdatum als 'dd.mm.yyyy' oder None."""
    expiry = get_expiry_date(ad_data)
    if expiry:
        return expiry.strftime("%d.%m.%Y")
    return None


@st.cache_data(show_spinner=False)
def _cached_fetch_all_ads_full(user_owner: str | None = None):
    """Cached wrapper for fetch_all_ads_full. Cache is invalidated in pages when ads change."""
    anzeigen, error_detail = fetch_all_ads_full(user_owner)
    # Convert Anzeige objects to dicts for pickling
    if anzeigen:
        anzeigen = [
            (ad_data.model_dump(), images, thumbnails)
            for ad_data, images, thumbnails in anzeigen
        ]
    return anzeigen, error_detail


decoder = st.cache_data(show_spinner=False)(lambda b64: _decode_and_thumb(b64))


def _decode_and_thumb(base64_str: str) -> Image.Image:
    raw = base64.b64decode(base64_str)
    buf = BytesIO(raw)
    img = Image.open(buf)

    img.thumbnail((THUMBNAIL_MAX_WIDTH, THUMBNAIL_MAX_WIDTH * 4))
    return img


def _render_copy_button(text):
    """Render copy button in a container with fixed height to avoid layout shift."""
    with st.container(height=44, border=False):
        if text:
            copy_button(text, tooltip="Kopieren", copied_label="Kopiert!")
        else:
            st.empty()


@st.fragment
def lazy_expander(
    title: str,
    key: str,
    on_expand,
    expanded: bool = False,
    status: str = "Entwurf",
    callback_kwargs: dict | None = None,
):
    """
    Kleiner Helfer: Rendert Inhalt erst beim Aufklappen.

    Args:
        title: Titel des Expanders.
        key: Eindeutiger Key für Session-State.
        on_expand: Funktion(container, **kwargs), die beim Aufklappen ausgeführt wird.
        expanded: Anfangszustand.
        status: Status für die Titel-Farbe (Entwurf, Archiviert, Online).
        callback_kwargs: Zusätzliche Argumente für on_expand.
    """
    if callback_kwargs is None:
        callback_kwargs = {}

    if key not in st.session_state:
        st.session_state[key] = expanded

    outer = st.container(border=True)
    current = bool(st.session_state[key])
    # Arrow + title in one button label (no wrap, fully clickable)
    arrow_icon = (
        ":material/keyboard_arrow_down:"
        if current
        else ":material/keyboard_arrow_right:"
    )
    status_color = get_status_color(status)
    button_label = f":{status_color}[{arrow_icon} {title}]"

    with outer:
        if st.button(
            button_label,
            key=f"{key}_toggle",
            width="content",
            type="tertiary",
            help=None,
        ):
            # Toggle state and force rerun to ensure sync
            st.session_state[key] = not current
            st.rerun()

        # Only render content when expanded
        if st.session_state[key]:
            with st.container():
                on_expand(outer, **callback_kwargs)


def list_ads():
    st.set_page_config(page_title="Gespeicherte Anzeigen")

    left, right = st.columns([2, 1])
    with left:
        st.subheader("💾 Gespeicherte Anzeigen")
    with right:
        st.html(
            """<div>
<a href="https://ko-fi.com/butburg" style="cursor: pointer; display: inline-block; padding: 8px 16px; background-color: #29abe0; color: #ffffff; border-radius: 6px; text-decoration: none; font-family: sans-serif; font-size: 14px;" target="_blank" rel="noopener">
    ☕ Support me on Ko-fi
</a>
</div>"""
        )

    render_flash()

    active_user = st.session_state.get("username") or None
    anzeigen, error_detail = _cached_fetch_all_ads_full(active_user)
    if error_detail:
        st.error("❌ Fehler beim Laden der Anzeigen.")
        render_error_with_details(
            error_detail, title="Fehlerdetails aus dem Datenspeicher"
        )
        return

    # Convert dicts back to Anzeige objects
    if anzeigen:
        anzeigen = [
            (Anzeige(**ad_dict), images, thumbnails)
            for ad_dict, images, thumbnails in anzeigen
        ]

    if not anzeigen:
        st.info("Noch keine Anzeigen gespeichert.")
        return

    for ad_data, images, thumbnails in anzeigen:
        uuid = ad_data.uuid
        st.session_state.setdefault(f"images_loaded_{uuid}", False)
        st.session_state.setdefault(f"thumbs_{uuid}", {})
        st.session_state.setdefault(f"confirm_delete_{uuid}", False)

        expanded = st.session_state.pop("open_ad_uuid", None) == uuid

        # Get status for color handling
        current_status = getattr(ad_data, 'status', 'Entwurf')

        # Add expiry icon if ad is expired
        expiry_icon = " ⏰" if is_expired(ad_data) else ""

        def _on_expand(
            container,
            uid=uuid,
            imgs=images,
            thumbs_b64=thumbnails,
            title=ad_data.title,
            ad=ad_data,
        ):
            # Beim ersten Aufklappen Thumbnails laden (bereits von DB generiert)
            if not st.session_state[f"images_loaded_{uid}"]:
                thumbs = {}
                for filename, thumb_b64_str in thumbs_b64.items():
                    try:
                        thumbs[filename] = decoder(thumb_b64_str)
                    except Exception:
                        thumbs[filename] = None
                st.session_state[f"thumbs_{uid}"] = thumbs
                st.session_state[f"images_loaded_{uid}"] = True

            thumbs = st.session_state[f"thumbs_{uid}"]
            with container:
                part_gallery(uid, thumbs, imgs, title)
                part_edit_inline(ad, uid)
                st.space("small")
                part_delete_button(uid, ad)

        # Test: eigener Lazy-Expander mit Pfeilsteuerung
        lazy_expander(
            title=f"📄 {ad_data.title}{expiry_icon}",
            key=f"expander_{uuid}",
            on_expand=_on_expand,
            expanded=expanded,
            status=current_status,
        )

    st.caption(f"{len(anzeigen or [])} Anzeigen")


def part_gallery(uuid, thumbs, images, ad_title):
    # Galerie mit fester Kachelbreite und horizontalem Overflow (kein Wrap)
    items_html: list[str] = []
    for filename, thumb in thumbs.items():
        if not thumb:
            st.warning(f"❌ Bild {filename} konnte nicht geladen werden.")
            continue

        buf = BytesIO()
        thumb.save(buf, format="JPEG", quality=85)
        thumb_b64 = base64.b64encode(buf.getvalue()).decode()
        full_b64 = images.get(filename, "")

        if not full_b64:
            st.warning(
                f"⚠️ Kein Vollbild für {filename} gefunden. Keys in images: {list(images.keys())}"
            )
            continue

        # Store base64 in data attribute - DOMPurify won't strip this
        items_html.append(
            f"""
            <div class=\"gallery-item\" style=\"flex:0 0 {THUMBNAIL_MAX_WIDTH}px;max-width:{THUMBNAIL_MAX_WIDTH}px;display:flex;flex-direction:column;gap:8px;align-items:center;text-align:center;box-sizing:border-box;\">
                <img style=\"width:100%;height:auto;object-fit:contain;border-radius:4px;display:block;\" src=\"data:image/jpeg;base64,{thumb_b64}\" alt=\"{filename}\" />
                <a class=\"gallery-download\" style=\"display:block;width:100%;padding:8px 12px;border:1px solid rgba(128,128,128,0.3);border-radius:6px;text-decoration:none;color:inherit;background:rgba(255,255,255,0.05);box-sizing:border-box;cursor:pointer;\" download=\"{ad_title}_{filename}\" data-fullimg=\"{full_b64}\">💾 Bild speichern</a>
            </div>
            """
        )

    html = f"""
    <style>
    #gallery-wrapper-{uuid} {{
        display: flex;
        padding: 12px;
        border: 1px solid rgba(128, 128, 128, 0.2);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.02);
        box-sizing: border-box;
        width: 100%;
        justify-content: center;
    }}
    #gallery-{uuid} {{
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        gap: 12px;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        white-space: nowrap;
    }}
    #gallery-{uuid} .gallery-item {{
        flex: 0 0 {THUMBNAIL_MAX_WIDTH}px;
        max-width: {THUMBNAIL_MAX_WIDTH}px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: center;
        text-align: center;
        box-sizing: border-box;
    }}
    #gallery-{uuid} img {{
        width: 100%;
        height: auto;
        object-fit: contain;
        border-radius: 4px;
        display: block;
    }}
    #gallery-{uuid} .gallery-download {{
        display: block;
        width: 100%;
        padding: 8px 12px;
        border: 1px solid rgba(128, 128, 128, 0.3);
        border-radius: 6px;
        text-decoration: none;
        color: inherit;
        background: rgba(255, 255, 255, 0.05);
        box-sizing: border-box;
        cursor: pointer;
        font: inherit;
    }}
    #gallery-{uuid} .gallery-download:hover {{
        background: rgba(255, 255, 255, 0.12);
    }}
    </style>
    <div id="gallery-wrapper-{uuid}" style="display:flex;padding:12px;border:1px solid rgba(128,128,128,0.2);border-radius:8px;background:rgba(255,255,255,0.02);box-sizing:border-box;width:100%;justify-content:center;">
        <div id="gallery-{uuid}" style="display:flex;flex-direction:row;flex-wrap:nowrap;gap:12px;overflow-x:auto;overflow-y:hidden;-webkit-overflow-scrolling:touch;white-space:nowrap;">
            {''.join(items_html)}
        </div>
    </div>
    <script>
    (function() {{
        var gallery = document.getElementById('gallery-{uuid}');
        if (gallery) {{
            var links = gallery.querySelectorAll('.gallery-download');
            links.forEach(function(link) {{
                var fullImg = link.getAttribute('data-fullimg');
                if (fullImg) {{
                    link.href = 'data:image/jpeg;base64,' + fullImg;
                }}
            }});
        }}
    }})();
    </script>
    """

    st.html(html, unsafe_allow_javascript=True)


def part_delete_button(uuid, ad_data):
    @st.dialog("🗑️ Komplette Anzeige löschen")
    def confirm_delete_dialog(uuid_delete):
        st.warning("Diese Aktion kann nicht rückgängig gemacht werden.")
        _, col_dialog_aboard, col_dialog_delete = st.columns(3)
        with col_dialog_aboard:
            if st.button(
                "❌ Abbrechen",
                key=f"cancel_delete_btn_{uuid_delete}",
                width="stretch",
            ):
                st.session_state[f"confirm_delete_{uuid_delete}"] = False
                st.rerun()
        with col_dialog_delete:
            if st.button(
                "✅ Ja, löschen",
                key=f"confirm_delete_btn_{uuid_delete}",
                width="stretch",
            ):
                ok, msg = delete_ad(uuid_delete)
                st.session_state[f"confirm_delete_{uuid_delete}"] = False
                if ok:
                    # Invalidate ad list cache after successful delete
                    st.cache_data.clear()
                    # kurzer Erfolg außerhalb des Dialogs
                    st.session_state["flash_toast"] = (
                        "✅", "Anzeige gelöscht.")
                else:
                    # nur kurze Fehlermeldung, kein Trace
                    st.session_state["flash_error"] = msg
                st.rerun()

    if st.button(
        "🗑️ Anzeige Löschen", key=f"trigger_delete_{uuid}", width="stretch"
    ):
        st.session_state[f"confirm_delete_{uuid}"] = True
    if st.session_state[f"confirm_delete_{uuid}"]:
        confirm_delete_dialog(uuid)


def part_edit_inline(ad_data, uuid):
    """Renders editable form fields with auto-save."""

    def _save_ad(updated_fields):
        """Helper to save updated ad data."""
        updated_ad = ad_data.model_copy(update=updated_fields)
        success, error_detail = update_ad(updated_ad)
        if success:
            st.cache_data.clear()
            st.rerun()
        else:
            st.session_state["flash_error"] = error_detail or "Speichern fehlgeschlagen."
            st.rerun()

    # Create unique keys for form fields
    key_title = f"title_{uuid}"
    key_desc = f"desc_{uuid}"
    key_price = f"price_{uuid}"
    key_shipping = f"shipping_{uuid}"
    key_condition = f"condition_{uuid}"
    key_status = f"status_{uuid}"

    # Initialize session state keys if not present
    st.session_state.setdefault(key_title, ad_data.title)
    st.session_state.setdefault(key_desc, ad_data.description)
    st.session_state.setdefault(key_price, ad_data.price)
    st.session_state.setdefault(key_shipping, ad_data.shipping)
    st.session_state.setdefault(key_condition, ad_data.condition)
    st.session_state.setdefault(
        key_status, getattr(ad_data, 'status', 'Entwurf'))

    st.divider()
    st.subheader("✏️ Bearbeiten")

    col1, col2 = st.columns([2, 1])

    # Title input with auto-save
    with col1:
        new_title = st.text_input(
            "📝 Titel",
            max_chars=AD_TITLE_MAX_LENGTH,
            key=key_title,
            help=f"Maximal {AD_TITLE_MAX_LENGTH} Zeichen",
        )
        _render_copy_button(new_title)
        if new_title and new_title != ad_data.title:
            _save_ad({"title": new_title})

    # Description input with auto-save
    new_desc = st.text_area(
        "📄 Beschreibung",
        height=150,
        max_chars=AD_DESCRIPTION_MAX_LENGTH,
        key=key_desc,
        help=f"Zwischen {AD_DESCRIPTION_MIN_LENGTH} und {AD_DESCRIPTION_MAX_LENGTH} Zeichen",
    )
    _render_copy_button(new_desc)
    if new_desc and new_desc != ad_data.description:
        if AD_DESCRIPTION_MIN_LENGTH <= len(new_desc) <= AD_DESCRIPTION_MAX_LENGTH:
            _save_ad({"description": new_desc})

    # Price input with auto-save
    with col2:
        new_price = st.number_input(
            "💰 Preis (€)",
            min_value=0,
            step=1,
            key=key_price,
        )
        if new_price != ad_data.price:
            _save_ad({"price": int(new_price)})

    # Shipping and condition in columns
    col_ship, col_cond = st.columns(2)

    with col_ship:
        shipping_options = ["klein", "mittel"]
        new_shipping = st.selectbox(
            "📦 Versandgröße",
            shipping_options,
            key=key_shipping,
        )
        if new_shipping != ad_data.shipping:
            _save_ad({"shipping": new_shipping})

    with col_cond:
        condition_options = ["Neu", "Sehr gut", "Gut", "In Ordnung", "Defekt"]
        new_condition = st.selectbox(
            "🌟 Zustand",
            condition_options,
            key=key_condition,
        )
        if new_condition != ad_data.condition:
            _save_ad({"condition": new_condition})

    # Status radio buttons
    status_options = ["Entwurf", "Online", "Archiviert"]
    new_status = st.radio(
        "Status",
        status_options,
        horizontal=True,
        key=key_status,
        help="Vermerke hier, ob deine Anzeige schon online ist.",
    )
    if new_status != getattr(ad_data, 'status', 'Entwurf'):
        _save_ad({"status": new_status})

    # Show prompt (read-only)
    st.caption("🧠 Prompt (wird nicht geändert)")
    st.code(ad_data.prompt_text or "–", language="text")

    # Show expiry date if available
    expiry_date_str = format_expiry_date(ad_data)
    if expiry_date_str:
        st.info(f"⏰ Diese Anzeige endet am: **{expiry_date_str}**")


if st.session_state.get("authentication_status") is not True:
    st.markdown('<a href="/" target="_self">🏠 Home</a>',
                unsafe_allow_html=True)
