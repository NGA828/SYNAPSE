#!/usr/bin/env python3
"""
SYNAPSE User Guide — PDF generator.

Regenerates docs/SYNAPSE-User-Guide.pdf as a fully designed document:
cover page, table of contents (with page numbers), styled section bands,
data tables, callouts and consistent headers/footers.

Usage:
    python3 docs/generate_user_guide.py [output.pdf]

Requires: reportlab (pip install reportlab)
"""

import os
import sys

from reportlab.lib import colors
from reportlab.lib.colors import HexColor, white
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.pdfmetrics import registerFontFamily
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    NextPageTemplate,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)
from reportlab.platypus.tableofcontents import TableOfContents

# ----------------------------------------------------------------------------
# Paths & fonts
# ----------------------------------------------------------------------------

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = sys.argv[1] if len(sys.argv) > 1 else os.path.join(HERE, "SYNAPSE-User-Guide.pdf")

FONT_DIR = "/usr/share/fonts/truetype/dejavu"
DEJAVU = os.path.join(FONT_DIR, "DejaVuSans.ttf")
DEJAVU_BOLD = os.path.join(FONT_DIR, "DejaVuSans-Bold.ttf")
DEJAVU_MONO = os.path.join(FONT_DIR, "DejaVuSansMono.ttf")

pdfmetrics.registerFont(TTFont("DejaVu", DEJAVU))
pdfmetrics.registerFont(TTFont("DejaVu-Bold", DEJAVU_BOLD))
pdfmetrics.registerFont(TTFont("DejaVuMono", DEJAVU_MONO))
registerFontFamily("DejaVu", normal="DejaVu", bold="DejaVu-Bold",
                   italic="DejaVu", boldItalic="DejaVu-Bold")

# ----------------------------------------------------------------------------
# Brand palette (mirrors the app: indigo → violet, teal accent, slate surfaces)
# ----------------------------------------------------------------------------

INDIGO_900 = HexColor("#312E81")
INDIGO_800 = HexColor("#3730A3")
INDIGO_700 = HexColor("#4338CA")
INDIGO_600 = HexColor("#4F46E5")
VIOLET = HexColor("#7C3AED")
TEAL = HexColor("#14B8A6")
SLATE_900 = HexColor("#0F172A")
SLATE_700 = HexColor("#334155")
SLATE_500 = HexColor("#64748B")
SLATE_400 = HexColor("#94A3B8")
SLATE_300 = HexColor("#CBD5E1")
SLATE_200 = HexColor("#E2E8F0")
SLATE_100 = HexColor("#F1F5F9")
SLATE_50 = HexColor("#F8FAFC")
EMERALD = HexColor("#059669")
ROSE = HexColor("#E11D48")
AMBER = HexColor("#D97706")
SKY = HexColor("#0284C7")

BG_INDIGO = HexColor("#EEF2FF")
BG_TEAL = HexColor("#F0FDFA")
BG_AMBER = HexColor("#FFFBEB")

# ----------------------------------------------------------------------------
# Page geometry & styles
# ----------------------------------------------------------------------------

PAGE_W, PAGE_H = A4
M = 50                       # side margin
CONTENT_W = PAGE_W - 2 * M   # usable width
FRAME = Frame(M, 56, CONTENT_W, PAGE_H - 56 - 62, id="body", leftPadding=0,
              rightPadding=0, topPadding=0, bottomPadding=0)


def _style(name, **kw):
    base = dict(fontName="DejaVu", fontSize=9.8, leading=14.4,
                textColor=SLATE_700, alignment=TA_LEFT, spaceAfter=6)
    base.update(kw)
    return ParagraphStyle(name, **base)


BODY = _style("Body")
BODY_LEAD = _style("BodyLead", fontSize=10.6, leading=16, textColor=SLATE_700)
BODY_SMALL = _style("BodySmall", fontSize=8.6, leading=12, textColor=SLATE_500)

# Section band (registers itself in the TOC via style name)
H1 = _style("TOCHeading1", fontName="DejaVu-Bold", fontSize=13.5, leading=18,
            textColor=white, backColor=INDIGO_700, borderColor=VIOLET,
            borderWidth=(3.5, 0, 0, 0), borderPadding=(8, 0, 8, 13),
            spaceBefore=18, spaceAfter=12, keepWithNext=1)

# Sub-section heading (registers itself in the TOC via style name)
H2 = _style("TOCHeading2", fontName="DejaVu-Bold", fontSize=11.4, leading=15,
            textColor=INDIGO_800, borderColor=VIOLET, borderWidth=(0, 0, 1.2, 0),
            borderPadding=(0, 0, 4, 0), spaceBefore=14, spaceAfter=6,
            keepWithNext=1)

H3 = _style("H3", fontName="DejaVu-Bold", fontSize=10.1, leading=13.5,
            textColor=SLATE_900, spaceBefore=10, spaceAfter=4, keepWithNext=1)

BULLET = _style("Bullet", leftIndent=16, bulletIndent=3, spaceAfter=3.5,
                bulletFontName="DejaVu", bulletFontSize=9.8,
                bulletColor=INDIGO_600)
STEP = _style("Step", leftIndent=18, bulletIndent=2, spaceAfter=5,
              bulletFontName="DejaVu-Bold", bulletFontSize=9.8,
              bulletColor=INDIGO_600)

CODE = _style("Code", fontName="DejaVuMono", fontSize=8.4, leading=12.5,
              textColor=INDIGO_800, backColor=SLATE_100, borderColor=SLATE_200,
              borderWidth=0.7, borderPadding=(5, 7, 5, 7), spaceBefore=4,
              spaceAfter=8)

CALLOUT = _style("Callout", fontSize=9.3, leading=13.8, textColor=SLATE_700)

TOC_TITLE = _style("TocTitle", fontName="DejaVu-Bold", fontSize=13.5,
                   leading=18, textColor=white, backColor=INDIGO_700,
                   borderColor=VIOLET, borderWidth=(3.5, 0, 0, 0),
                   borderPadding=(8, 0, 8, 13), spaceBefore=0, spaceAfter=14)

