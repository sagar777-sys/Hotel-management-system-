import csv
import html as htmllib
import io
import json
import os
import shutil
import subprocess
from datetime import date, datetime, timedelta

import streamlit as st

BASE = os.path.dirname(os.path.abspath(__file__))
CLI = os.path.join(BASE, "cli.php")

st.set_page_config(
    page_title="Grand Stay Hotel & Resort",
    page_icon="🏨",
    layout="wide",
    initial_sidebar_state="expanded",
)

ROOM_TYPES = ["Standard", "Deluxe", "Suite", "Presidential"]
STAFF_PAGES = ["Dashboard", "Rooms & Suites", "Guests", "Reservations", "Billing", "Reports"]
ICONS = {
    "Home": "🏠", "Staff Login": "🔐", "Dashboard": "📊", "Rooms & Suites": "🛏️",
    "Guests": "👥", "Reservations": "📅", "Billing": "🧾", "Reports": "📈",
}


def today():
    try:
        from zoneinfo import ZoneInfo
        return datetime.now(ZoneInfo("Asia/Kolkata")).date()
    except Exception:  # noqa: BLE001
        return date.today()


# ---------- backend bridge ----------
def run_backend(action, data=None):
    proc = subprocess.run(
        ["php", CLI, action],
        input=json.dumps(data or {}),
        capture_output=True,
        text=True,
        timeout=30,
        cwd=BASE,
    )
    if not proc.stdout:
        print(proc.stderr)
        return json.dumps({"ok": False, "message": "Something went wrong. Please try again."})
    return proc.stdout


def call(action, data=None):
    try:
        return json.loads(run_backend(action, data))
    except Exception:  # noqa: BLE001
        return {"ok": False, "message": "Something went wrong. Please try again."}


def render(action, data=None):
    markup = run_backend(action, data)
    markup = " ".join(line.strip() for line in markup.splitlines() if line.strip())
    st.markdown(markup, unsafe_allow_html=True)


def hero(title, subtitle, image="hero", height=260):
    render("view_hero", {"title": title, "subtitle": subtitle, "image": image, "height": height})


def heading(text):
    st.markdown(f"<div class='hms-h'>{text}</div>", unsafe_allow_html=True)


def section(title, lead=""):
    lead_html = f"<div class='hms-lead'>{lead}</div>" if lead else ""
    st.markdown(f"<div class='hms-title2'>{title}</div>{lead_html}", unsafe_allow_html=True)


def flash(res):
    st.session_state["flash"] = (res.get("ok", False), res.get("message", ""))
    st.rerun()


# ---------- styling ----------
with open(os.path.join(BASE, "style.css"), encoding="utf-8") as f:
    st.markdown(f"<style>{f.read()}</style>", unsafe_allow_html=True)

if shutil.which("php") is None:
    st.error("The service is temporarily unavailable. Please try again shortly.")
    st.stop()


# ---------- authentication ----------
def do_login():
    res = call("login", {
        "username": st.session_state.get("lu", ""),
        "password": st.session_state.get("lp", ""),
    })
    if res.get("ok"):
        st.session_state["auth"] = True
        st.session_state["nav"] = "Dashboard"
        st.session_state["flash"] = (True, "Welcome back! You are signed in as Administrator.")
    else:
        st.session_state["flash"] = (False, "Incorrect username or password.")


def do_logout():
    st.session_state["auth"] = False
    st.session_state["nav"] = "Home"


authed = st.session_state.get("auth", False)
options = ["Home"] + (STAFF_PAGES if authed else ["Staff Login"])
if st.session_state.get("nav") not in options:
    st.session_state["nav"] = "Home"

# ---------- sidebar ----------
st.sidebar.markdown(
    "<div class='hms-brand'>GRAND STAY<small>HOTEL &amp; RESORT</small></div>",
    unsafe_allow_html=True,
)
page = st.sidebar.radio(
    "Menu", options, key="nav",
    format_func=lambda x: f"{ICONS[x]}  {x}",
    label_visibility="collapsed",
)
if authed:
    st.sidebar.markdown("<div class='hms-user'>Signed in as <b>Administrator</b></div>", unsafe_allow_html=True)
    st.sidebar.button("Sign out", on_click=do_logout, use_container_width=True)
