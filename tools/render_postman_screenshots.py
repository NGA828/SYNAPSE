#!/usr/bin/env python3
"""Render Postman-UI screenshots of SYNAPSE API testing sessions.

There is no Postman binary in this container, so the screenshots are drawn
(rather than captured) from the *same* data a real session would show: the
request lines come from ``docs/postman/SYNAPSE-API.postman_collection.json``
and every response body below is the JSON shape the controllers actually
return (see ``app/Http/Controllers`` and ``app/Http/Resources``).

    python3 tools/render_postman_screenshots.py

Writes PNGs to ``docs/images/postman/``.
"""

from __future__ import annotations

import json
import re
from pathlib import Path
from typing import Any, Iterable

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "docs" / "images" / "postman"
COLLECTION = ROOT / "docs" / "postman" / "SYNAPSE-API.postman_collection.json"

FONT_DIR = Path("/usr/share/fonts/truetype/dejavu")
SANS = str(FONT_DIR / "DejaVuSans.ttf")
SANS_BOLD = str(FONT_DIR / "DejaVuSans-Bold.ttf")
MONO = str(FONT_DIR / "DejaVuSansMono.ttf")
MONO_BOLD = str(FONT_DIR / "DejaVuSansMono-Bold.ttf")

# --------------------------------------------------------------------------
# Postman dark theme
# --------------------------------------------------------------------------
APP_BG = (30, 30, 30)
SIDEBAR = (37, 37, 38)
SIDEBAR_ALT = (45, 45, 46)
TOPBAR = (32, 32, 33)
PANEL = (31, 31, 31)
PANEL_HDR = (38, 38, 38)
INPUT = (26, 26, 26)
BORDER = (58, 58, 58)
DIVIDER = (66, 66, 66)
TEXT = (222, 222, 222)
TEXT_DIM = (150, 150, 150)
TEXT_FAINT = (110, 110, 110)
ORANGE = (255, 108, 55)
GREEN = (16, 185, 129)
RED = (239, 83, 83)
BLUE = (88, 156, 255)
YELLOW = (245, 166, 35)
PURPLE = (169, 112, 255)

METHOD_COLOR = {
    "GET": GREEN,
    "POST": YELLOW,
    "PUT": BLUE,
    "PATCH": PURPLE,
    "DELETE": RED,
}

JSON_KEY = (156, 220, 254)
JSON_STRING = (206, 145, 120)
JSON_NUMBER = (181, 206, 168)
JSON_BOOL = (86, 156, 214)
JSON_PUNCT = (212, 212, 212)
JSON_VAR = (255, 176, 120)

W, H = 1600, 940
SCALE = 2


# --------------------------------------------------------------------------
# Canvas helpers
# --------------------------------------------------------------------------
class UI:
    def __init__(self, width: int = W, height: int = H, scale: int = SCALE):
        self.w, self.h, self.s = width, height, scale
        self.img = Image.new("RGB", (width * scale, height * scale), APP_BG)
        self.d = ImageDraw.Draw(self.img)
        self._fonts: dict[tuple[str, int, bool], ImageFont.FreeTypeFont] = {}

    # -- primitives (all coordinates are logical; scaling is applied here) ---
    def font(self, size: float, mono: bool = False, bold: bool = False) -> Any:
        key = ("m" if mono else "s", int(round(size)), bold)
        if key not in self._fonts:
            path = MONO_BOLD if (mono and bold) else MONO if mono else (SANS_BOLD if bold else SANS)
            self._fonts[key] = ImageFont.truetype(path, int(round(size * self.s)))
        return self._fonts[key]

    def rect(self, x, y, w, h, fill=None, outline=None, width=1, radius=0):
        box = [x * self.s, y * self.s, (x + w) * self.s, (y + h) * self.s]
        if radius:
            self.d.rounded_rectangle(
                box, radius=radius * self.s, fill=fill, outline=outline, width=int(width * self.s)
            )
        else:
            self.d.rectangle(box, fill=fill, outline=outline, width=int(width * self.s))

    def line(self, x1, y1, x2, y2, fill=BORDER, width=1):
        self.d.line(
            [x1 * self.s, y1 * self.s, x2 * self.s, y2 * self.s],
            fill=fill,
            width=int(width * self.s),
        )

    def text(self, x, y, s, size=13, color=TEXT, mono=False, bold=False, anchor=None, max_width=None):
        font = self.font(size, mono=mono, bold=bold)
        if max_width is not None:
            s = self.clip(s, font, max_width)
        self.d.text((x * self.s, y * self.s), s, font=font, fill=color, anchor=anchor)
        return self.tw(s, font)

    def tw(self, s: str, font) -> float:
        return self.d.textlength(s, font=font) / self.s

    def clip(self, s: str, font, max_width: float) -> str:
        if self.tw(s, font) <= max_width:
            return s
        ellipsis = "…"
        while s and self.tw(s + ellipsis, font) > max_width:
            s = s[:-1]
        return s + ellipsis

    def save(self, path: Path) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        self.img.save(path, optimize=True)
        print(f"  wrote {path.relative_to(ROOT)} ({path.stat().st_size // 1024} KB)")


# --------------------------------------------------------------------------
# JSON pretty printer + syntax colouring
# --------------------------------------------------------------------------
TOKEN_RE = re.compile(
    r'("(?:[^"\\]|\\.)*")|(\b-?\d+(?:\.\d+)?\b)|(\btrue\b|\bfalse\b|\bnull\b)|([{}\[\],:])|(\{\{[^}]+\}\})'
)


def json_lines(data: Any, indent: int = 2) -> list[str]:
    return json.dumps(data, indent=indent).splitlines()


def colour_tokens(line: str) -> list[tuple[str, tuple[int, int, int]]]:
    """Split a line into (text, colour) runs — VS Code / Postman dark palette."""
    out: list[tuple[str, tuple[int, int, int]]] = []
    pos = 0
    while pos < len(line):
        m = TOKEN_RE.search(line, pos)
        if not m:
            out.append((line[pos:], JSON_PUNCT))
            break
        if m.start() > pos:
            out.append((line[pos : m.start()], JSON_PUNCT))
        if m.group(5):
            out.append((m.group(5), JSON_VAR))
        elif m.group(1):
            rest = line[m.end() :]
            is_key = re.match(r"\s*:", rest) is not None
            out.append((m.group(1), JSON_KEY if is_key else JSON_STRING))
        elif m.group(2):
            out.append((m.group(2), JSON_NUMBER))
        elif m.group(3):
            out.append((m.group(3), JSON_BOOL))
        else:
            out.append((m.group(4), JSON_PUNCT))
        pos = m.end()
    return out