TOC0 = _style("TOC0", fontName="DejaVu-Bold", fontSize=10.6, leading=16,
              textColor=INDIGO_800, leftIndent=6, spaceBefore=9)
TOC1 = _style("TOC1", fontName="DejaVu", fontSize=9.4, leading=13.6,
              textColor=SLATE_700, leftIndent=24, spaceBefore=2)

CELL = _style("Cell", fontSize=8.8, leading=12, textColor=SLATE_700,
              spaceAfter=0)
CELL_HEAD = _style("CellHead", fontName="DejaVu-Bold", fontSize=8.8,
                   leading=12, textColor=white, spaceAfter=0)

# ----------------------------------------------------------------------------
# Document template with header/footer
# ----------------------------------------------------------------------------


class GuideDoc(BaseDocTemplate):
    """Registers TOC entries for the band/sub-section paragraphs."""

    def afterFlowable(self, flowable):
        if isinstance(flowable, Paragraph):
            name = flowable.style.name
            if name == "TOCHeading1":
                self.notify("TOCEntry", (0, flowable.getPlainText(), self.page))
            elif name == "TOCHeading2":
                self.notify("TOCEntry", (1, flowable.getPlainText(), self.page))


def draw_content_page(canv, doc):
    """Header + footer for every content page."""
    canv.saveState()
    # Header
    canv.setFont("DejaVu-Bold", 10)
    canv.setFillColor(INDIGO_700)
    canv.drawString(M, PAGE_H - 38, "SYNAPSE")
    canv.setFont("DejaVu", 9)
    canv.setFillColor(SLATE_500)
    canv.drawString(M + 60, PAGE_H - 36, "User Guide")
    canv.setFont("DejaVu", 8.5)
    canv.drawRightString(PAGE_W - M, PAGE_H - 36,
                         "Multi-Tenant School Management SaaS")
    canv.setStrokeColor(SLATE_200)
    canv.setLineWidth(0.8)
    canv.line(M, PAGE_H - 48, PAGE_W - M, PAGE_H - 48)
    # Footer
    canv.line(M, 44, PAGE_W - M, 44)
    canv.setFont("DejaVu", 8)
    canv.setFillColor(SLATE_500)
    canv.drawString(M, 32, "SYNAPSE  ·  Multi-Tenant School Management SaaS")
    canv.drawRightString(PAGE_W - M, 32, "Page %d" % canv.getPageNumber())
    canv.restoreState()


def draw_cover(canv, doc):
    """Full-bleed cover with gradient and a neural-network motif."""
    canv.saveState()
    w, h = PAGE_W, PAGE_H
    # Gradient background (fall back to flat indigo)
    try:
        canv.linearGradient(0, 0, w, h,
                            (INDIGO_900, INDIGO_800, INDIGO_700),
                            extend=True)
    except Exception:
        canv.setFillColor(INDIGO_700)
        canv.rect(0, 0, w, h, fill=1, stroke=0)

    # ---- Decorative neural-network motif ---------------------------------
    nodes = [(150, 706), (300, 752), (452, 706), (96, 614), (232, 634),
             (366, 654), (506, 592), (186, 548), (416, 548)]
    edges = [(0, 1), (1, 2), (2, 5), (5, 6), (6, 8), (4, 3), (3, 7), (4, 7),
             (4, 5), (7, 8), (0, 3), (2, 6)]
    canv.setStrokeColor(white)
    canv.setLineWidth(1.1)
    canv.setStrokeAlpha(0.22)
    for a, b in edges:
        canv.line(nodes[a][0], nodes[a][1], nodes[b][0], nodes[b][1])
    for i, (x, y) in enumerate(nodes):
        r = 4.2 + (i % 3)
        if i == 4:  # highlighted node with a halo
            canv.setFillAlpha(0.22)
            canv.setFillColor(white)
            canv.circle(x, y, 13, fill=1, stroke=0)
            canv.setFillAlpha(1)
            canv.setFillColor(white)
            canv.circle(x, y, 6, fill=1, stroke=0)
        else:
            canv.setFillAlpha(0.92)
            canv.setFillColor(TEAL if i % 2 else HexColor("#A5B4FC"))
            canv.circle(x, y, r, fill=1, stroke=0)
    canv.setFillAlpha(1)

    # ---- Wordmark & title ------------------------------------------------
    canv.setFillColor(white)
    canv.setFont("DejaVu-Bold", 46)
    canv.drawCentredString(w / 2, 470, "SYNAPSE")

    canv.setFont("DejaVu", 12.5)
    canv.setFillAlpha(0.88)
    try:
        canv.setCharSpace(2.4)
    except AttributeError:
        canv._charSpace = 2.4
    canv.drawCentredString(w / 2, 438, "MULTI-TENANT SCHOOL MANAGEMENT SAAS")
    try:
        canv.setCharSpace(0)
    except AttributeError:
        canv._charSpace = 0

    canv.setStrokeAlpha(0.5)
    canv.setLineWidth(0.8)
    canv.line(w / 2 - 62, 421, w / 2 + 62, 421)

    canv.setFillAlpha(1)
    canv.setFont("DejaVu-Bold", 52)
    canv.drawCentredString(w / 2, 330, "User Guide")

    canv.setFont("DejaVu", 12)
    canv.setFillAlpha(0.88)
    canv.drawCentredString(
        w / 2, 296,
        "End-user documentation for Students, Teachers, Administrators and Super Admins")

    # ---- Role chips -------------------------------------------------------
    chips = ["STUDENT", "TEACHER", "ADMINISTRATOR", "SUPER ADMIN"]
    canv.setFont("DejaVu-Bold", 10)
    total = sum(canv.stringWidth(c, "DejaVu-Bold", 10) + 28 for c in chips) \
        + 12 * (len(chips) - 1)
    x = (w - total) / 2
    for c in chips:
        cw = canv.stringWidth(c, "DejaVu-Bold", 10) + 28
        canv.setFillAlpha(0.13)
        canv.setFillColor(white)
        canv.setStrokeAlpha(0.4)
        canv.setStrokeColor(white)
        canv.roundRect(x, 198, cw, 27, 13.5, fill=1, stroke=1)
        canv.setFillAlpha(1)
        canv.setFillColor(white)
        canv.drawCentredString(x + cw / 2, 207, c)
        x += cw + 12

    # ---- Footer of the cover ---------------------------------------------
    canv.setFont("DejaVu", 9.5)
    canv.setFillAlpha(0.78)
    canv.drawCentredString(w / 2, 118, "Version 1.0   ·   August 2026")
    canv.setFont("DejaVu", 9.5)
    canv.drawCentredString(w / 2, 100, "Covers the complete SYNAPSE platform — Phases 1–4 and the SaaS upgrade")
    canv.setFillAlpha(0.55)
    canv.setFont("DejaVu", 8.5)
    canv.drawCentredString(w / 2, 64, "© 2026 SYNAPSE  ·  All rights reserved")
    canv.restoreState()


