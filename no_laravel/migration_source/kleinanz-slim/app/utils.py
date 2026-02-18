"""
Hilfsfunktionen für Logging & UI.
"""

import logging
import streamlit as st

logging.basicConfig(level=logging.INFO)


def log_event(message: str) -> None:
    logging.info(message)



def render_error_with_details(detail: str, title: str = "Fehlerdetails") -> None:
    """
    Zeigt einen kleinen Info-Indikator mit Expander für Debug-Details.
    """
    if not detail:
        return

    with st.expander(title):
        st.code(detail, language="text")