st.sidebar.markdown(
    "<div class='hms-side-foot'>Front desk open 24 hours<br>+91 22 5555 0100</div>",
    unsafe_allow_html=True,
)

if "flash" in st.session_state:
    ok, msg = st.session_state.pop("flash")
    (st.success if ok else st.error)(msg)
if st.session_state.pop("celebrate", False):
    st.balloons()

# =====================================================================
# HOME (public)
# =====================================================================
if page == "Home":
    hero("Experience Luxury,\nFeel at Home".replace("\n", " "),
         "Elegant rooms, world-class dining and warm hospitality in the heart of the city.", "hero", 380)

    section("Plan Your Stay", "Choose your dates and we will show you the rooms that are free.")
    with st.form("plan"):
        c1, c2, c3, c4 = st.columns(4)
        d1 = c1.date_input("Check-in", today(), min_value=today(), key="pl_in")
        d2 = c2.date_input("Check-out", today() + timedelta(days=1), min_value=today(), key="pl_out")
        c3.number_input("Guests", min_value=1, max_value=6, value=2, key="pl_guests")
        rtype = c4.selectbox("Room type", ["Any"] + ROOM_TYPES, key="pl_type")
        go = st.form_submit_button("Check Availability")
    if go:
        res = call("available_rooms", {"check_in": d1.isoformat(), "check_out": d2.isoformat(), "type": rtype})
        if res.get("ok"):
            st.session_state["search"] = res
        else:
            st.session_state["search"] = None
            st.error(res.get("message", "Please check your dates."))

    found = st.session_state.get("search")
    if found:
        render("view_available", found)
        if found.get("rooms"):
            heading("Reserve Your Room")
            with st.form("reserve"):
                opts = {
                    f"Room {r['room_no']} - {r['type']} - ₹{r['price']:.0f}/night": r["id"]
                    for r in found["rooms"]
                }
                choice = st.selectbox("Choose a room", list(opts.keys()))
                a, b = st.columns(2)
                name = a.text_input("Full name")
                phone = b.text_input("Phone number")
                email = a.text_input("Email address")
                if st.form_submit_button("Reserve Now"):
                    res = call("online_booking", {
                        "name": name, "phone": phone, "email": email,
                        "room_id": opts[choice],
                        "check_in": found["check_in"], "check_out": found["check_out"],
                    })
                    if res.get("ok"):
                        st.session_state.pop("search", None)
                        st.session_state["celebrate"] = True
                    flash(res)

    section("Rooms & Suites", "Four categories, each designed for comfort.")
    render("view_home_rooms")

    section("Hotel Facilities", "Everything you need for a perfect stay.")
    render("view_facilities")

    section("A Glimpse of Grand Stay")
    render("view_gallery")

    section("What Our Guests Say")
    render("view_reviews")

    render("view_footer")

# =====================================================================
# STAFF LOGIN
# =====================================================================
elif page == "Staff Login":
    hero("Staff Portal", "Sign in to manage reservations, rooms and billing.", "night", 240)
    _, mid, _ = st.columns([1, 1.4, 1])
    with mid:
        with st.form("login"):
            heading("Sign in")
            st.text_input("Username", key="lu")
            st.text_input("Password", type="password", key="lp")
            st.form_submit_button("Sign In", on_click=do_login)
        st.caption("Demo access - Username: admin  |  Password: Hotel@123")

# =====================================================================
# DASHBOARD
# =====================================================================
elif page == "Dashboard":
    hero("Front Desk Dashboard", "Everything happening at the hotel, at a glance.", "lobby", 220)
    render("view_dashboard")