# ----------------------------------------------------------------------------
# Building blocks
# ----------------------------------------------------------------------------


def section(title, body=None):
    """A numbered H1 band, optionally followed by its first paragraph."""
    out = [Paragraph(title, H1)]
    if body:
        out.append(Paragraph(body, BODY_LEAD))
    return out


def h2(title):
    return Paragraph(title, H2)


def h3(title):
    return Paragraph(title, H3)


def p(text, style=BODY):
    return Paragraph(text, style)


def bullet(text, kind="bullet"):
    par = Paragraph(text, BULLET)
    par.bulletText = "•"
    return par


def bullets(items, numbered=False):
    out = []
    for i, item in enumerate(items, 1):
        if numbered:
            par = Paragraph(item, STEP)
            par.bulletText = "%d." % i
            out.append(par)
        else:
            out.append(bullet(item))
    return out


def callout(text, kind="info", title=None):
    cfg = {
        "info": (INDIGO_600, BG_INDIGO),
        "tip": (TEAL, BG_TEAL),
        "warn": (AMBER, BG_AMBER),
    }
    accent, bg = cfg[kind]
    inner = "<b>%s</b><br/>%s" % (title, text) if title else text
    cell = Paragraph(inner, CALLOUT)
    t = Table([[cell]], colWidths=[CONTENT_W])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), bg),
        ("LINEBEFORE", (0, 0), (0, -1), 3.2, accent),
        ("LEFTPADDING", (0, 0), (-1, -1), 11),
        ("RIGHTPADDING", (0, 0), (-1, -1), 10),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
    ]))
    return t


def data_table(header, rows, widths=None, aligns=None):
    data = [[Paragraph(h, CELL_HEAD) for h in header]]
    for row in rows:
        data.append([Paragraph(c, CELL) for c in row])
    if widths is None:
        widths = [CONTENT_W / len(header)] * len(header)
    style = [
        ("BACKGROUND", (0, 0), (-1, 0), INDIGO_700),
        ("GRID", (0, 0), (-1, -1), 0.5, SLATE_200),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [white, SLATE_50]),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 5.5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5.5),
    ]
    t = Table(data, colWidths=widths, repeatRows=1)
    t.setStyle(TableStyle(style))
    return t


# ----------------------------------------------------------------------------
# Story
# ----------------------------------------------------------------------------

story = []
story.append(NextPageTemplate("content"))
story.append(PageBreak())

# --- Table of contents ------------------------------------------------------
story.append(Paragraph("Contents", TOC_TITLE))
toc = TableOfContents()
toc.levelStyles = [TOC0, TOC1]
toc.dotsMinLevel = 0
story.append(toc)
story.append(PageBreak())

# --- 1. Welcome -------------------------------------------------------------
story += section("1.  Welcome to SYNAPSE",
                 "SYNAPSE is a production-grade, <b>multi-tenant</b> school "
                 "management platform. Independent schools share one "
                 "application and one database, while every school's data — "
                 "students, teachers, classes, subjects, grades, requests, "
                 "documents, announcements and notifications — stays "
                 "completely isolated from every other school. Authorization "
                 "is always enforced on the server: the interface only ever "
                 "shows what your role is allowed to do.")

story.append(p("This guide covers everything an end user can do in SYNAPSE: "
               "signing in, working with grades and report cards, timetables, "
               "attendance, exams, requests and documents, announcements, "
               "subscriptions and billing. Pick the chapter that matches your "
               "role and follow it step by step."))

story.append(h3("What SYNAPSE manages for your school"))
story += bullets([
    "<b>Academic structure</b> — academic years, semesters, classes and subjects.",
    "<b>People</b> — student and teacher profiles, enrollments and teaching assignments.",
    "<b>Grades</b> — per-subject grade entry on a 0–20 scale, term averages and ranked report cards.",
    "<b>Timetables</b> — a weekly schedule (days 1–5) for every class.",
    "<b>Attendance</b> — daily present / absent / late / excused records per class.",
    "<b>Exams</b> — scheduled exam sessions with date, time and room, plus result compilation.",
    "<b>Requests &amp; documents</b> — certificates, transcripts and recommendation letters with a tracked lifecycle.",
    "<b>Announcements &amp; notifications</b> — targeted school communication with an unread counter.",
    "<b>Subscriptions &amp; billing</b> — plans, usage limits, trials, upgrades and payments.",
    "<b>White-label branding</b> — each school keeps its own name, logo and brand colour.",
])

story.append(h3("Who this guide is for"))
story.append(data_table(
    ["Audience", "What they do in SYNAPSE", "Start here"],
    [
        ["Students", "Follow their own grades, report card, timetable, attendance, requests, documents and exams.", "Chapter 4"],
        ["Teachers", "View their assigned classes, enter grades, take attendance and see scheduled exams.", "Chapter 5"],
        ["School administrators", "Run one school: structure, people, timetable, exams, requests, billing and settings.", "Chapter 6"],
        ["Super admins", "Run the platform: schools, subscription plans, subscriptions and payments.", "Chapter 7"],
    ],
    widths=[88, 315, 92],
))

story.append(Spacer(1, 6))
story.append(callout(
    "All screens described here use the same visual language: an indigo "
    "sidebar with your role's menu, a top bar with your account and the "
    "notification bell, and the main content area. Menu labels in this guide "
    "match the sidebar labels exactly.", kind="tip",
    title="Tip — finding your way around"))

