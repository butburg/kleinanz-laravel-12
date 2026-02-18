"""
Persistenzschicht für Anzeigen (SQLite/MySQL via SQLAlchemy).
"""

from __future__ import annotations

import base64
import json
from typing import Dict, List, Tuple
from datetime import datetime, timezone

from sqlalchemy import (
    Column,
    Integer,
    LargeBinary,
    MetaData,
    String,
    Table,
    Text,
    create_engine,
    delete,
    select,
    text,
)
from sqlalchemy.engine import Engine
from sqlalchemy.exc import SQLAlchemyError

from anzeigen_schema import Anzeige
from utils import log_event


class StorageError(RuntimeError):
    """Fehler beim Zugriff auf den Datenspeicher."""


def _summarize_db_error(exc: Exception) -> str:
    """Erzeugt eine nutzerfreundliche Fehlermeldung ohne geheime Details."""
    orig = getattr(exc, "orig", exc)
    errno = getattr(orig, "errno", None)
    message = str(getattr(orig, "msg", "")) or str(orig)
    lowered = message.lower()

    if "access denied" in lowered:
        base = "Datenbankzugriff verweigert – Zugangsdaten oder Rechte prüfen."
    elif "unknown database" in lowered or "doesn't exist" in lowered:
        base = "Datenbank nicht gefunden – Namen prüfen."
    elif "timeout" in lowered or "timed out" in lowered:
        base = "Verbindung zur Datenbank ist abgelaufen."
    elif (
        "could not connect" in lowered
        or "connection refused" in lowered
        or "unknown mysql server host" in lowered
    ):
        base = "Verbindung zur Datenbank konnte nicht aufgebaut werden."
    elif "no such table" in lowered:
        base = "Erwartete Tabelle fehlt – Migration/Schema prüfen."
    elif "data too long for column" in lowered:
        column_hint = ""
        needle = "column '"
        start = lowered.find(needle)
        if start != -1:
            start += len(needle)
            end = message.find("'", start)
            if end != -1:
                column_hint = f" – Feld '{message[start:end]}'"
        base = (
            "Ein Feldinhalt war zu lang für die Datenbank."
            f"{column_hint} Bitte Eingabe kürzen oder Schema prüfen."
        )
    else:
        base = "Unbekannter Datenbankfehler – Details im Log."

    result = base
    if errno is not None:
        result = f"{result} (Fehlercode {errno})"
    return result


