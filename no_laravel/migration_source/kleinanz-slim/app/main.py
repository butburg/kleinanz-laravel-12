# 4_web_interface/app/main.py

import streamlit as st
import streamlit_authenticator as stauth
import bcrypt
import re
import random
from PIL import Image
from pathlib import Path
from config import (
    DEBUG_INFO,
    DEFAULT_USE_MOCK_API,
    AUTH_COOKIE_NAME,
    AUTH_COOKIE_SECRET,
    DEFAULT_DISCLAIMER,
)
from utils import log_event
from pages.create_ads import create_ads
from pages.list_ads import list_ads
from pages.info import info
from services import get_storage, StorageError


# ------------------ Caching-Funktion ------------------
@st.cache_data(show_spinner=False)
def load_image() -> Image.Image:
    """
    Lädt das Bild und cached es automatisch.
    Wenn das Bild sich auf der Festplatte ändert, erkennt Streamlit das
    und lädt neu.
    """
    here = Path(__file__).parent
    return Image.open(here / "faust.jpg")


# ------------------ Seiteneinstellungen ------------------
st.set_page_config(
    page_title="Anzeigen Generator", layout="centered", page_icon="app/favicon.png"
)
# favicon.png

st.logo(
    "app/weedyuniverse.png",
    size="large",
    link="https://buymeacoffee.com/butburg",
)

# ------------------ Authenticator config (DB-backed login) ------------------

DEFAULT_ADMIN_CREDS = {
    "usernames": {
        "admin": {
            "email": "admin",
            "name": "admin",
            "password": "$2b$12$CDonecpF9tXrOYmXlGp28OKMESFhZ1HcdGgFQegNb8XKii7STXYFG",  # bcrypt hash
        }
    }
}


@st.cache_data(show_spinner=False)
def load_credentials_from_db() -> dict:
    """Lädt alle Nutzer-Credentials aus der DB einmalig pro Session.

    - Jede Browser-Session erhält eigene Kopie (kein Global Singleton wie @st.cache_resource)
    - Neue Registrierungen in anderen Browser-Sessions sind sofort sichtbar (kein Multi-User Bug)
    - Nach neuer Registrierung im SELBEN Browser: Lokale Credentials-Dict wird manuell aktualisiert
    - Fallback: DEFAULT_ADMIN_CREDS, wenn DB leer ist.

    NOTE: st.error() is a static element (not interactive) so it's safe in cached functions
    and will be replayed on cache hits per Streamlit 1.16+ behavior.
    """
    try:
        storage = get_storage()
        creds = storage.get_all_credentials()
        # Fallback auf Admin, falls DB leer ist
        if not creds.get("usernames"):
            return DEFAULT_ADMIN_CREDS
        return creds
    except StorageError as exc:
        st.error(f"Login-Daten konnten nicht geladen werden: {exc}")
    except Exception as exc:
        st.error(f"Unerwarteter Fehler beim Laden der Login-Daten: {exc}")
    return DEFAULT_ADMIN_CREDS


# Credentials pro Session laden (Beschleunigung ohne Multi-User Bugs)
credentials = load_credentials_from_db()


# Don't cache Authenticator: stauth.Authenticate has internal widget commands
authenticator = stauth.Authenticate(
    credentials,
    AUTH_COOKIE_NAME,
    AUTH_COOKIE_SECRET,
    cookie_expiry_days=1,
    preauthorized=[],
    auto_hash=False,
)


# ------------------- Captcha- und Validierungs-Funktionen ------------------
def is_valid_email(email: str) -> bool:
    """Prüft ob eine E-Mail-Adresse gültig formatiert ist."""
    pattern = r"^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
    return bool(re.match(pattern, email))


def generate_captcha() -> tuple[str, int]:
    """
    Generiert ein einfaches mathematisches Captcha.
    Rückgabe: (Frage als String, Korrekte Antwort als Integer)
    """
    num1 = random.randint(1, 20)
    num2 = random.randint(1, 20)
    return f"{num1} + {num2} = ?", num1 + num2


def init_captcha_session():
    """Initialisiert das Captcha beim ersten Laden des Registrierungs-Formulars."""
    if "captcha_question" not in st.session_state:
        question, answer = generate_captcha()
        st.session_state["captcha_question"] = question
        st.session_state["captcha_answer"] = answer