# --- 2. Getting started -----------------------------------------------------
story += section("2.  Getting Started",
                 "SYNAPSE runs entirely in your web browser — there is "
                 "nothing to install. This chapter covers accessing the "
                 "platform, signing in, the demo accounts and demo mode.")

story.append(h2("2.1  Accessing SYNAPSE"))
story.append(p("Open the SYNAPSE address in a modern browser (Chrome, "
               "Firefox, Edge or Safari). The public landing page introduces "
               "the product; from there you can <b>sign in</b> to an existing "
               "account, or <b>register a new school</b> through the "
               "onboarding flow (Chapter 11). Each school also has its own "
               "branded sign-in page at <font face='DejaVuMono' "
               "color='#4338CA'>/school/&lt;slug&gt;</font>, which shows the "
               "school's logo, name and colours."))
story.append(p("When you run SYNAPSE locally for development, the app is "
               "served at:", BODY))
story.append(Paragraph("http://localhost:5173", CODE))

story.append(h2("2.2  Signing in"))
story += bullets([
    "Enter the <b>email address and password</b> your school administrator gave you.",
    "After a successful login you are redirected automatically to the dashboard that matches your role: <font face='DejaVuMono' color='#4338CA'>/student</font>, <font face='DejaVuMono' color='#4338CA'>/teacher</font>, <font face='DejaVuMono' color='#4338CA'>/admin</font> or <font face='DejaVuMono' color='#4338CA'>/super-admin</font>.",
    "If the credentials are wrong, an error is shown — contact your school administrator if the problem persists (administrators can reset accounts; passwords are stored as secure hashes, never in plain text).",
    "Use the <b>Sign out</b> control in the top bar when you are done. Signing out revokes your session token on the server and clears it from the browser.",
], numbered=True)

story.append(h2("2.3  Demo accounts"))
story.append(p("A fresh installation is seeded with demo accounts for every "
               "role. All of them use the password "
               "<font face='DejaVuMono' color='#4338CA'>password123</font>:"))
story.append(data_table(
    ["Role", "Email", "School"],
    [
        ["Super Admin", "superadmin@synapse.test", "Platform"],
        ["Administrator", "admin@synapse.test", "AICS Cameroon"],
        ["Teacher", "teacher@synapse.test", "AICS Cameroon"],
        ["Student", "student@synapse.test", "AICS Cameroon"],
        ["Administrator", "admin.saintalbert@synapse.test", "Saint Albert (trial)"],
        ["Teacher", "teacher.saintalbert@synapse.test", "Saint Albert (trial)"],
        ["Student", "student.saintalbert@synapse.test", "Saint Albert (trial)"],
        ["Administrator", "admin.demo@synapse.test", "Demo Intl (expired)"],
    ],
    widths=[105, 245, 145],
))
story.append(Spacer(1, 4))
story.append(callout(
    "These accounts exist to explore the platform. Change their passwords or "
    "remove them before using SYNAPSE with real data.", kind="warn",
    title="Demo accounts only"))

story.append(h2("2.4  Demo mode (without a backend)"))
story.append(p("If the Laravel backend is not running, the frontend can run "
               "in <b>mock mode</b>: setting "
               "<font face='DejaVuMono' color='#4338CA'>VITE_USE_MOCK=true</font> "
               "in <font face='DejaVuMono' color='#4338CA'>frontend/.env</font> "
               "installs an in-browser adapter that reproduces the exact API "
               "responses of the real backend — including tenant isolation, "
               "subscription enforcement and plan limits. Set it to "
               "<font face='DejaVuMono' color='#4338CA'>false</font> to talk "
               "to the real API; no other change is needed."))

story.append(h2("2.5  Subscription banners"))
story.append(p("If your school's plan is in a trial, expired or over-limit "
               "state, a banner appears at the top of the workspace with the "
               "reason and the relevant action (renew, upgrade…). Billing "
               "always stays reachable, so a school can renew even when "
               "academic features are blocked. See Chapter 10."))

# --- 3. Roles & permissions -------------------------------------------------
story += section("3.  Roles &amp; Permissions",
                 "SYNAPSE has four roles in a strict hierarchy. Every menu, "
                 "page and API endpoint is guarded by the server, so a user "
                 "can never see or change data that does not belong to their "
                 "role — or their school.")

story.append(data_table(
    ["Role", "Scope", "Typical responsibilities"],
    [
        ["Super Admin", "The whole platform",
         "Manages schools (activate / suspend / trial / expire), subscription plans, subscriptions and payments; sees platform-wide statistics."],
        ["Administrator", "One school",
         "Manages academic years, classes, subjects, students, teachers, teaching assignments, timetables, exams, requests, announcements, billing and school settings."],
        ["Teacher", "Their own assignments",
         "Sees only the classes and subjects they are assigned to; enters grades, takes attendance and views exams for those classes."],
        ["Student", "Their own records",
         "Sees their own grades, report card, timetable, attendance, transcript, requests, documents and exams."],
    ],
    widths=[95, 115, 285],
))
story.append(Spacer(1, 6))
story += bullets([
    "<b>Tenant isolation</b> — every tenant-owned record carries the school's identity; queries are scoped automatically, so one school can never read another school's data.",
    "<b>Teacher assignments are the source of truth</b> — a teacher can only view or save grades for a class after an administrator created a teaching assignment (teacher → subject → class → academic year) for them.",
    "<b>Students see only their own rows</b> — grades, documents and notifications are additionally scoped to the signed-in student.",
    "<b>Wrong-role access is blocked</b> — opening another role's page shows an “Access denied” screen instead of data.",
])

# --- 4. Student guide -------------------------------------------------------
story += section("4.  The Student Guide",
                 "As a student you follow your own academic life: grades, "
                 "report card, timetable, attendance, transcript, exams, "
                 "requests and documents. Everything you see is yours — "
                 "other students' data is never visible to you.")

story.append(h2("4.1  Dashboard"))
story.append(p("Your home page. It greets you by name and shows the "
               "highlights of your school life: summary statistics (subjects, "
               "current average and more), today's timetable, and recent "
               "announcements targeted at students."))