class AdStorage:
    """Einfacher Storage-Layer mit zwei Tabellen (ads, ad_images)."""

    def __init__(self, db_url: str):
        try:
            self.engine: Engine = create_engine(db_url, future=True, pool_pre_ping=True)
            self.metadata = MetaData()
            self.ads = Table(
                "ads",
                self.metadata,
                Column("id", String(64), primary_key=True),
                Column("ad_json", Text, nullable=False),
            )
            self.ad_images = Table(
                "ad_images",
                self.metadata,
                Column("id", Integer, primary_key=True, autoincrement=True),
                Column("ad_id", String(64), nullable=False),
                Column("filename", String(255), nullable=False),
                Column(
                    "image",
                    LargeBinary(length=(2**24) - 1),  # MEDIUMBLOB (~16 MB) for MySQL
                    nullable=False,
                ),
                Column(
                    "thumbnail",
                    LargeBinary(length=(2**24) - 1),  # BLOB for thumbnail
                    nullable=True,  # Nullable for backward compatibility with existing rows
                ),
            )
            # Nutzer-Tabelle: Email als Primärschlüssel (Login-Name)
            self.users = Table(
                "users",
                self.metadata,
                Column("email", String(255), primary_key=True),
                Column("password_hash", String(255), nullable=True),
                Column("appendix", Text, nullable=True),
                Column("openai_key", Text, nullable=True),
            )
            self.metadata.create_all(self.engine)
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            log_event(f"[storage] Initialisierung fehlgeschlagen: {exc}")
            raise StorageError(message) from exc

    def get_ad_by_uuid(self, ad_uuid: str) -> Anzeige | None:
        """Lädt eine einzelne Anzeige anhand der UUID."""
        try:
            with self.engine.begin() as conn:
                row = conn.execute(
                    select(self.ads.c.ad_json).where(self.ads.c.id == ad_uuid)
                ).first()
                if row:
                    ad_dict = json.loads(row[0])
                    return Anzeige.model_validate(ad_dict)
                return None
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(f"Fehler beim Laden der Anzeige: {message}") from exc
        except Exception as exc:
            log_event(f"[storage] Fehler beim Validieren von {ad_uuid}: {exc}")
            return None

    def save_ad_data(self, ad: Anzeige, images: Dict[str, Dict]) -> str:
        """Speichert oder aktualisiert eine Anzeige inkl. Bilder."""
        # Ensure status is set to "Entwurf" if not already set (for new ads)
        if not hasattr(ad, 'status') or ad.status is None:
            ad = ad.model_copy(update={"status": "Entwurf"})
        
        # Check if status changed to "Online" and update last_online_at
        existing_ad = self.get_ad_by_uuid(ad.uuid)
        if existing_ad:
            old_status = getattr(existing_ad, 'status', None)
            new_status = ad.status
            # If status changed to "Online", update last_online_at
            if new_status == "Online" and old_status != "Online":
                metadata = ad.metadata or {}
                metadata['last_online_at'] = datetime.now(timezone.utc).isoformat()
                ad = ad.model_copy(update={"metadata": metadata})
        
        ad_json = ad.model_dump()
        try:
            with self.engine.begin() as conn:
                existing = conn.execute(
                    select(self.ads.c.id).where(self.ads.c.id == ad.uuid)
                ).first()
                if existing:
                    conn.execute(
                        self.ads.update()
                        .where(self.ads.c.id == ad.uuid)
                        .values(ad_json=json.dumps(ad_json))
                    )
                else:
                    conn.execute(
                        self.ads.insert().values(
                            id=ad.uuid, ad_json=json.dumps(ad_json)
                        )
                    )

                if images:
                    conn.execute(
                        delete(self.ad_images).where(self.ad_images.c.ad_id == ad.uuid)
                    )
                    for image in images.values():
                        filename = image["filename"]
                        image_bytes = base64.b64decode(image["data"])
                        thumbnail_bytes = (
                            base64.b64decode(image.get("thumbnail", ""))
                            if image.get("thumbnail")
                            else None
                        )
                        conn.execute(
                            self.ad_images.insert().values(
                                ad_id=ad.uuid,
                                filename=filename,
                                image=image_bytes,
                                thumbnail=thumbnail_bytes,
                            )
                        )
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(f"Fehler beim Speichern: {message}") from exc

        return f"db://ads/{ad.uuid}"

    def list_all_ads(
        self, user_owner: str | None = None
    ) -> List[Tuple[Anzeige, Dict[str, str], Dict[str, str]]]:
        """Lädt alle Anzeigen inklusive Base64-Bildern und Thumbnails.

        Returns:
            List[Tuple[Anzeige, Dict[str, str], Dict[str, str]]]:
            Liste von (Anzeige, processed_images_dict, thumbnails_dict)

        Optionaler Filter:
        - Wenn `user_owner` gesetzt ist, werden nur Anzeigen mit passendem `user_owner`
          oder ohne gesetzten `user_owner` geladen.
        - Wenn `user_owner` leer/None ist, werden alle Anzeigen geladen.
        """
        try:
            with self.engine.begin() as conn:
                ads_query = select(self.ads.c.id, self.ads.c.ad_json)
                used_db_filter = False
                # Versuche, DB-seitige Filterung anhand des JSON-Feldes anzuwenden
                if user_owner:
                    dialect = self.engine.dialect.name
                    if dialect.startswith("mysql"):
                        ads_query = ads_query.where(
                            text(
                                "JSON_UNQUOTE(JSON_EXTRACT(ad_json, '$.user_owner')) = :u "
                                "OR JSON_EXTRACT(ad_json, '$.user_owner') IS NULL"
                            )
                        ).params(u=user_owner)
                        used_db_filter = True
                    elif dialect == "sqlite":
                        ads_query = ads_query.where(
                            text(
                                "(json_extract(ad_json, '$.user_owner') = :u "
                                "OR json_extract(ad_json, '$.user_owner') IS NULL)"
                            )
                        ).params(u=user_owner)
                        used_db_filter = True

                ads = conn.execute(ads_query).all()
                images = conn.execute(
                    select(
                        self.ad_images.c.ad_id,
                        self.ad_images.c.filename,
                        self.ad_images.c.image,
                        self.ad_images.c.thumbnail,
                    )
                ).all()
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(f"Fehler beim Laden: {message}") from exc

        image_map: Dict[str, Dict[str, str]] = {}
        thumbnail_map: Dict[str, Dict[str, str]] = {}
        for ad_id, filename, blob, thumb_blob in images:
            image_map.setdefault(ad_id, {})[filename] = base64.b64encode(blob).decode(
                "utf-8"
            )
            if thumb_blob:
                thumbnail_map.setdefault(ad_id, {})[filename] = base64.b64encode(
                    thumb_blob
                ).decode("utf-8")

        result: List[Tuple[Anzeige, Dict[str, str], Dict[str, str]]] = []
        for ad_id, payload in ads:
            try:
                ad_dict = json.loads(payload)
                ad_obj = Anzeige.model_validate(ad_dict)
                result.append(
                    (ad_obj, image_map.get(ad_id, {}), thumbnail_map.get(ad_id, {}))
                )
            except Exception as exc:
                log_event(f"[storage] Fehler beim Validieren von {ad_id}: {exc}")
                continue

        # Fallback-Filterung in Python, falls keine DB-Filterung möglich war
        if user_owner:
            result = [
                (ad, imgs, thumbs)
                for ad, imgs, thumbs in result
                if (ad.user_owner is None) or (ad.user_owner == user_owner)
            ]

        result.sort(
            key=lambda entry: (entry[0].metadata or {}).get("created_at", ""),
            reverse=True,
        )
        return result

    def ping(self) -> tuple[bool, str]:
        """Prüft, ob die Datenbank erreichbar ist."""
        try:
            with self.engine.connect() as conn:
                conn.execute(text("SELECT 1"))
            return True, "Verbindung hergestellt."
        except SQLAlchemyError as exc:
            summary = _summarize_db_error(exc)
            log_event(f"[storage] DB-Ping fehlgeschlagen: {exc}")
            return False, summary

    def delete_ad(self, ad_uuid: str) -> None:
        try:
            with self.engine.begin() as conn:
                deleted = conn.execute(
                    delete(self.ads).where(self.ads.c.id == ad_uuid)
                ).rowcount
                conn.execute(
                    delete(self.ad_images).where(self.ad_images.c.ad_id == ad_uuid)
                )
            if not deleted:
                raise StorageError("Anzeige nicht gefunden.")
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(f"Fehler beim Löschen: {message}") from exc

    # -------- Nutzer-Funktionen --------
    def list_users(self) -> List[str]:
        """Liefert alle bekannten Nutzer (Email als Schlüssel)."""
        try:
            with self.engine.begin() as conn:
                rows = conn.execute(select(self.users.c.email)).all()
            return [row[0] for row in rows]
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(f"Fehler beim Laden der Nutzer: {message}") from exc

    def add_user(self, email: str) -> None:
        """Fügt einen neuen Nutzer hinzu, falls noch nicht vorhanden (Email als PK)."""
        email = (email or "").strip().lower()
        if not email:
            return
        try:
            with self.engine.begin() as conn:
                existing = conn.execute(
                    select(self.users.c.email).where(self.users.c.email == email)
                ).first()
                if existing:
                    return
                conn.execute(self.users.insert().values(email=email))
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(f"Fehler beim Speichern des Nutzers: {message}") from exc

    def get_user_appendix(self, email: str) -> str | None:
        """Liest den gespeicherten Appendix für einen Nutzer."""
        email = (email or "").strip().lower()
        if not email:
            return None
        try:
            with self.engine.begin() as conn:
                row = conn.execute(
                    select(self.users.c.appendix).where(self.users.c.email == email)
                ).first()
            if not row:
                return None
            return row[0]
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(
                f"Fehler beim Laden des Appendix für Nutzer '{email}': {message}"
            ) from exc

    def set_user_appendix(self, email: str, appendix: str | None) -> None:
        """Speichert oder aktualisiert den Appendix eines Nutzers."""
        email = (email or "").strip().lower()
        if not email:
            return

        cleaned_appendix = (appendix or "").strip() or None
        try:
            with self.engine.begin() as conn:
                existing = conn.execute(
                    select(self.users.c.email).where(self.users.c.email == email)
                ).first()
                if existing:
                    conn.execute(
                        self.users.update()
                        .where(self.users.c.email == email)
                        .values(appendix=cleaned_appendix)
                    )
                else:
                    conn.execute(
                        self.users.insert().values(
                            email=email, appendix=cleaned_appendix
                        )
                    )
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(
                f"Fehler beim Speichern des Appendix für Nutzer '{email}': {message}"
            ) from exc

    # -------- Auth-Credentials-Funktionen --------
    def get_all_credentials(self) -> dict:
        """
        Lädt alle User-Credentials aus der DB und konvertiert sie
        in das Format, das Streamlit-Authenticator erwartet.

        Rückgabe-Format:
        {
            "usernames": {
                "max": {
                    "email": "max@example.com",
                    "name": "Max Mustermann",
                    "password": "$2b$12$..."  # bcrypt hash
                },
                "lisa": {...}
            }
        }
        """
        try:
            with self.engine.begin() as conn:
                rows = conn.execute(
                    select(
                        self.users.c.email,
                        self.users.c.password_hash,
                    )
                ).all()

            credentials = {"usernames": {}}
            for email, password_hash in rows:
                # Nur User mit gesetztem Password-Hash sind Auth-User
                if password_hash:
                    credentials["usernames"][email] = {
                        "email": email or "",
                        "name": email or "",
                        "password": password_hash,
                    }

            return credentials
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(f"Fehler beim Laden der Credentials: {message}") from exc

    def save_user_credentials(self, email: str, password_hash: str) -> None:
        """
        Speichert oder aktualisiert die Auth-Credentials eines Users.
        Email dient als Primärschlüssel (Login-Name).
        """
        email = (email or "").strip().lower()

        if not email or not password_hash:
            raise ValueError("Email und Password-Hash sind erforderlich.")

        try:
            with self.engine.begin() as conn:
                existing = conn.execute(
                    select(self.users.c.email).where(self.users.c.email == email)
                ).first()

                if existing:
                    conn.execute(
                        self.users.update()
                        .where(self.users.c.email == email)
                        .values(password_hash=password_hash)
                    )
                else:
                    conn.execute(
                        self.users.insert().values(
                            email=email,
                            password_hash=password_hash,
                        )
                    )
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(
                f"Fehler beim Speichern der Credentials für '{email}': {message}"
            ) from exc

    def get_user_openai_key(self, email: str) -> str | None:
        """Lädt den gespeicherten OpenAI-API-Key für einen User."""
        email = (email or "").strip().lower()
        if not email:
            return None
        try:
            with self.engine.begin() as conn:
                row = conn.execute(
                    select(self.users.c.openai_key).where(self.users.c.email == email)
                ).first()
            return row[0] if row else None
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(
                f"Fehler beim Laden des API-Keys für '{email}': {message}"
            ) from exc

    def set_user_openai_key(self, email: str, api_key: str | None) -> None:
        """Speichert oder aktualisiert den OpenAI-API-Key eines Users."""
        email = (email or "").strip().lower()
        if not email:
            return

        cleaned_key = (api_key or "").strip() or None
        try:
            with self.engine.begin() as conn:
                existing = conn.execute(
                    select(self.users.c.email).where(self.users.c.email == email)
                ).first()
                if existing:
                    conn.execute(
                        self.users.update()
                        .where(self.users.c.email == email)
                        .values(openai_key=cleaned_key)
                    )
                else:
                    conn.execute(
                        self.users.insert().values(email=email, openai_key=cleaned_key)
                    )
        except SQLAlchemyError as exc:
            message = _summarize_db_error(exc)
            raise StorageError(
                f"Fehler beim Speichern des API-Keys für '{email}': {message}"
            ) from exc
