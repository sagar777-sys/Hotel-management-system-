"""Hotel Management System - Streamlit front-end (Python) + PHP back-end + HTML/CSS views."""
import json
import os
import shutil
import subprocess
from datetime import date, timedelta

import streamlit as st
import streamlit.components.v1 as components

BASE = os.path.dirname(os.path.abspath(__file__))
CLI = os.path.join(BASE, "cli.php")

st.set_page_config(page_title="Grand Stay Hotel", page_icon="🏨", layout="wide")


# ---------- PHP bridge ----------
def run_php(action, data=None):
    proc = subprocess.run(
        ["php", CLI, action],
        input=json.dumps(data or {}),
        capture_output=True,
        text=True,
        timeout=30,
        cwd=BASE,
    )
    if proc.returncode != 0 and not proc.stdout:
        return json.dumps({"ok": False, "message": proc.stderr.strip() or "PHP error"})
    return proc.stdout


def call(action, data=None):
    try:
        return json.loads(run_php(action, data))
    except Exception as e:  # noqa: BLE001
        return {"ok": False, "message": f"Backend error: {e}"}


def show_html(action, data=None, height=480):
    components.html(run_php(action, data), height=height, scrolling=True)


def flash(res):
    st.session_state["flash"] = (res.get("ok", False), res.get("message", ""))
    st.rerun()


# ---------- PHP availability check ----------
if shutil.which("php") is None:
    st.error(
        "PHP is not installed on this server. Make sure `packages.txt` "
        "(php-cli, php-sqlite3) is in the root of your repository, then reboot the app."
    )
    st.stop()

# ---------- Header ----------
st.title("🏨 Grand Stay Hotel - Management System")
st.caption("Python (Streamlit) UI  •  PHP + SQLite backend  •  HTML/CSS views")

if "flash" in st.session_state:
    ok, msg = st.session_state.pop("flash")
    (st.success if ok else st.error)(msg)

page = st.sidebar.radio(
    "Navigation",
    ["📊 Dashboard", "🛏️ Rooms", "👤 Guests", "📅 Bookings", "🧾 Invoice"],
)
st.sidebar.info("Demo data is stored in a temporary SQLite database and may reset when the app restarts.")

rooms = call("list_rooms").get("rooms", [])
guests = call("list_guests").get("guests", [])
bookings = call("list_bookings").get("bookings", [])

# ---------- Dashboard ----------
if page.endswith("Dashboard"):
    show_html("view_dashboard", height=560)

# ---------- Rooms ----------
elif page.endswith("Rooms"):
    show_html("view_rooms", height=420)
    c1, c2 = st.columns(2)
    with c1:
        with st.form("add_room", clear_on_submit=True):
            st.subheader("Add Room")
            room_no = st.text_input("Room number")
            rtype = st.selectbox("Type", ["Standard", "Deluxe", "Suite", "Presidential"])
            price = st.number_input("Price per night (Rs)", min_value=0.0, step=100.0, value=2000.0)
            if st.form_submit_button("Add Room"):
                flash(call("add_room", {"room_no": room_no, "type": rtype, "price": price}))
    with c2:
        with st.form("del_room"):
            st.subheader("Delete Room")
            opts = {f"{r['room_no']} - {r['type']}": r["id"] for r in rooms}
            sel = st.selectbox("Select room", list(opts.keys())) if opts else None
            if st.form_submit_button("Delete Room") and sel:
                flash(call("delete_room", {"room_id": opts[sel]}))

# ---------- Guests ----------
elif page.endswith("Guests"):
    show_html("view_guests", height=380)
    with st.form("add_guest", clear_on_submit=True):
        st.subheader("Register Guest")
        a, b = st.columns(2)
        name = a.text_input("Full name")
        phone = b.text_input("Phone")
        email = a.text_input("Email")
        id_proof = b.text_input("ID proof (Aadhaar / Passport no.)")
        if st.form_submit_button("Register Guest"):
            flash(call("add_guest", {"name": name, "phone": phone, "email": email, "id_proof": id_proof}))

# ---------- Bookings ----------
elif page.endswith("Bookings"):
    show_html("view_bookings", height=380)
    c1, c2 = st.columns(2)
    with c1:
        with st.form("new_booking"):
            st.subheader("New Booking")
            g_opts = {f"{g['name']} (#{g['id']})": g["id"] for g in guests}
            r_opts = {f"{r['room_no']} - {r['type']} (Rs {r['price']:.0f}/night)": r["id"] for r in rooms}
            g_sel = st.selectbox("Guest", list(g_opts.keys())) if g_opts else st.caption("Register a guest first.")
            r_sel = st.selectbox("Room", list(r_opts.keys())) if r_opts else None
            d1 = st.date_input("Check-in", date.today())
            d2 = st.date_input("Check-out", date.today() + timedelta(days=1))
            if st.form_submit_button("Book Room") and g_opts and r_opts:
                flash(call("book", {
                    "guest_id": g_opts[g_sel], "room_id": r_opts[r_sel],
                    "check_in": d1.isoformat(), "check_out": d2.isoformat(),
                }))
    with c2:
        st.subheader("Manage Booking")
        b_opts = {f"#{b['id']} - {b['guest']} - Room {b['room_no']} [{b['status']}]": b["id"] for b in bookings}
        if b_opts:
            b_sel = st.selectbox("Select booking", list(b_opts.keys()))
            bid = b_opts[b_sel]
            x, y, z = st.columns(3)
            if x.button("Check-in", use_container_width=True):
                flash(call("check_in", {"booking_id": bid}))
            if y.button("Check-out", use_container_width=True):
                flash(call("check_out", {"booking_id": bid}))
            if z.button("Cancel", use_container_width=True):
                flash(call("cancel", {"booking_id": bid}))
        else:
            st.info("No bookings yet.")

# ---------- Invoice ----------
elif page.endswith("Invoice"):
    st.subheader("Generate Invoice")
    b_opts = {f"#{b['id']} - {b['guest']} - Room {b['room_no']} [{b['status']}]": b["id"] for b in bookings}
    if b_opts:
        sel = st.selectbox("Select booking", list(b_opts.keys()))
        html = run_php("view_invoice", {"booking_id": b_opts[sel]})
        components.html(html, height=520, scrolling=True)
        st.download_button("⬇️ Download invoice (HTML)", html, file_name=f"invoice_{b_opts[sel]}.html", mime="text/html")
    else:
        st.info("Create a booking to generate an invoice.")