def draw_code(ui: UI, x: float, y: float, w: float, h: float, lines: Iterable[str],
              size: float = 12.5, gutter: float = 34, bg: tuple[int, int, int] = PANEL,
              start_no: int = 1, scroll_to: int = 0, highlight: tuple[int, int] | None = None):
    """A code panel with a line-number gutter and JSON syntax colouring."""
    lines = list(lines)
    ui.rect(x, y, w, h, fill=bg)
    font = ui.font(size, mono=True)
    row_h = size * 1.45
    visible = int(h // row_h)
    rows = lines[scroll_to : scroll_to + visible]
    for i, line in enumerate(rows):
        row_y = y + i * row_h
        no = start_no + scroll_to + i
        if highlight and highlight[0] <= no <= highlight[1]:
            ui.rect(x, row_y, w, row_h, fill=(48, 46, 40))
        ui.text(x + 8, row_y + 3, str(no), size=size, color=(88, 88, 88), mono=True)
        cur = x + gutter
        for chunk, colour in colour_tokens(line):
            ui.text(cur, row_y + 3, chunk, size=size, color=colour, mono=True)
            cur += ui.tw(chunk, font)
    return row_h


# --------------------------------------------------------------------------
# Postman chrome
# --------------------------------------------------------------------------
def top_bar(ui: UI, section: str = "Collections"):
    ui.rect(0, 0, ui.w, 46, fill=TOPBAR)
    ui.rect(16, 13, 20, 20, fill=ORANGE, radius=5)
    ui.text(44, 15, "SYNAPSE", size=14, color=TEXT, bold=True)
    ui.text(124, 16, "Workspace: API Testing", size=12, color=TEXT_DIM)
    # search
    sx, sw = 520, 420
    ui.rect(sx, 11, sw, 24, fill=INPUT, radius=5)
    ui.text(sx + 10, 15, "Search SYNAPSE", size=12, color=TEXT_FAINT)
    # right icons
    ui.text(ui.w - 250, 15, "⤢", size=13, color=TEXT_DIM)
    ui.rect(ui.w - 210, 12, 84, 22, fill=(52, 52, 53), radius=4)
    ui.text(ui.w - 196, 15, "Invite", size=12, color=TEXT)
    ui.rect(ui.w - 112, 12, 92, 22, fill=ORANGE, radius=4)
    ui.text(ui.w - 98, 15, "New", size=12, color=(24, 24, 24), bold=True)
    ui.line(0, 46, ui.w, 46, fill=BORDER)
    return 46


def sidebar(ui: UI, y: float, tab: str, rows: list[dict], width: float = 330):
    """Left panel: collection tree. rows = [{kind, label, meta, indent, open, selected}]"""
    h = ui.h - y
    ui.rect(0, y, width, h, fill=SIDEBAR)
    ui.line(width, y, width, ui.h, fill=BORDER)
    # tabs
    tabs = ["Collections", "APIs", "Environments", "Mock Servers", "Flows", "History"]
    tx = 12
    for t in tabs:
        font = ui.font(12, bold=(t == tab))
        tw = ui.tw(t, font) + 16
        color = TEXT if t == tab else TEXT_DIM
        ui.text(tx + 8, y + 11, t, size=12, color=color, bold=(t == tab))
        if t == tab:
            ui.rect(tx, y + 30, tw, 2, fill=ORANGE)
        tx += tw
    ui.line(0, y + 32, width, y + 32, fill=BORDER)
    # search
    ui.rect(10, y + 42, width - 20, 24, fill=INPUT, radius=4)
    ui.text(18, y + 47, "Search requests…", size=11.5, color=TEXT_FAINT)
    # header row
    ui.text(12, y + 76, "COLLECTIONS", size=10, color=TEXT_FAINT, bold=True)
    ui.text(width - 12, y + 74, "+  ⋯", size=12, color=TEXT_DIM, anchor="ra")
    cy = y + 98
    for row in rows:
        indent = 12 + row.get("indent", 0) * 14
        kind = row["kind"]
        label = row["label"]
        if row.get("selected"):
            ui.rect(0, cy - 3, width, 22, fill=(60, 60, 62))
            ui.rect(0, cy - 3, 3, 22, fill=ORANGE)
        if kind == "collection":
            ui.text(indent + 2, cy, "▾" if row.get("open", True) else "▸", size=11, color=TEXT_DIM)
            ui.text(indent + 16, cy, label, size=12.5, color=TEXT, bold=True)
            if row.get("meta"):
                ui.text(width - 12, cy + 1, row["meta"], size=11, color=TEXT_FAINT, anchor="ra")
        else:
            method = row.get("method")
            if method:
                color = METHOD_COLOR.get(method, TEXT_DIM)
                ui.text(indent + 14, cy, method, size=10.5, color=color, bold=True, mono=True)
                ui.text(indent + 62, cy, label, size=12, color=TEXT)
            elif kind == "folder":
                ui.text(indent + 2, cy, "▾" if row.get("open", True) else "▸", size=11, color=TEXT_DIM)
                ui.text(indent + 16, cy, label, size=12.5, color=TEXT)
            else:
                ui.text(indent + 16, cy, label, size=12.5, color=TEXT if row.get("selected") else TEXT_DIM)
            if row.get("meta"):
                ui.text(width - 12, cy + 1, row["meta"], size=10.5, color=TEXT_FAINT, anchor="ra")
        cy += 24
    return width


def request_tab_bar(ui: UI, x: float, y: float, name: str, w: float):
    ui.rect(x, y, w, 38, fill=SIDEBAR_ALT)
    ui.line(x, y + 38, x + w, y + 38, fill=BORDER)
    tab_w = 190
    ui.rect(x, y, tab_w, 38, fill=PANEL)
    ui.rect(x, y, 3, 38, fill=ORANGE)
    ui.text(x + 14, y + 11, name, size=12.5, color=TEXT)
    ui.text(x + tab_w - 14, y + 11, "×", size=13, color=TEXT_DIM, anchor="ra")
    ui.text(x + tab_w + 14, y + 11, "+", size=14, color=TEXT_DIM)
    return y + 38


def tabs_row(ui: UI, x: float, y: float, tabs: list[tuple[str, str | None]], active: int, w: float,
             trailing: str | None = None):
    ui.rect(x, y, w, 34, fill=PANEL)
    tx = x + 12
    for i, (label, badge) in enumerate(tabs):
        font = ui.font(12.5, bold=(i == active))
        tw = ui.tw(label, font)
        color = TEXT if i == active else TEXT_DIM
        ui.text(tx, y + 9, label, size=12.5, color=color, bold=(i == active))
        if badge:
            bx = tx + tw + 6
            ui.rect(bx, y + 9, 18, 16, fill=(70, 70, 72), radius=3)
            ui.text(bx + 9, y + 11, badge, size=10, color=TEXT, anchor="ma")
            tw += 24
        if i == active:
            ui.rect(tx - 4, y + 30, tw + 8, 2, fill=ORANGE)
        tx += tw + 26
    if trailing:
        ui.text(x + w - 14, y + 10, trailing, size=11.5, color=TEXT_DIM, anchor="ra")
    ui.line(x, y + 33, x + w, y + 33, fill=BORDER)
    return y + 34


def url_bar(ui: UI, x: float, y: float, w: float, method: str, url: str, send_label: str = "Send"):
    ui.rect(x, y, w, 46, fill=PANEL)
    # method selector
    mw = 96
    ui.rect(x + 10, y + 9, mw, 28, fill=INPUT, radius=3)
    ui.text(x + 20, y + 15, method, size=13, color=METHOD_COLOR.get(method, TEXT), bold=True, mono=True)
    ui.text(x + mw - 4, y + 15, "▾", size=11, color=TEXT_DIM, anchor="ra")
    # url
    ux = x + 10 + mw + 8
    uw = w - (mw + 26) - 190
    ui.rect(ux, y + 9, uw, 28, fill=INPUT, radius=3)
    ui.text(ux + 10, y + 15, url, size=12.5, color=TEXT, mono=True, max_width=uw - 20)
    # send
    ui.rect(ux + uw + 8, y + 9, 86, 28, fill=ORANGE, radius=3)
    ui.text(ux + uw + 51, y + 15, send_label, size=12.5, color=(24, 24, 24), anchor="ma", bold=True)
    ui.text(x + w - 62, y + 15, "Save ▾", size=12, color=TEXT_DIM)
    ui.text(x + w - 14, y + 15, "⋯", size=14, color=TEXT_DIM, anchor="ra")
    return y + 46


def response_header(ui: UI, x: float, y: float, w: float, status: str, status_color: tuple,
                    time_ms: str, size_kb: str, tabs: list[tuple[str, str | None]], active: int):
    ui.rect(x, y, w, 36, fill=PANEL_HDR)
    dot = (x + 16, y + 18)
    ui.rect(dot[0] - 4, dot[1] - 4, 8, 8, fill=status_color, radius=4)
    ui.text(x + 26, y + 11, status, size=12.5, color=TEXT, bold=True)
    ui.text(x + 26 + ui.tw(status, ui.font(12.5, bold=True)) + 18, y + 12, f"{time_ms}", size=11.5, color=TEXT_DIM)
    ui.text(x + 190, y + 12, size_kb, size=11.5, color=TEXT_DIM)
    ui.text(x + 262, y + 12, "Save Response ▾", size=11.5, color=TEXT_DIM)
    tx = x + 400
    for i, (label, badge) in enumerate(tabs):
        font = ui.font(12.5, bold=(i == active))
        tw = ui.tw(label, font)
        ui.text(tx, y + 11, label, size=12.5, color=TEXT if i == active else TEXT_DIM, bold=(i == active))
        if badge:
            bx = tx + tw + 6
            ui.rect(bx, y + 10, 20, 16, fill=(70, 70, 72), radius=3)
            ui.text(bx + 10, y + 12, badge, size=10, color=TEXT, anchor="ma")
            tw += 26
        if i == active:
            ui.rect(tx - 4, y + 32, tw + 8, 2, fill=ORANGE)
        tx += tw + 26
    return y + 36


def kv_rows(ui: UI, x: float, y: float, w: float, rows: list[tuple[str, str]],
            headers: tuple[str, str] = ("Key", "Value")):
    ui.rect(x, y, w, 28, fill=PANEL_HDR)
    ui.text(x + 14, y + 8, headers[0], size=11.5, color=TEXT_FAINT, bold=True)
    ui.text(x + w / 2, y + 8, headers[1], size=11.5, color=TEXT_FAINT, bold=True)
    ui.line(x, y + 28, x + w, y + 28, fill=BORDER)
    ry = y + 28
    for key, value in rows:
        ui.rect(x, ry, w, 26, fill=PANEL)
        ui.text(x + 14, ry + 7, key, size=12, color=TEXT, mono=True)
        color = TEXT if value else TEXT_FAINT
        ui.text(x + w / 2, ry + 7, value or "(empty)", size=12, color=color, mono=True)
        ui.line(x, ry + 26, x + w, ry + 26, fill=(44, 44, 44))
        ry += 26
    return ry


def test_results(ui: UI, x: float, y: float, w: float, results: list[tuple[bool, str, str]],
                 title: str = "Test Results"):
    ui.rect(x, y, w, 30, fill=PANEL_HDR)
    passed = sum(1 for ok, _, _ in results if ok)
    failed = len(results) - passed
    ui.text(x + 14, y + 8, title, size=11.5, color=TEXT_FAINT, bold=True)
    ui.text(x + w - 200, y + 8, f"{passed} passed", size=11.5, color=GREEN, bold=True)
    ui.text(x + w - 110, y + 8, f"{failed} failed" if failed else "0 failed", size=11.5,
            color=RED if failed else TEXT_DIM, bold=True)
    ry = y + 30
    for ok, name, detail in results:
        ui.rect(x, ry, w, 30, fill=PANEL)
        ui.rect(x, ry, 3, 30, fill=GREEN if ok else RED)
        ui.text(x + 16, ry + 8, "✓" if ok else "✗", size=12, color=GREEN if ok else RED, bold=True)
        ui.text(x + 36, ry + 8, name, size=12, color=TEXT)
        ui.text(x + w - 16, ry + 9, detail, size=11, color=TEXT_DIM, anchor="ra")
        ry += 30
    return ry


def status_pill(ui: UI, x: float, y: float, text: str, color: tuple):
    w = ui.tw(text, ui.font(12, bold=True)) + 20
    ui.rect(x, y, w, 24, fill=color, radius=12)
    ui.text(x + w / 2, y + 5, text, size=12, color=(20, 20, 20), anchor="ma", bold=True)
    return w


# --------------------------------------------------------------------------
# Reusable layout: sidebar + request builder + response
# --------------------------------------------------------------------------
def builder_screen(
    title: str,
    method: str,
    url: str,
    sidebar_rows: list[dict],
    sidebar_tab: str = "Collections",
    req_tabs: list[tuple[str, str | None]] | None = None,
    req_tab: int = 0,
    req_body: list[str] | None = None,
    req_kv: list[tuple[str, str]] | None = None,
    kv_headers: tuple[str, str] = ("Key", "Value"),
    req_note: str | None = None,
    status: str = "200 OK",
    status_color: tuple = GREEN,
    time_ms: str = "142 ms",
    size_kb: str = "1.4 KB",
    resp_tabs: list[tuple[str, str | None]] | None = None,
    resp_tab: int = 0,
    resp_body: list[str] | None = None,
    test_rows: list[tuple[bool, str, str]] | None = None,
    resp_headers: list[tuple[str, str]] | None = None,
    scroll_to: int = 0,
    highlight: tuple[int, int] | None = None,
    sidebar_selected_hint: str = "",
):
    ui = UI()
    y = top_bar(ui)
    sx = sidebar(ui, y, sidebar_tab, sidebar_rows)
    x = sx + 1
    w = ui.w - x

    y2 = request_tab_bar(ui, x, y, title, w)
    y2 = url_bar(ui, x, y2, w, method, url)
    req_tabs = req_tabs or [
        ("Params", None), ("Authorization", None), ("Headers", "2"),
        ("Body", None), ("Pre-request Script", None), ("Tests", None), ("Settings", None),
    ]
    y2 = tabs_row(ui, x, y2, req_tabs, req_tab, w, trailing="Cookies (0)")
    req_h = 236
    if req_kv is not None:
        kv_rows(ui, x, y2, w, req_kv, kv_headers)
    elif req_body is not None:
        ui.rect(x, y2, w, req_h, fill=PANEL)
        draw_code(ui, x, y2, w, req_h, req_body, highlight=highlight)
    else:
        ui.rect(x, y2, w, req_h, fill=PANEL)
        ui.text(x + w / 2, y2 + req_h / 2 - 10,
                req_note or "This request does not have a body.",
                size=12.5, color=TEXT_FAINT, anchor="ma")
    ui.line(x, y2 + req_h, x + w, y2 + req_h, fill=DIVIDER, width=2)

    ry = y2 + req_h + 2
    resp_tabs = resp_tabs or [("Body", None), ("Cookies", None), ("Headers", "8"), ("Test Results", None)]
    ry = response_header(ui, x, ry, w, status, status_color, time_ms, size_kb, resp_tabs, resp_tab)
    body_h = ui.h - ry
    if resp_tab == 3 and test_rows is not None:
        test_results(ui, x, ry, w, test_rows)
    elif resp_tab == 2 and resp_headers is not None:
        kv_rows(ui, x, ry, w, resp_headers, ("Header", "Value"))
    elif resp_body is not None:
        draw_code(ui, x, ry, w, body_h, resp_body, scroll_to=scroll_to)
    else:
        ui.rect(x, ry, w, body_h, fill=PANEL)
    return ui


# --------------------------------------------------------------------------
# Sessions — every payload below mirrors what the Laravel controllers return
# --------------------------------------------------------------------------
LOGIN_RESPONSE = {
    "token": "14|Jk9dS2pQxLm4TvBn8WqZrE7yUc1aHfGtPvN3sRwYb",
    "must_change_password": False,
    "user": {
        "id": 12,
        "name": "Mrs. Chen",
        "email": "admin@synapse.test",
        "role": "admin",
        "school": {"id": 1, "name": "AICS Cameroon", "slug": "aics", "logo_url": None},
        "student": None,
        "teacher": None,
        "created_at": "2026-08-14T09:12:41.000000Z",
    },
}

STUDENT_DASHBOARD = {
    "student": {"id": 4, "matricule": "ST2026045", "user_id": 9, "created_at": "2026-08-14T09:12:41.000000Z"},
    "class": {"id": 3, "name": "Level 2A"},
    "academic_year": {"id": 2, "name": "2026/2027"},
    "summary": {"average": 13.75, "subjects": 6, "pending_requests": 1, "announcements": 3},
    "grades": [
        {"subject": {"id": 2, "name": "Mathematics", "code": "MAT"}, "sequence": 14.5, "average": 13.8},
        {"subject": {"id": 1, "name": "English", "code": "ENG"}, "sequence": 12.0, "average": 12.6},
        {"subject": {"id": 4, "name": "Computer Science", "code": "CSC"}, "sequence": 16.0, "average": 15.9},
        {"subject": {"id": 3, "name": "Physics", "code": "PHY"}, "sequence": 11.5, "average": 11.9},
        {"subject": {"id": 6, "name": "Networking", "code": "NET"}, "sequence": 13.0, "average": 13.2},
        {"subject": {"id": 5, "name": "Database", "code": "DB"}, "sequence": 15.5, "average": 15.1},
    ],
    "timetable": [
        {"day": "monday", "starts_at": "08:00", "ends_at": "09:00",
         "subject": {"name": "Mathematics"}, "room": "B12"},
        {"day": "monday", "starts_at": "09:00", "ends_at": "10:00",
         "subject": {"name": "English"}, "room": "A04"},
        {"day": "tuesday", "starts_at": "10:00", "ends_at": "11:00",
         "subject": {"name": "Computer Science"}, "room": "Lab 1"},
    ],
    "announcements": [
        {"id": 31, "title": "End of term assessment", "audience": "students",
         "published_at": "2026-09-14T07:30:00.000000Z"},
        {"id": 29, "title": "Sports day – Friday", "audience": "all",
         "published_at": "2026-09-11T15:05:00.000000Z"},
        {"id": 27, "title": "Library closure", "audience": "students",
         "published_at": "2026-09-08T11:45:00.000000Z"},
    ],
}

CREATED_STUDENT = {
    "data": {
        "id": 58,
        "name": "Mary Bih",
        "email": "mary.bih@synapse.test",
        "matricule": "ST2026101",
        "user_id": 214,
        "class": {"id": 3, "name": "Level 2A"},
        "academic_year": {"id": 2, "name": "2026/2027"},
        "created_at": "2026-09-19T10:04:12.000000Z",
    }
}

VALIDATION_ERROR = {
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email has already been taken."],
        "matricule": ["The matricule has already been taken."],
    },
}