story.append(h2("4.2  My Grades"))
story.append(p("Lists your grades per subject and class, grouped by "
               "semester. Grades follow the school's 0–20 scale and the "
               "average is computed from the components your teacher has "
               "entered (see Chapter 9)."))

story.append(h2("4.3  Report Card"))
story.append(p("Your official term results: each subject's average, the "
               "class average and your <b>rank</b> in class. Switch semesters "
               "with the tabs at the top."))
story.append(callout(
    "The Report Card and Documents menu items only appear when your school's "
    "plan enables those features. If you don't see them, the feature is "
    "turned off for your school — ask your administrator.", kind="info",
    title="Feature-dependent menus"))

story.append(h2("4.4  Timetable"))
story.append(p("Your weekly schedule as a grid, organised by day (1–5) and "
               "period, with the subject and time of each slot."))

story.append(h2("4.5  Requests"))
story.append(p("Ask the school for an official document — a "
               "<b>Certificate of Enrollment</b>, a <b>Transcript Request</b>, "
               "a <b>Recommendation Letter</b> or something else — by "
               "submitting a request with a type and a reason. Each request "
               "gets a reference number (<font face='DejaVuMono' "
               "color='#4338CA'>REQ-…</font>) and a visual stepper that shows "
               "its progress: <b>Submitted → Under Review → Approved → "
               "Ready</b>. When the administrator generates the document, the "
               "request becomes <b>Ready</b> and you can download it from "
               "Documents. If it is rejected, the administrator's note "
               "explains why."))

story.append(h2("4.6  Documents"))
story.append(p("The official documents generated for your approved "
               "requests. Once a document is ready, download it from here."))

story.append(h2("4.7  Attendance"))
story.append(p("Your attendance record, date by date. Each day is marked "
               "<b>Present</b>, <b>Absent</b>, <b>Late</b> or "
               "<b>Excused</b> with its status colour."))

story.append(h2("4.8  Transcript"))
story.append(p("Your complete academic record across the years you have "
               "spent at the school — past enrollments are preserved, so "
               "your history is never lost when the school moves to a new "
               "academic year."))

story.append(h2("4.9  Exams"))
story.append(p("The exam sessions scheduled for your class: subject, date, "
               "start and end time, and room. Use it to plan your "
               "revision."))

story.append(h2("4.10  Announcements"))
story.append(p("All announcements published for students, newest first. "
               "Announcements addressed to “all” audiences also appear "
               "here."))

# --- 5. Teacher guide -------------------------------------------------------
story += section("5.  The Teacher Guide",
                 "Teachers work only with the classes and subjects the "
                 "administrator assigned to them. Every list, grade form and "
                 "attendance roster is filtered by your teaching assignments "
                 "for the current academic year.")

story.append(h2("5.1  Dashboard"))
story.append(p("A summary of your teaching load: your current assignments, "
               "upcoming classes and recent activity."))

story.append(h2("5.2  My Assignments"))
story.append(p("The list of (subject, class, academic year) combinations you "
               "teach. Each assignment opens the class view. If a class is "
               "missing, the administrator has not created the teaching "
               "assignment yet."))

story.append(h2("5.3  Grade Entry"))
story += bullets([
    "Pick one of your assignments (subject → class) and a semester.",
    "The roster of that class appears with a score field per student, per grade component — by default <b>Test 1</b>, <b>Test 2</b> and <b>Exam</b> (the school can define its own components with weights, see 6.2).",
    "Enter scores on the 0–20 scale. The average is computed automatically from the components you filled in.",
    "Save. The grade is recorded with your identity and the class, subject and academic year — one grade per student, subject, class and year.",
])
story.append(callout(
    "Grade access is checked twice: the page only lists assigned classes, "
    "and the server refuses any save for a class without a valid teaching "
    "assignment. You can never see or edit another teacher's classes.",
    kind="info", title="Assignment enforcement"))

story.append(h2("5.4  Class view &amp; Gradebook"))
story.append(p("Inside a class you find the student roster and the "
               "gradebook: per-student grades across the semester, with "
               "averages. Use it to review your class's progress before "
               "exams or report cards."))

story.append(h2("5.5  Attendance"))
story.append(p("Take attendance for your assigned classes: pick the class "
               "and date, then mark each student <b>Present</b>, "
               "<b>Absent</b>, <b>Late</b> or <b>Excused</b>. The status "
               "buttons are colour-coded; unmarked students stay unmarked "
               "until you save."))

story.append(h2("5.6  Exams"))
story.append(p("The exam sessions scheduled for your subjects and classes: "
               "date, time and room, so you always know where you are "
               "expected to invigilate."))

story.append(h2("5.7  Announcements"))
story.append(p("Announcements addressed to teachers — school-wide messages "
               "and everything published for the “teachers” audience."))

# --- 6. Administrator guide -------------------------------------------------
story += section("6.  The Administrator Guide",
                 "The school administrator runs one school. This chapter "
                 "walks through every management area, in the order you will "
                 "typically use them when setting up a school.")

story.append(h2("6.1  Dashboard"))
story.append(p("The school at a glance: counts of students, teachers, "
               "classes and subjects, plus recent activity across the "
               "school."))

story.append(h2("6.2  Academic Structure"))
story.append(p("The foundation everything else builds on. Set it up before "
               "adding people:"))
story += bullets([
    "<b>Academic years</b> — create years with start and end dates and mark exactly one as the <b>current</b> year. New enrollments and assignments belong to the current year.",
    "<b>Semesters</b> — the school's term structure (2 semesters, 3 terms or 4 quarters). Grades and report cards are grouped by semester.",
    "<b>Classes</b> — the classes of your school (e.g. “Form 1 A”).",
    "<b>Subjects</b> — the subjects taught (name and code).",
    "<b>Grading components</b> — define how each subject's average is built: name (e.g. “Assignments”), weight % and optionally a specific subject. The defaults are Test 1, Test 2 and Exam.",
])

