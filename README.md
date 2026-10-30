# Hotel Management System (Python + PHP + HTML + CSS)

Streamlit UI (app.py) -> PHP backend (cli.php, actions.php, db.php, views.php) -> SQLite, with HTML/CSS views (style.css).

## Deploy on Streamlit Community Cloud
1. Create a GitHub repo and upload ALL files directly in the repo root (no folders).
2. On share.streamlit.io choose the repo, branch `main`, main file `app.py`.
3. `packages.txt` installs PHP automatically; `requirements.txt` installs Streamlit.

## Features
Dashboard stats, room management, guest registration, booking with date-overlap check,
check-in / check-out / cancel, GST invoice with HTML download.

## Note
Data is stored in a temporary SQLite file, so it may reset when the app restarts.
Set the HOTEL_DB env variable to use a different database path.