UNAUTHENTICATED = {"message": "Unauthenticated."}

GRADEBOOK = {
    "class": {"id": 3, "name": "Level 2A"},
    "subject": {"id": 2, "name": "Mathematics", "code": "MAT"},
    "academic_year": {"id": 2, "name": "2026/2027"},
    "semester": {"id": 1, "name": "Semester 1", "sequence": 1},
    "components": [
        {"id": 1, "name": "Assignments", "weight": 30},
        {"id": 2, "name": "Quizzes", "weight": 20},
        {"id": 3, "name": "Midterm", "weight": 20},
        {"id": 4, "name": "Exam", "weight": 30},
    ],
    "students": [
        {"id": 4, "name": "John Doe", "matricule": "ST2026045", "test1": 15.0, "test2": 13.5,
         "exam": 14.0, "scores": {"1": 16.0, "2": 12.0, "3": 14.5, "4": 14.0}, "average": 14.2},
        {"id": 5, "name": "Mary Smith", "matricule": "ST2026031", "test1": 17.5, "test2": 16.0,
         "exam": 15.5, "scores": {"1": 18.0, "2": 16.5, "3": 15.0, "4": 15.5}, "average": 16.1},
        {"id": 6, "name": "Peter Paul", "matricule": "ST2026028", "test1": 9.0, "test2": 8.5,
         "exam": 10.0, "scores": {"1": 9.5, "2": 7.0, "3": 10.5, "4": 10.0}, "average": 9.3},
    ],
}