# =====================================================================
# ROOMS
# =====================================================================
elif page == "Rooms & Suites":
    hero("Rooms & Suites", "Comfort, elegance and views to remember.", "Suite", 220)
    rooms = call("list_rooms").get("rooms", [])
    f1, f2 = st.columns(2)
    type_filter = f1.selectbox("Room type", ["All"] + ROOM_TYPES, key="rf_type")
    status_filter = f2.selectbox("Status", ["All", "Available", "Occupied", "Cleaning", "Maintenance"], key="rf_status")
    render("view_rooms", {"type": type_filter, "status": status_filter})

    t_add, t_house, t_del = st.tabs(["Add Room", "Housekeeping", "Remove Room"])
    with t_add:
        with st.form("add_room", clear_on_submit=True):
            room_no = st.text_input("Room number")
            rtype = st.selectbox("Room type", ROOM_TYPES, key="ar_type")
            price = st.number_input("Price per night (₹)", min_value=0.0, step=100.0, value=2000.0)
            if st.form_submit_button("Add Room"):
                flash(call("add_room", {"room_no": room_no, "type": rtype, "price": price}))
    with t_house:
        with st.form("house"):
            opts = {f"Room {r['room_no']} - {r['type']} [{r['status']}]": r["id"] for r in rooms}
            sel = st.selectbox("Room", list(opts.keys())) if opts else None
            new_status = st.selectbox("Set status to", ["Available", "Cleaning", "Maintenance"])
            if st.form_submit_button("Update Status") and sel:
                flash(call("set_room_status", {"room_id": opts[sel], "status": new_status}))
    with t_del:
        with st.form("del_room"):
            opts = {f"Room {r['room_no']} - {r['type']}": r["id"] for r in rooms}
            sel = st.selectbox("Room", list(opts.keys()), key="dr_sel") if opts else None
            if st.form_submit_button("Remove Room") and sel:
                flash(call("delete_room", {"room_id": opts[sel]}))

# =====================================================================
# GUESTS
# =====================================================================
elif page == "Guests":
    hero("Our Guests", "Every guest is a story worth remembering.", "dining", 220)
    q = st.text_input("Search by name, phone or email", key="gq")
    render("view_guests", {"q": q})
    section("Register a Guest")
    with st.form("add_guest", clear_on_submit=True):
        a, b = st.columns(2)
        name = a.text_input("Full name")
        phone = b.text_input("Phone number")
        email = a.text_input("Email address")
        id_proof = b.text_input("ID proof (Aadhaar / Passport no.)")
        if st.form_submit_button("Register Guest"):
            flash(call("add_guest", {"name": name, "phone": phone, "email": email, "id_proof": id_proof}))

# =====================================================================
# RESERVATIONS
# =====================================================================
elif page == "Reservations":
    hero("Reservations", "Plan a stay, welcome a guest, settle the day.", "pool", 220)
    guests = call("list_guests").get("guests", [])
    bookings = call("list_bookings").get("bookings", [])
    t_new, t_manage, t_all = st.tabs(["New Reservation", "Check-in / Check-out / Billing", "All Reservations"])

    with t_new:
        c1, c2, c3 = st.columns(3)
        d1 = c1.date_input("Check-in", today(), min_value=today(), key="rs_in")
        d2 = c2.date_input("Check-out", today() + timedelta(days=1), min_value=today(), key="rs_out")
        rtype = c3.selectbox("Room type", ["Any"] + ROOM_TYPES, key="rs_type")
        avail = call("available_rooms", {"check_in": d1.isoformat(), "check_out": d2.isoformat(), "type": rtype})
        if not avail.get("ok"):
            st.error(avail.get("message", "Please check your dates."))
        else:
            render("view_available", avail)
            if avail["rooms"] and guests:
                with st.form("new_booking"):
                    g_opts = {f"{g['name']} (#{g['id']})": g["id"] for g in guests}
                    r_opts = {
                        f"Room {r['room_no']} - {r['type']} - ₹{r['price']:.0f}/night": r["id"]
                        for r in avail["rooms"]
                    }
                    g_sel = st.selectbox("Guest", list(g_opts.keys()))
                    r_sel = st.selectbox("Room", list(r_opts.keys()))
                    if st.form_submit_button("Confirm Reservation"):
                        flash(call("book", {
                            "guest_id": g_opts[g_sel], "room_id": r_opts[r_sel],
                            "check_in": d1.isoformat(), "check_out": d2.isoformat(),
                        }))
            elif avail["rooms"]:
                st.info("Register a guest first (Guests page) to confirm a reservation.")

    with t_manage:
        b_opts = {f"#{b['id']} - {b['guest']} - Room {b['room_no']} [{b['status']}]": b["id"] for b in bookings}
        if not b_opts:
            st.info("No reservations yet.")
        else:
            sel = st.selectbox("Select reservation", list(b_opts.keys()), key="mg_sel")
            bid = b_opts[sel]
            cur = next(x for x in bookings if x["id"] == bid)
            st.markdown(
                "<div class='hms-panel'><b>{g}</b> &middot; Room {r} &middot; {i} to {o} &nbsp; "
                "<span class='hms-badge {sc}'>{s}</span> <span class='hms-badge {pc}'>{p}</span></div>".format(
                    g=htmllib.escape(cur["guest"]), r=htmllib.escape(cur["room_no"]),
                    i=cur["check_in"], o=cur["check_out"],
                    sc=cur["status"].lower(), s=cur["status"],
                    pc=cur["payment"].lower(), p=cur["payment"],
                ),
                unsafe_allow_html=True,
            )
            x, y, z = st.columns(3)
            if x.button("Check-in", use_container_width=True):
                flash(call("check_in", {"booking_id": bid}))
            if y.button("Check-out", use_container_width=True):
                flash(call("check_out", {"booking_id": bid}))
            if z.button("Cancel", use_container_width=True):
                flash(call("cancel", {"booking_id": bid}))

            p1, p2 = st.columns(2)
            with p1:
                with st.form("charge"):
                    heading("Add a Service")
                    svc = st.selectbox("Service", ["Restaurant", "Room service", "Laundry", "Spa", "Airport transfer", "Minibar", "Other"])
                    amt = st.number_input("Amount (₹)", min_value=0.0, step=100.0, value=500.0)
                    if st.form_submit_button("Add to Bill"):
                        flash(call("add_charge", {"booking_id": bid, "description": svc, "amount": amt}))
            with p2:
                with st.form("pay"):
                    heading("Record Payment")
                    method = st.selectbox("Method", ["Cash", "Card", "UPI"])
                    if st.form_submit_button("Mark as Paid"):
                        flash(call("pay", {"booking_id": bid, "method": method}))

    with t_all:
        status_filter = st.selectbox("Filter by status", ["All", "Booked", "Checked-in", "Checked-out", "Cancelled"], key="al_status")
        render("view_bookings", {"status": status_filter})