story.append(h2("6.3  Students"))
story.append(p("Register students and manage their profiles. A student is a "
               "user account plus a school profile; the key action is the "
               "<b>enrollment</b> (student → class → academic year). One "
               "enrollment per student, class and year — and history is "
               "preserved, so a student's past classes are never deleted "
               "when years change."))

story.append(h2("6.4  Teachers"))
story.append(p("Register teachers, then create their <b>teaching "
               "assignments</b>: teacher → subject → class → academic year. "
               "An assignment is what unlocks the class for that teacher — "
               "without it they cannot see the class or enter grades. One "
               "assignment per (teacher, subject, class, year) combination."))

story.append(h2("6.5  Timetable"))
story.append(p("Build the weekly timetable per class: pick a class and "
               "academic year, then place subjects on days 1–5 with start "
               "and end times. The edit view lets you add, move or remove "
               "slots; students and teachers see the result in their "
               "timetables immediately."))

story.append(h2("6.6  Attendance"))
story.append(p("The school-wide attendance view: pick a class and date to "
               "see and edit the roster's statuses (Present, Absent, Late, "
               "Excused), with a live count of each status."))

story.append(h2("6.7  Exams"))
story += bullets([
    "<b>Schedule</b> — create exam sessions: class, subject, semester, date, start and end time, and room. Sessions appear in the exam timetable and can be removed again.",
    "<b>Result compilation</b> — rank students by term average per class and subject to prepare report cards and merit lists.",
])

story.append(h2("6.8  Bulk Import"))
story.append(p("Add many students or teachers at once from a CSV file:"))
story += bullets([
    "Choose the import type (students or teachers) and download the CSV template.",
    "Fill the template — columns are <font face='DejaVuMono' color='#4338CA'>name</font>, <font face='DejaVuMono' color='#4338CA'>email</font> and the fields shown in the template.",
    "Upload the file. SYNAPSE validates every row and reports parse or validation errors before anything is created.",
], numbered=True)

story.append(h2("6.9  Requests"))
story.append(p("The school's request queue. Administrators drive every "
               "transition of the lifecycle:"))
story.append(data_table(
    ["You see…", "Action", "Result"],
    [
        ["A request in <b>Submitted</b> state", "Start the review", "Status becomes <b>Under Review</b>; the student is notified."],
        ["A request <b>Under Review</b>", "Approve or reject (with a note)", "Approved → <b>Approved</b>. Rejected → <b>Rejected</b>; the student sees your note."],
        ["An <b>Approved</b> request", "Generate document", "The official document is created, the status becomes <b>Ready</b> and the student can download it."],
    ],
    widths=[165, 120, 210],
))

story.append(h2("6.10  Announcements"))
story.append(p("Publish announcements to your school with an audience: "
               "<b>everyone</b>, <b>students only</b> or <b>teachers "
               "only</b>. Publishing notifies the right users instantly; "
               "each audience only ever sees announcements meant for it."))

story.append(h2("6.11  Billing"))
story.append(p("Your school's subscription, in one page:"))
story += bullets([
    "<b>Current plan</b> — plan name, status (trial / active / expired / …) and the next renewal or trial-end date.",
    "<b>Usage vs limits</b> — progress bars for students, teachers and classes against your plan's limits.",
    "<b>Renew</b> — renew the current plan (mock payment in development).",
    "<b>Available plans</b> — upgrade at any time; limits take effect immediately.",
    "<b>Payment history</b> — every payment for the school.",
])
story.append(callout(
    "Plan limits are enforced on the server: when the school reaches its "
    "student, teacher or class limit, the API rejects further creation with "
    "a clear “upgrade required” message, and the banner on top of the "
    "workspace shows the same information.", kind="info",
    title="How limits work"))

story.append(h2("6.12  Settings"))
story.append(p("School settings, grouped in two cards:"))
story += bullets([
    "<b>Branding</b> — school logo, school name and brand colour, plus a switch to enable custom branding on the school's portal and sign-in pages.",
    "<b>Academics</b> — grading scale, semester structure (2 semesters / 3 terms / 4 quarters) and timezone.",
])

# --- 7. Super admin ---------------------------------------------------------
story += section("7.  The Super Admin Guide",
                 "The Super Admin runs the SYNAPSE platform itself — not a "
                 "single school. This chapter covers the platform dashboard, "
                 "school management, plans, subscriptions and payments.")

story.append(h2("7.1  Platform Dashboard"))
story.append(p("Platform-wide statistics: total schools, total users, "
               "students and active subscriptions, the distribution of "
               "schools across plans, and the breakdown of school statuses "
               "(<b>Active</b>, <b>Trial</b>, <b>Suspended</b>, "
               "<b>Expired</b>)."))

story.append(h2("7.2  Schools"))
story.append(p("Create tenant schools (name, slug, email, phone) or open a "
               "school's detail page. From the detail page you control the "
               "school's status — <b>activate</b>, <b>suspend</b>, put it "
               "back on <b>trial</b> or <b>expire</b> it — and inspect its "
               "users and subscription. Suspended or expired schools are "
               "blocked from academic operations but can still reach "
               "billing to renew."))

story.append(h2("7.3  Subscription Plans"))
story.append(p("Plans are fully configurable — never hard-coded. Each plan "
               "has a name, slug, price, billing interval (monthly/yearly), "
               "currency and limits (maximum students, teachers, classes). "
               "Plans also carry <b>features</b> that gate functionality per "
               "school (e.g. report cards, document management)."))
story.append(callout(
    "Changing a plan's limits or features affects every school on that plan "
    "immediately — both the interface and the server-side enforcement read "
    "the current plan.", kind="warn", title="Plans apply immediately"))

story.append(h2("7.4  Subscriptions"))
story.append(p("Every subscription across the platform, per school: plan, "
               "status (<font face='DejaVuMono' color='#4338CA'>trial</font>, "
               "<font face='DejaVuMono' color='#4338CA'>active</font>, "
               "<font face='DejaVuMono' color='#4338CA'>past_due</font>, "
               "<font face='DejaVuMono' color='#4338CA'>cancelled</font>, "
               "<font face='DejaVuMono' color='#4338CA'>expired</font>, "
               "<font face='DejaVuMono' color='#4338CA'>suspended</font>), "
               "dates and amounts. Use it to audit revenue (MRR) and plan "
               "adoption."))