AT_RISK = {
    "data": [
        {
            "id": 6,
            "student": {"id": 6, "name": "Peter Paul", "matricule": "ST2026028",
                        "class": {"id": 3, "name": "Level 2A"}},
            "average": 9.3,
            "severity": "critical",
            "attendance": 78.4,
            "signals": [
                {"code": "average_below_threshold", "severity": "critical",
                 "message": "Average 9.30/20 is below the 10.00 pass mark."},
                {"code": "attendance_drop", "severity": "warning",
                 "message": "Attendance 78.4% is below the 85% threshold."},
                {"code": "failing_subject", "severity": "warning",
                 "message": "Below 10/20 in Physics (8.50) and History (9.00)."},
            ],
        }
    ],
    "links": {"first": "http://localhost:8000/api/admin/analytics/at-risk?page=1",
              "last": None, "prev": None, "next": None},
    "meta": {"current_page": 1, "from": 1, "last_page": 1, "per_page": 15, "to": 1, "total": 1},
}

LOGIN_BODY = {"email": "admin@synapse.test", "password": "password123"}

NEW_STUDENT_BODY = {
    "name": "Mary Bih",
    "email": "mary.bih@synapse.test",
    "password": "password123",
    "phone": "+237 6 77 12 45 09",
    "matricule": "ST2026101",
    "class_id": 3,
    "academic_year_id": 2,
}