# =====================================================================
# BILLING
# =====================================================================
elif page == "Billing":
    hero("Billing", "Clear, itemised invoices for every stay.", "spa", 220)
    bookings = call("list_bookings").get("bookings", [])
    b_opts = {f"#{b['id']} - {b['guest']} - Room {b['room_no']} [{b['status']}]": b["id"] for b in bookings}
    if b_opts:
        sel = st.selectbox("Select reservation", list(b_opts.keys()), key="bl_sel")
        render("view_invoice", {"booking_id": b_opts[sel]})
        st.download_button(
            "Download Invoice",
            run_backend("invoice_page", {"booking_id": b_opts[sel]}),
            file_name=f"invoice_{b_opts[sel]}.html",
            mime="text/html",
        )
    else:
        st.info("Create a reservation to generate an invoice.")

# =====================================================================
# REPORTS
# =====================================================================
elif page == "Reports":
    hero("Reports", "Revenue, services and reservation insights.", "night", 220)
    render("view_reports")
    section("Export Data")
    bookings = call("list_bookings").get("bookings", [])
    guests = call("list_guests").get("guests", [])

    buf = io.StringIO()
    w = csv.writer(buf)
    w.writerow(["ID", "Guest", "Room", "Type", "Check-in", "Check-out", "Nights", "Room charges", "Status", "Payment"])
    for b in bookings:
        w.writerow([b["id"], b["guest"], b["room_no"], b["room_type"], b["check_in"], b["check_out"],
                    b["nights"], b["total"], b["status"], b["payment"]])
    gbuf = io.StringIO()
    gw = csv.writer(gbuf)
    gw.writerow(["ID", "Name", "Phone", "Email", "ID proof"])
    for g in guests:
        gw.writerow([g["id"], g["name"], g["phone"], g["email"], g["id_proof"]])

    e1, e2 = st.columns(2)
    e1.download_button("Download Reservations (CSV)", buf.getvalue(), "reservations.csv", "text/csv", use_container_width=True)
    e2.download_button("Download Guests (CSV)", gbuf.getvalue(), "guests.csv", "text/csv", use_container_width=True)