story.append(h2("7.5  Payments"))
story.append(p("The platform-wide payment ledger: every payment with its "
               "school, subscription, amount, gateway and status. "
               "Development payments are flagged as sandbox and are never "
               "counted as production revenue."))

# --- 8. Requests end-to-end -------------------------------------------------
story += section("8.  Requests &amp; Documents — End to End",
                 "This chapter follows one request through the whole "
                 "lifecycle, so both students and administrators know "
                 "exactly what happens at each step.")

story.append(data_table(
    ["Step", "Who acts", "What happens"],
    [
        ["1 — Submitted", "Student",
         "The student creates a request (type + reason). It gets a REQ-… reference, the status is <b>submitted</b>, and the school's administrators are notified."],
        ["2 — Under Review", "Administrator",
         "The administrator starts the review: status becomes <b>under_review</b>; the student is notified."],
        ["3 — Approved", "Administrator",
         "The administrator approves (or rejects with a note). <b>Approved</b> notifies the student; <b>Rejected</b> ends the lifecycle with the note attached."],
        ["4 — Ready", "Administrator",
         "For an approved request, the administrator generates the official document. The status becomes <b>ready</b>, the student is notified, and the document appears in the student's Documents page for download."],
    ],
    widths=[95, 100, 300],
))
story.append(Spacer(1, 6))
story.append(p("On the student's side, a visual stepper (Submitted → Under "
               "Review → Approved → Ready) shows exactly where the request "
               "stands at any moment."))

# --- 9. Grading model -------------------------------------------------------
story += section("9.  Grades &amp; the Grading Model",
                 "Grades in SYNAPSE follow a simple, auditable model on the "
                 "school's 0–20 scale. Understanding it makes grade entry "
                 "and report cards predictable.")

story += bullets([
    "<b>One grade per (student, subject, class, academic year)</b> — grades never overwrite history across years.",
    "<b>Components</b> — a grade is built from components; by default <b>Test 1</b>, <b>Test 2</b> and <b>Exam</b>. The school can define its own components with weights (6.2).",
    "<b>Average</b> — the mean of the components that have been entered, rounded to two decimals. Empty fields are ignored, so you can save partial grades.",
    "<b>Who entered it</b> — every grade records the teacher who entered it, alongside class, subject and year.",
    "<b>Report cards</b> — term averages per subject, the class average, and the student's rank in class.",
])

story.append(data_table(
    ["Average (0–20)", "Meaning in the UI"],
    [
        ["16 and above", "Shown green — excellent result"],
        ["12 to 15.9", "Shown blue — good result"],
        ["Below 12", "Shown amber — needs improvement"],
    ],
    widths=[130, 365],
))

story.append(h2("9.1  Who can enter grades"))
story.append(p("Only a teacher with a valid teaching assignment for that "
               "subject, class and academic year. The check is enforced in "
               "the backend on every save — even if the page were bypassed, "
               "the API would refuse."))

# --- 10. Subscriptions & billing --------------------------------------------
story += section("10.  Subscriptions &amp; Billing",
                 "Every school runs on a subscription plan. This chapter "
                 "explains plans, trials, limits, enforcement and payments.")

story.append(h3("Plans and trials"))
story += bullets([
    "<b>Plans</b> define price, interval, currency, limits and features; the super admin configures them (7.3).",
    "<b>Trials</b> — every new school starts on a free trial (configurable, 14 days by default).",
    "<b>Status</b> — a subscription can be trial, active, past_due, cancelled, expired or suspended; the school's status mirrors its subscription health.",
])

story.append(h3("Enforcement"))
story += bullets([
    "<b>Limits</b> — creating students, teachers or classes beyond the plan's limit returns a clear “upgrade required” error.",
    "<b>Academic block</b> — expired or suspended schools are blocked from academic operations, but <b>billing stays reachable</b> so they can renew.",
    "<b>Features</b> — plan features gate both the menus (report cards, documents) and the underlying API.",
])

story.append(h3("Payments (Cameroon-first)"))
story.append(p("Payments go through a provider abstraction with four "
               "gateways: <b>Mock</b> (development only — always marked "
               "sandbox), <b>MTN Mobile Money</b>, <b>Orange Money</b> and "
               "<b>Card</b>. Real gateways refuse to charge until genuine "
               "credentials are configured, so no fake production payments "
               "can ever occur."))

# --- 11. Onboarding ---------------------------------------------------------
story += section("11.  Onboarding a New School",
                 "A new school joins SYNAPSE through a public four-step "
                 "onboarding wizard. This chapter describes the wizard and "
                 "the administrator's first-day checklist.")

story.append(data_table(
    ["Step", "What you provide"],
    [
        ["1 — School information", "School name, URL slug, email, phone and address."],
        ["2 — Administrator account", "The first administrator's name, email and password (at least 8 characters)."],
        ["3 — Plan", "Pick a subscription plan; the wizard shows what each plan includes."],
        ["4 — Free trial", "The school starts on its free trial and the administrator signs in to set everything up."],
    ],
    widths=[160, 335],
))
story.append(Spacer(1, 6))
story.append(h3("First-day checklist for the new administrator"))
story += bullets([
    "Open <b>Academic Structure</b> (6.2) and create the current academic year, semesters, classes and subjects.",
    "Add students and teachers — individually (6.3, 6.4) or via CSV import (6.8).",
    "Create <b>teaching assignments</b> so teachers can access their classes (6.4).",
    "Build the <b>timetable</b> (6.5) and check it from a student and teacher account.",
    "Review <b>Settings</b> (6.12): grading scale, semester structure, timezone and branding.",
    "Publish a welcome <b>announcement</b> (6.10) so users see the notification system working.",
])

# --- 12. Announcements & notifications --------------------------------------
story += section("12.  Announcements &amp; Notifications",
                 "Communication is built into every workflow: whenever "
                 "something happens that concerns you, SYNAPSE notifies you.")