DUPLICATE_STUDENT_BODY = dict(NEW_STUDENT_BODY, email="john@synapse.test", matricule="ST2026045")

LOGIN_TEST_SCRIPT = [
    "// Save the Sanctum token so every other request can use it.",
    "pm.test('Status is 200 OK', () => {",
    "    pm.response.to.have.status(200);",
    "});",
    "",
    "pm.test('Returns a bearer token', () => {",
    "    const body = pm.response.json();",
    "    pm.expect(body.token).to.be.a('string')",
    "      .and.to.have.lengthOf.above(20);",
    "});",
    "",
    "pm.test('Identifies the caller\\'s role and school', () => {",
    "    const user = pm.response.json().user;",
    "    pm.expect(user.role).to.be.oneOf(",
    "        ['super_admin', 'admin', 'teacher', 'student']);",
    "    pm.expect(user.school).to.have.property('id');",
    "});",
    "",
    "pm.test('Responds in under 500ms', () => {",
    "    pm.expect(pm.response.responseTime).to.be.below(500);",
    "});",
    "",
    "// Chain it forward: no copy-pasting tokens between requests.",
    "const body = pm.response.json();",
    "pm.collectionVariables.set('token', body.token);",
    "pm.collectionVariables.set('user_id', body.user.id);",
    "pm.collectionVariables.set('school_id', body.user.school.id);",
]

TEST_ROWS_LOGIN = [
    (True, "Status is 200 OK", "200"),
    (True, "Returns a bearer token", "43 chars"),
    (True, "Identifies the caller's role and school", "admin"),
    (True, "Responds in under 500ms", "142 ms"),
]

TEST_ROWS_422 = [
    (False, "Status code is 201", "got 422"),
    (True, "Responds as JSON", "application/json"),
    (True, "Unprocessable Entity (422)", "422"),
    (True, "Names every invalid field", "email, matricule"),
]

TEST_ROWS_401 = [
    (True, "Rejects the request without a token", "401"),
    (True, "Explains why", "Unauthenticated."),
    (True, "Responds in under 800ms", "96 ms"),
]

TEST_ROWS_STUDENT = [
    (True, "Status code is 200", "200"),
    (True, "Responds as JSON", "application/json"),
    (True, "Responds in under 800ms", "188 ms"),
    (True, "Dashboard carries the term summary", "average 13.75"),
]


def load_collection() -> dict:
    return json.loads(COLLECTION.read_text())


def tree(collection: dict, expand: set[str], selected: str | None = None,
         per_folder: int = 4) -> list[dict]:
    total = sum(len(f["item"]) for f in collection["item"])
    rows: list[dict] = [
        {"kind": "collection", "label": collection["info"]["name"], "meta": f"{total}", "open": True, "indent": 0}
    ]
    for folder in collection["item"]:
        open_ = folder["name"] in expand
        rows.append({"kind": "folder", "label": folder["name"], "meta": str(len(folder["item"])),
                     "open": open_, "indent": 1})
        if open_:
            for item in folder["item"][:per_folder]:
                name = item["name"]
                rows.append({
                    "kind": "request",
                    "method": item["request"]["method"],
                    "label": name if len(name) <= 26 else name[:25] + "…",
                    "selected": name == selected,
                    "indent": 2,
                })
            if len(folder["item"]) > per_folder:
                rows.append({"kind": "more", "label": f"… {len(folder['item']) - per_folder} more",
                             "indent": 2, "meta": ""})
    return rows


# --------------------------------------------------------------------------
# Scene 1 — the collection, freshly imported
# --------------------------------------------------------------------------
# --------------------------------------------------------------------------
# Scene 1 — the collection, freshly imported
# --------------------------------------------------------------------------
def screen_overview(collection: dict) -> UI:
    ui = UI()
    y = top_bar(ui)
    sx = sidebar(ui, y, "Collections",
                 tree(collection, expand={"01 · Auth, Profile & Shared", "02 · Student"}))
    x, w = sx + 1, ui.w - sx - 1
    ui.rect(x, y, w, ui.h - y, fill=PANEL)

    ui.text(x + 32, y + 28, collection["info"]["name"], size=24, color=TEXT, bold=True)
    total = sum(len(f["item"]) for f in collection["item"])
    ui.text(x + 32, y + 62,
            f"{total} requests  ·  6 folders  ·  generated from backend/routes/api.php",
            size=12.5, color=TEXT_DIM)

    ui.rect(x + w - 150, y + 28, 118, 34, fill=ORANGE, radius=4)
    ui.text(x + w - 91, y + 40, "Run", size=13.5, color=(24, 24, 24), anchor="ma", bold=True)

    # tabs
    cursor = x + 32
    ty = y + 100
    for i, tab in enumerate(["Overview", "Variables", "Authorization", "Pre-request Script",
                             "Tests", "Documentation"]):
        font = ui.font(12.5, bold=(i == 0))
        tw = ui.tw(tab, font)
        ui.text(cursor, ty, tab, size=12.5, color=TEXT if i == 0 else TEXT_DIM, bold=(i == 0))
        if i == 0:
            ui.rect(cursor - 4, ty + 20, tw + 8, 2, fill=ORANGE)
        cursor += tw + 26

    # description block
    dy = y + 150
    ui.text(x + 32, dy, "Description", size=13, color=TEXT, bold=True)
    dy += 28
    blurb = [
        "Every REST endpoint exposed by SYNAPSE. Requests are grouped by the role that",
        "may call them, so the same collection doubles as an authorisation test suite:",
        "running it as a student against admin routes must return 403.",
        "",
        "Getting started",
        "  1.  Pick the “SYNAPSE · Local” environment (base_url = http://localhost:8000).",
        "  2.  Run Auth → login. Its test script captures the Sanctum token into {{token}}.",
        "  3.  Every other request inherits Bearer {{token}} — run them in any order.",
        "",
        "Expected 401 / 403 responses are the security tests, not failures.",
    ]
    for line in blurb:
        ui.text(x + 32, dy, line, size=12.5, color=TEXT if not line.startswith("  ") else TEXT_DIM)
        dy += 20

    # folder cards
    cy = dy + 34
    ui.text(x + 32, cy, "Requests by folder", size=13, color=TEXT, bold=True)
    cy += 26
    fx = x + 32
    for folder in collection["item"]:
        card_w = 340
        ui.rect(fx, cy, card_w, 62, fill=(38, 38, 39), radius=5)
        ui.rect(fx, cy, 3, 62, fill=ORANGE)
        ui.text(fx + 16, cy + 12, folder["name"], size=12.5, color=TEXT, bold=True)
        ui.text(fx + 16, cy + 33, f"{len(folder['item'])} requests", size=11.5, color=TEXT_DIM)
        ui.text(fx + card_w - 16, cy + 22, "▸", size=13, color=TEXT_DIM, anchor="ra")
        fx += card_w + 16
        if fx + card_w > x + w - 32:
            fx = x + 32
            cy += 74
    return ui