def show_captcha_field() -> tuple[bool, str]:
    """
    Zeigt das Captcha-Feld und validiert die Eingabe.
    Rückgabe: (Ist valide, Fehlermeldung)
    """
    init_captcha_session()

    question = st.session_state["captcha_question"]

    if st.button(
        f"🔄 {question}", help="Neues Captcha generieren", key="refresh_captcha"
    ):
        new_question, new_answer = generate_captcha()
        st.session_state["captcha_question"] = new_question
        st.session_state["captcha_answer"] = new_answer
        st.rerun()

    # Captcha Input
    captcha_input = st.text_input(
        "Deine Antwort:", placeholder="z.B. 15", key="reg_captcha"
    )

    if captcha_input:
        try:
            user_answer = int(captcha_input.strip())
            if user_answer == st.session_state["captcha_answer"]:
                return True, ""
            else:
                return False, f"Leider Captcha falsch."
        except ValueError:
            return False, "Bitte geben Sie eine Zahl ein."

    return False, ""


# ------------------ Hilfs-Render-Funktionen ------------------
def render_hero_image(caption: str):
    cols = st.columns([1, 2, 1])
    with cols[1]:
        st.image(
            load_image(),
            caption=caption,
            width="stretch",
        )


def render_login_widget(expanded_intro: bool = True):
    """
    Zeigt die Start-/Login-Elemente:
    - Hero-Bild (Login-Branding)
    - Header + Kurzbeschreibung
    - Unterstützungsblock + Buy-Me-a-Coffee
    - Login-Formular
    - Changelog (Expander)
    """

    # Bild gehört zum Login
    render_hero_image("Und jetzt kommt die riesen Faust")

    # Header & Kurzerklärung
    st.title("Anzeigen Generator")

    st.caption("Schneller Anzeigen erstellen – einfach, schlank und kostenlos.")

    st.markdown(
        """
**So funktioniert’s in 3 Sekunden:**

📸 **Foto hochladen** von deinem Artikel.
🧠 KI analysiert das Bild und 📝 schreibt dir den
fertigen Anzeigentext, plus ✨ Titel  und 💰 Preis als Vorschlag. **Prüfen, anpassen, kopieren, fertig.** Ohne kompliziertes Setup.
        """
    )

    # Login-Widget (UI only, state managed by streamlit-authenticator)
    try:
        authenticator.login(
            location="main",
            fields={
                "Form name": "Login",
                "Username": "Benutzer",
                "Password": "Passwort",
            },
            key="auth",
        )
        # Show toast immediately after login attempt if it failed
        if st.session_state.get("authentication_status") is False:
            st.toast("❌ Login fehlgeschlagen. Bitte versuche es erneut.", icon="🔒")
            # Reset status immediately after showing toast to prevent it from persisting
            st.session_state["authentication_status"] = None
    except Exception as e:
        st.error(f"Login-Fehler: {e}")

    # Register-Widget direkt unter dem Login (eigene Form, weil register_user keinen Hash liefert)
    with st.expander("📝 Neuen Account erstellen"):
        if success_email := st.session_state.pop("registration_success_email", None):
            st.success(
                f"Account '{success_email}' erfolgreich erstellt! Bitte logge dich jetzt ein."
            )
            # Captcha zurücksetzen
            st.session_state.pop("captcha_question", None)
            st.session_state.pop("captcha_answer", None)

        # Captcha-Feld AUSSERHALB des Formulars (weil Button nicht in Form geht)
        captcha_valid, captcha_error = show_captcha_field()

        with st.form("register_form", clear_on_submit=True):
            email = st.text_input("E-Mail", key="reg_email")
            password = st.text_input("Passwort", type="password", key="reg_pwd")
            password_repeat = st.text_input(
                "Passwort wiederholen", type="password", key="reg_pwd2"
            )

            submitted = st.form_submit_button("Registrieren", use_container_width=True)

        if submitted:
            # Validierungen
            cleaned_email = (email or "").strip().lower()
            cleaned_name = cleaned_email
            pwd = (password or "").strip()
            pwd2 = (password_repeat or "").strip()

            if not cleaned_email or not pwd or not pwd2:
                st.error("E-Mail und Passwort dürfen nicht leer sein.")
            elif not is_valid_email(cleaned_email):
                st.error(
                    "Bitte geben Sie eine gültige E-Mail-Adresse ein (z.B. beispiel@domain.de)"
                )
            elif (
                len(pwd) < 8
                or not any(c.isalpha() for c in pwd)
                or not any(c.isdigit() for c in pwd)
            ):
                st.error(
                    "Passwort muss mindestens 8 Zeichen lang sein und Buchstaben sowie Zahlen enthalten."
                )
            elif pwd != pwd2:
                st.error("Passwörter stimmen nicht überein.")
            elif not captcha_valid:
                st.error(captcha_error or "Bitte lösen Sie das Captcha korrekt.")
            else:
                try:
                    # Prüfe ob E-Mail bereits existiert (credentials wurde fresh aus DB geladen)
                    if cleaned_email in credentials.get("usernames", {}):
                        st.error(
                            f"Die E-Mail '{cleaned_email}' ist bereits registriert."
                        )
                    else:
                        # Passwort hashen (bcrypt direkt, da Hasher-API nicht verfügbar)
                        hashed = bcrypt.hashpw(
                            pwd.encode("utf-8"), bcrypt.gensalt()
                        ).decode("utf-8")

                        storage = get_storage()
                        storage.save_user_credentials(
                            email=cleaned_email,
                            password_hash=hashed,
                        )

                        # Cache invalidieren, damit Login die frischen Credentials aus DB lädt
                        st.cache_data.clear()

                        st.session_state["registration_success_email"] = cleaned_email
                        st.toast(
                            "Registrierung erfolgreich. Bitte jetzt einloggen.",
                            icon="✅",
                        )
                        st.rerun()
                except StorageError as exc:
                    st.error(f"Registrierung gespeichert, aber DB-Fehler: {exc}")
                except Exception as exc:
                    st.error("Unerwarteter Fehler beim Speichern der Registrierung.")
                    st.exception(exc)