story += bullets([
    "<b>Announcements</b> are published by administrators with an audience: everyone, students or teachers. Each audience only sees what is meant for them.",
    "<b>Notifications</b> are personal: the bell in the top bar shows your unread count; open it to read and act on each notification or mark all as read.",
    "<b>Events that notify</b> — a request submitted, a request status change, a document ready for download, and every published announcement.",
])

# --- 13. Privacy & isolation -------------------------------------------------
story += section("13.  Privacy &amp; Data Isolation",
                 "SYNAPSE is multi-tenant by design. Schools share the "
                 "application, but nothing leaks between them.")

story += bullets([
    "<b>School isolation</b> — every tenant-owned record is tied to its school and every query is scoped automatically; a user of school A can never read school B's data, by accident or on purpose.",
    "<b>Role isolation</b> — the hierarchy super admin → administrator → teacher → student strictly limits what each role can do (Chapter 3).",
    "<b>Personal isolation</b> — students only ever see their own grades, documents and notifications; teachers only their assigned classes.",
    "<b>Audit trails</b> — school, onboarding, subscription and payment events are recorded in audit logs for accountability.",
    "<b>Secure by default</b> — passwords are stored as hashes, sessions use revocable tokens, and payments are sandboxed until real credentials are configured.",
])

# --- 14. Troubleshooting ----------------------------------------------------
story += section("14.  Troubleshooting &amp; FAQ")

story.append(h3("I signed in but landed on the wrong page"))
story.append(p("You are redirected to your role's dashboard. If that role "
               "is wrong (for example you are a teacher but land on the "
               "student dashboard), ask your administrator or super admin "
               "to correct your account."))

story.append(h3("I see “Access denied” (403)"))
story.append(p("You opened a page that belongs to another role. Use the "
               "sidebar links of your own role — for example, teachers use "
               "<font face='DejaVuMono' color='#4338CA'>/teacher/…</font> "
               "pages, never <font face='DejaVuMono' "
               "color='#4338CA'>/admin/…</font> pages."))

story.append(h3("A teacher cannot see a class they teach"))
story.append(p("The administrator must create a <b>teaching assignment</b> "
               "(teacher → subject → class → academic year) for the current "
               "academic year. Without it the class will not appear for the "
               "teacher (6.4)."))

story.append(h3("“Upgrade required” when adding students or teachers"))
story.append(p("The school has reached its plan's limit. The administrator "
               "can upgrade in <b>Billing</b> (6.11); limits take effect "
               "immediately."))

story.append(h3("A banner says the subscription has expired"))
story.append(p("Academic operations are blocked for expired or suspended "
               "schools, but billing remains reachable: the administrator "
               "renews in <b>Billing</b> (6.11) and everything unlocks "
               "again."))

story.append(h3("The Report Card or Documents menu is missing"))
story.append(p("Those menus are feature-gated by the school's plan. If the "
               "plan does not include them, they are hidden — and the API "
               "refuses them too (10)."))

story.append(h3("Grades, timetable or attendance look empty"))
story.append(p("Most pages follow the current academic year and semester. "
               "Check the selected year/semester, and that the administrator "
               "has created the structure, enrollments and assignments "
               "(6.2–6.5)."))

story.append(h3("I forgot my password"))
story.append(p("Contact your school administrator (students, teachers) or "
               "the platform super admin (administrators) to reset your "
               "account. Passwords are stored as secure hashes and can "
               "never be recovered in plain text."))

story.append(h3("Is this the real data or the demo?"))
story.append(p("If the frontend runs with "
               "<font face='DejaVuMono' color='#4338CA'>VITE_USE_MOCK=true</font> "
               "you are in mock mode (2.4): an in-browser adapter replays "
               "realistic responses without a backend. Set it to "
               "<font face='DejaVuMono' color='#4338CA'>false</font> and "
               "start the Laravel API to work with real data."))

# --- 15. Quick reference ----------------------------------------------------
story += section("15.  Quick Reference")

story.append(h3("Dashboards by role"))
story.append(data_table(
    ["Role", "Dashboard path"],
    [
        ["Super Admin", "/super-admin"],
        ["Administrator", "/admin"],
        ["Teacher", "/teacher"],
        ["Student", "/student"],
    ],
    widths=[170, 325],
))
story.append(Spacer(1, 8))

story.append(h3("Key terminology"))
story.append(data_table(
    ["Term", "Meaning"],
    [
        ["School status", "active · trial · suspended · expired"],
        ["Subscription status", "trial · active · past_due · cancelled · expired · suspended"],
        ["Request status", "submitted → under_review → approved → ready (or rejected)"],
        ["Attendance status", "present · absent · late · excused"],
        ["Grading scale", "0–20; averages are the mean of entered components"],
        ["Teaching assignment", "teacher → subject → class → academic year; unlocks grade entry"],
        ["Enrollment", "student → class → academic year; preserved across years"],
    ],
    widths=[150, 345],
))
story.append(Spacer(1, 10))
story.append(callout(
    "This guide mirrors the SYNAPSE interface. If a screen differs from what "
    "you see, you are likely running a different version — check the "
    "changelog or ask your administrator.", kind="info",
    title="Version note"))

# ----------------------------------------------------------------------------
# Build
# ----------------------------------------------------------------------------

doc = GuideDoc(
    OUT,
    pagesize=A4,
    title="SYNAPSE User Guide",
    author="SYNAPSE",
    subject="User guide for the SYNAPSE multi-tenant school management platform",
    keywords="SYNAPSE, school management, user guide, SaaS, multi-tenant",
    creator="SYNAPSE",
    leftMargin=M, rightMargin=M, topMargin=62, bottomMargin=56,
)

doc.addPageTemplates([
    PageTemplate(id="cover", frames=[Frame(M, 60, CONTENT_W, PAGE_H - 130,
                                           id="cover-frame", leftPadding=0,
                                           rightPadding=0, topPadding=0,
                                           bottomPadding=0)],
                 onPage=draw_cover, pagesize=A4),
    PageTemplate(id="content", frames=[FRAME], onPage=draw_content_page,
                 pagesize=A4),
])

doc.multiBuild(story)
print("Wrote %s (%d pages)" % (OUT, doc.page))