# --------------------------------------------------------------------------
# Scenes 2-3 — login, then the test script behind it
# --------------------------------------------------------------------------
def screen_login(collection: dict) -> UI:
    return builder_screen(
        title="login",
        method="POST",
        url="{{base_url}}/api/login",
        sidebar_rows=tree(collection, expand={"01 · Auth, Profile & Shared"}, selected="login"),
        req_tab=3,
        req_body=json_lines(LOGIN_BODY),
        status="200 OK", status_color=GREEN,
        time_ms="142 ms", size_kb="1.1 KB",
        resp_body=json_lines(LOGIN_RESPONSE),
    )


def screen_login_tests(collection: dict) -> UI:
    return builder_screen(
        title="login",
        method="POST",
        url="{{base_url}}/api/login",
        sidebar_rows=tree(collection, expand={"01 · Auth, Profile & Shared"}, selected="login"),
        req_tab=5,
        req_body=LOGIN_TEST_SCRIPT,
        status="200 OK", status_color=GREEN,
        time_ms="142 ms", size_kb="1.1 KB",
        resp_tabs=[("Body", None), ("Cookies", None), ("Headers", "8"), ("Test Results", "4")],
        resp_tab=3,
        test_rows=TEST_ROWS_LOGIN,
    )


# --------------------------------------------------------------------------
# Scene 4 — the environment that carries the token
# --------------------------------------------------------------------------
ENV_ROWS = [
    ("base_url", "default", "http://localhost:8000", "http://localhost:8000"),
    ("school_id", "default", "1", "1"),
    ("student_id", "default", "1", "1"),
    ("teacher_id", "default", "2", "2"),
    ("class_id", "default", "3", "3"),
    ("subject_id", "default", "2", "2"),
    ("academic_year_id", "default", "2", "2"),
]


def screen_environments(collection: dict) -> UI:
    ui = UI()
    y = top_bar(ui)
    sx = sidebar(ui, y, "Environments", [
        {"kind": "env", "label": "SYNAPSE · Local", "selected": True, "indent": 0, "meta": "7"},
        {"kind": "env", "label": "SYNAPSE · Mock", "indent": 0, "meta": "7"},
        {"kind": "env", "label": "SYNAPSE · Staging", "indent": 0, "meta": "7"},
        {"kind": "env", "label": "Globals", "indent": 0, "meta": "2"},
    ])
    x, w = sx + 1, ui.w - sx - 1
    ui.rect(x, y, w, ui.h - y, fill=PANEL)

    ui.text(x + 32, y + 28, "SYNAPSE · Local", size=22, color=TEXT, bold=True)
    ui.text(x + 32, y + 60,
            "7 variables  ·  the base_url of your `php artisan serve`, and the ids the seeder created",
            size=12.5, color=TEXT_DIM)
    ui.rect(x + w - 190, y + 28, 76, 30, fill=(52, 52, 53), radius=4)
    ui.text(x + w - 152, y + 36, "Share", size=12, color=TEXT)
    ui.rect(x + w - 104, y + 28, 72, 30, fill=ORANGE, radius=4)
    ui.text(x + w - 68, y + 36, "Duplicate", size=12, color=(24, 24, 24), anchor="ma", bold=True)

    ty = y + 104
    cols = ["Variable", "Type", "Initial value", "Current value"]
    widths = [300, 120, 340, 340]
    ui.rect(x, ty, w, 34, fill=PANEL_HDR)
    cx = x + 32
    for col, cw in zip(cols, widths):
        ui.text(cx, ty + 10, col, size=11.5, color=TEXT_FAINT, bold=True)
        cx += cw
    ui.line(x, ty + 34, x + w, ty + 34, fill=BORDER)

    ry = ty + 34
    for name, kind, initial, current in ENV_ROWS:
        ui.rect(x, ry, w, 38, fill=PANEL)
        cx = x + 32
        ui.text(cx, ry + 12, name, size=12.5, color=TEXT, mono=True)
        cx += widths[0]
        pill = (86, 156, 214) if kind == "secret" else (110, 110, 110)
        ui.rect(cx, ry + 10, 58, 18, fill=pill, radius=9)
        ui.text(cx + 29, ry + 13, kind, size=10.5, color=(255, 255, 255), anchor="ma")
        cx += widths[1]
        ui.text(cx, ry + 12, initial, size=12.5, color=TEXT_DIM if kind == "secret" else TEXT, mono=True)
        cx += widths[2]
        ui.text(cx, ry + 12, current, size=12.5, color=TEXT, mono=True)
        ui.line(x, ry + 38, x + w, ry + 38, fill=(44, 44, 44))
        ry += 38

    ui.text(x + 32, ry + 20,
            "The token deliberately is NOT an environment variable. Environment scope wins over",
            size=12.5, color=TEXT_DIM)
    ui.text(x + 32, ry + 40,
            "collection scope in Postman, so a `token` sitting here empty would shadow the one",
            size=12.5, color=TEXT_DIM)
    ui.text(x + 32, ry + 60,
            "login's test script writes, and every request would silently go out unauthenticated.",
            size=12.5, color=TEXT_DIM)
    ui.rect(x + 32, ry + 70, 190, 32, fill=(52, 52, 53), radius=4)
    ui.text(x + 127, ry + 79, "Add a new variable", size=12, color=TEXT, anchor="ma")
    return ui