def logout():
    authenticator.logout(location="unrendered", key="nav")
    st.session_state["authentication_status"] = None
    st.session_state.pop("openai_api_key", None)
    st.session_state.pop("custom_appendix", None)
    st.rerun()


# ------------------ Session-Initialisierung ------------------

# Widget-Werte über Seitenwechsel erhalten (Option 3 aus Streamlit Docs)
# Verhindert, dass Streamlit Widget-Keys beim Navigieren zwischen Pages löscht
if "user_prompt_area" in st.session_state:
    st.session_state.user_prompt_area = st.session_state.user_prompt_area

if "use_fake_model" in st.session_state:
    st.session_state.use_fake_model = st.session_state.use_fake_model
else:
    st.session_state.setdefault("use_fake_model", DEFAULT_USE_MOCK_API)

if "openai_api_key" in st.session_state:
    st.session_state.openai_api_key = st.session_state.openai_api_key

st.session_state["authenticator"] = authenticator

st.session_state.setdefault("config_logged", False)
if not st.session_state["config_logged"]:
    log_event(f"[LOGIN] Konfiguration geladen: {DEBUG_INFO}")
    st.session_state["config_logged"] = True

st.session_state.setdefault(
    "list_ads_page", st.Page(list_ads, title="Gespeicherte Anzeigen", icon="💾")
)

st.html(
    f"""
    <style>
    /* Haupt-Block enger an den Header */
    [data-testid="stMainBlockContainer"] {{
        padding-top: 2rem !important;
    }}

    /* Copy-Button in Code-Blöcken immer sichtbar machen */
    .st-emotion-cache-chk1w8 {{
        opacity: 0.5 !important;
        visibility: visible !important;
    }}

    </style>
    """
)

# ------------------ Routing ------------------

# Not authenticated: show login only
if st.session_state.get("authentication_status") is None:
    # Vor Login: Start-/Login-Seite inkl. Bild und Changelog
    render_login_widget()
    st.stop()  # ← Prevent further execution

# Failed login: show error + login
if st.session_state.get("authentication_status") is False:
    # Login fehlgeschlagen: gleiches Login-Layout erneut anzeigen (keine Duplikate)
    # Toast wird bereits in render_login_widget() angezeigt
    render_login_widget()
    st.stop()  # ← Prevent further execution

# Authenticated: load API key and show app
# (At this point, we KNOW user is authenticated)
if "openai_api_key" not in st.session_state:
    try:
        storage = get_storage()
        username = st.session_state.get("username")
        api_key = storage.get_user_openai_key(username)
        if api_key:
            st.session_state["openai_api_key"] = api_key
    except Exception:
        pass

# Load user appendix
if "custom_appendix" not in st.session_state:
    try:
        storage = get_storage()
        username = st.session_state.get("username")
        user_appendix = storage.get_user_appendix(username)
        if user_appendix and str(user_appendix).strip():
            st.session_state["custom_appendix"] = str(user_appendix).strip()
        else:
            st.session_state["custom_appendix"] = DEFAULT_DISCLAIMER
    except Exception:
        st.session_state["custom_appendix"] = DEFAULT_DISCLAIMER

# Build and run app with cached pages
user_title = st.session_state.get("username") or "Kein Nutzer"
pages = [
    st.Page(create_ads, title="Erstellen", icon="📸"),
    st.session_state.get("list_ads_page"),
    st.Page(info, title=user_title, icon="ℹ️"),
    # st.Page(logout, title="Logout", icon="📋"),
]
pg = st.navigation(pages=pages, position="top")
pg.run()