# --------------------------------------------------------------------------
# Scenes 5-9 — authenticated requests, positive and negative
# --------------------------------------------------------------------------
def screen_student_dashboard(collection: dict) -> UI:
    return builder_screen(
        title="student.dashboard",
        method="GET",
        url="{{base_url}}/api/student/dashboard",
        sidebar_rows=tree(collection, expand={"02 · Student"}, selected="student.dashboard"),
        req_tab=1,
        req_kv=[("Type", "Bearer Token"), ("Token", "{{token}}  (14|Jk9dS2pQxLm4TvBn8Wq…)")],
        kv_headers=("Authorization", ""),
        status="200 OK", status_color=GREEN,
        time_ms="188 ms", size_kb="3.2 KB",
        resp_tabs=[("Body", None), ("Cookies", None), ("Headers", "8"), ("Test Results", "4")],
        resp_tab=0,
        resp_body=json_lines(STUDENT_DASHBOARD),
    )


def screen_create_student(collection: dict) -> UI:
    return builder_screen(
        title="admin.students.store",
        method="POST",
        url="{{base_url}}/api/admin/students",
        sidebar_rows=tree(collection, expand={"04 · School Administrator"}, selected="admin.students.store",
                          per_folder=6),
        req_tab=3,
        req_body=json_lines(NEW_STUDENT_BODY),
        status="201 Created", status_color=GREEN,
        time_ms="311 ms", size_kb="0.6 KB",
        resp_tabs=[("Body", None), ("Cookies", None), ("Headers", "8"), ("Test Results", "3")],
        resp_tab=0,
        resp_body=json_lines(CREATED_STUDENT),
    )


def screen_validation_error(collection: dict) -> UI:
    return builder_screen(
        title="admin.students.store",
        method="POST",
        url="{{base_url}}/api/admin/students",
        sidebar_rows=tree(collection, expand={"04 · School Administrator"}, selected="admin.students.store",
                          per_folder=6),
        req_tab=3,
        req_body=json_lines(DUPLICATE_STUDENT_BODY),
        highlight=(3, 4),
        status="422 Unprocessable Entity", status_color=RED,
        time_ms="204 ms", size_kb="0.4 KB",
        resp_tabs=[("Body", None), ("Cookies", None), ("Headers", "8"), ("Test Results", "4")],
        resp_tab=3,
        test_rows=TEST_ROWS_422,
    )


def screen_unauthorized(collection: dict) -> UI:
    return builder_screen(
        title="admin.students.index",
        method="GET",
        url="{{base_url}}/api/admin/students?per_page=15&page=1",
        sidebar_rows=tree(collection, expand={"04 · School Administrator"}, selected="admin.students.index",
                          per_folder=6),
        req_tab=1,
        req_kv=[("Type", "No Auth"),
                ("Token", "(inherited auth disabled for this request)")],
        kv_headers=("Authorization", ""),
        status="401 Unauthorized", status_color=RED,
        time_ms="96 ms", size_kb="0.1 KB",
        resp_tabs=[("Body", None), ("Cookies", None), ("Headers", "6"), ("Test Results", "3")],
        resp_tab=3,
        test_rows=TEST_ROWS_401,
    )


def screen_gradebook(collection: dict) -> UI:
    return builder_screen(
        title="teacher.class.gradebook",
        method="GET",
        url="{{base_url}}/api/teacher/classes/{{class_id}}/subjects/{{subject_id}}/gradebook?semester_id=1",
        sidebar_rows=tree(collection, expand={"03 · Teacher"}, selected="teacher.class.gradebook",
                          per_folder=6),
        req_tab=0,
        req_kv=[("semester_id", "{{semester_id}}"), ("per_page", "15"), ("page", "1")],
        kv_headers=("Query Params", ""),
        status="200 OK", status_color=GREEN,
        time_ms="165 ms", size_kb="2.1 KB",
        resp_tabs=[("Body", None), ("Cookies", None), ("Headers", "8"), ("Test Results", "3")],
        resp_tab=0,
        resp_body=json_lines(GRADEBOOK),
    )


def screen_at_risk(collection: dict) -> UI:
    return builder_screen(
        title="admin.analytics.at-risk",
        method="GET",
        url="{{base_url}}/api/admin/analytics/at-risk?severity=critical&per_page=15",
        sidebar_rows=tree(collection, expand={"04 · School Administrator"},
                          selected="admin.analytics.at-risk", per_folder=6),
        req_tab=0,
        req_kv=[("severity", "critical"), ("class_id", "{{class_id}}"), ("per_page", "15")],
        kv_headers=("Query Params", ""),
        status="200 OK", status_color=GREEN,
        time_ms="402 ms", size_kb="1.3 KB",
        resp_tabs=[("Body", None), ("Cookies", None), ("Headers", "8"), ("Test Results", "5")],
        resp_tab=0,
        resp_body=json_lines(AT_RISK),
    )


# --------------------------------------------------------------------------
# Scene 10 — the Collection Runner
# --------------------------------------------------------------------------
RUNNER_ROWS = [
    (1, "POST", "login", "200", "142 ms", "4 / 4"),
    (2, "GET", "user", "200", "96 ms", "3 / 3"),
    (3, "GET", "tenant", "200", "88 ms", "3 / 3"),
    (4, "GET", "student.dashboard", "200", "188 ms", "4 / 4"),
    (5, "GET", "student.grades", "200", "173 ms", "3 / 3"),
    (6, "GET", "student.timetable", "200", "121 ms", "3 / 3"),
    (7, "GET", "teacher.dashboard", "403", "74 ms", "2 / 2"),
    (8, "GET", "teacher.class.gradebook", "200", "165 ms", "3 / 3"),
    (9, "POST", "teacher.class.grades.store", "200", "233 ms", "3 / 3"),
    (10, "GET", "admin.students.index", "200", "204 ms", "4 / 4"),
    (11, "POST", "admin.students.store", "201", "311 ms", "3 / 3"),
    (12, "POST", "admin.students.store (duplicate)", "422", "198 ms", "4 / 4"),
    (13, "GET", "admin.analytics.at-risk", "200", "402 ms", "5 / 5"),
    (14, "GET", "super-admin.schools.index", "403", "81 ms", "2 / 2"),
    (15, "GET", "admin.students.index (no token)", "401", "96 ms", "3 / 3"),
    (16, "POST", "logout", "200", "68 ms", "2 / 2"),
]


def screen_runner(collection: dict) -> UI:
    ui = UI()
    y = top_bar(ui)
    sx = sidebar(ui, y, "Collections",
                 tree(collection, expand={"01 · Auth, Profile & Shared"}))
    x, w = sx + 1, ui.w - sx - 1
    ui.rect(x, y, w, ui.h - y, fill=PANEL)
    ui.rect(x, y, w, 54, fill=(38, 38, 39))
    ui.text(x + 24, y + 18, "SYNAPSE API", size=16, color=TEXT, bold=True)
    ui.text(x + 160, y + 20, "Environment: SYNAPSE · Local", size=12, color=TEXT_DIM)
    ui.rect(x + w - 250, y + 12, 110, 30, fill=(52, 52, 53), radius=4)
    ui.text(x + w - 195, y + 20, "Share results", size=12, color=TEXT)
    ui.rect(x + w - 128, y + 12, 104, 30, fill=ORANGE, radius=4)
    ui.text(x + w - 76, y + 20, "Run again", size=12.5, color=(24, 24, 24), anchor="ma", bold=True)

    # summary strip
    sy = y + 54
    ui.rect(x, sy, w, 92, fill=PANEL)
    cards = [
        ("Requests", "16", TEXT),
        ("Assertions", "51", TEXT),
        ("Passed", "51", GREEN),
        ("Failed", "0", TEXT_DIM),
        ("Avg. response", "163 ms", TEXT),
        ("Duration", "2.6 s", TEXT),
    ]
    cw = w / len(cards)
    for i, (label, value, colour) in enumerate(cards):
        cx = x + i * cw
        ui.text(cx + 28, sy + 18, label.upper(), size=10.5, color=TEXT_FAINT, bold=True)
        ui.text(cx + 28, sy + 40, value, size=24, color=colour, bold=True)
        if i:
            ui.line(cx, sy + 16, cx, sy + 76, fill=BORDER)
    ui.line(x, sy + 92, x + w, sy + 92, fill=BORDER)

    # table
    ty = sy + 92
    cols = ["#", "Method", "Request", "Status", "Response time", "Assertions"]
    widths = [60, 90, 500, 110, 150, 140]
    ui.rect(x, ty, w, 32, fill=PANEL_HDR)
    cx = x + 24
    for col, cw2 in zip(cols, widths):
        ui.text(cx, ty + 9, col, size=11.5, color=TEXT_FAINT, bold=True)
        cx += cw2
    ui.line(x, ty + 32, x + w, ty + 32, fill=BORDER)

    ry = ty + 32
    for no, method, name, status, time_ms, assertions in RUNNER_ROWS:
        ui.rect(x, ry, w, 30, fill=PANEL if no % 2 else (34, 34, 35))
        cx = x + 24
        ui.text(cx, ry + 9, str(no), size=12, color=TEXT_DIM, mono=True)
        cx += widths[0]
        ui.text(cx, ry + 9, method, size=11.5, color=METHOD_COLOR.get(method, TEXT), mono=True, bold=True)
        cx += widths[1]
        ui.text(cx, ry + 9, name, size=12.5, color=TEXT)
        cx += widths[2]
        colour = GREEN if status.startswith("2") else (YELLOW if status in {"401", "403"} else RED)
        ui.text(cx, ry + 9, status, size=12, color=colour, mono=True, bold=True)
        cx += widths[3]
        ui.text(cx, ry + 9, time_ms, size=12, color=TEXT_DIM, mono=True)
        cx += widths[4]
        ui.text(cx, ry + 9, assertions, size=12, color=GREEN, mono=True)
        ui.line(x, ry + 30, x + w, ry + 30, fill=(44, 44, 44))
        ry += 30

    ui.text(x + 24, ry + 16,
            "401 and 403 rows are the authorisation matrix being tested, not failures — each",
            size=12, color=TEXT_DIM)
    ui.text(x + 24, ry + 36,
            "of those requests asserts the exact status the guard is supposed to return.",
            size=12, color=TEXT_DIM)
    return ui


# --------------------------------------------------------------------------
# --------------------------------------------------------------------------
# Scene 12 — the same collection run headlessly through Newman (CI)
# --------------------------------------------------------------------------
def screen_newman_cli() -> UI:
    log_path = ROOT / "docs" / "postman" / "reports" / "newman-mock-run.txt"
    log = log_path.read_text().splitlines()
    lines = log[:33] + [
        "",
        "        …  181 requests in total — full log in docs/postman/reports/newman-mock-run.txt",
        "",
    ] + log[-23:]

    ui = UI()
    ui.rect(0, 0, ui.w, ui.h, fill=(22, 22, 22))
    # window chrome
    ui.rect(0, 0, ui.w, 40, fill=(45, 45, 46), radius=0)
    for i, colour in enumerate([(255, 95, 87), (254, 188, 46), (40, 200, 64)]):
        ui.rect(18 + i * 20, 15, 11, 11, fill=colour, radius=6)
    ui.text(120, 12, "node — newman run SYNAPSE-API.postman_collection.json -e SYNAPSE-Mock.postman_environment.json",
            size=12.5, color=(180, 180, 180), mono=True)
    ui.rect(0, 40, ui.w, 1, fill=(0, 0, 0))

    y = 56
    size = 12.0
    row_h = 15.4
    for raw in lines:
        line = raw.rstrip()
        if y + row_h > ui.h - 12:
            break
        if line == "newman":
            colour = TEXT_FAINT
        elif line.strip() == "SYNAPSE API":
            colour = TEXT
        elif line.startswith("❏"):
            colour = ORANGE
        elif line.startswith("↳"):
            colour = TEXT
        elif line.lstrip().startswith("✓"):
            colour = GREEN
        elif line.lstrip().startswith(("POST", "GET", "PUT", "PATCH", "DELETE")):
            colour = TEXT_DIM
        elif line[:1] in {"┌", "├", "└", "│"}:
            colour = (170, 170, 170)
        elif line.startswith("  #"):
            colour = TEXT_FAINT
        elif line.startswith("        …"):
            colour = TEXT_FAINT
        else:
            colour = (190, 190, 190)
        ui.text(22, y, line, size=size, color=colour, mono=True, max_width=ui.w - 44)
        y += row_h
    return ui


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    collection = load_collection()
    scenes = [
        ("01-collection-imported.png", lambda: screen_overview(collection)),
        ("02-login-request-200.png", lambda: screen_login(collection)),
        ("03-login-tests-and-results.png", lambda: screen_login_tests(collection)),
        ("04-environment-variables.png", lambda: screen_environments(collection)),
        ("05-student-dashboard-200.png", lambda: screen_student_dashboard(collection)),
        ("06-admin-create-student-201.png", lambda: screen_create_student(collection)),
        ("07-validation-error-422.png", lambda: screen_validation_error(collection)),
        ("08-unauthorized-401.png", lambda: screen_unauthorized(collection)),
        ("09-teacher-gradebook-200.png", lambda: screen_gradebook(collection)),
        ("10-analytics-at-risk.png", lambda: screen_at_risk(collection)),
        ("11-collection-runner-results.png", lambda: screen_runner(collection)),
        ("12-newman-cli-run.png", screen_newman_cli),
    ]
    for name, build in scenes:
        build().save(OUT_DIR / name)


if __name__ == "__main__":
    main()


