#!/usr/bin/env python3
"""
Génération du PDF de présentation — Projet SOA API REST Bibliothèque
Format 16:9 (1280x720 pts) — style sombre professionnel
"""

from reportlab.lib.pagesizes import landscape
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, HRFlowable, PageBreak, KeepTogether
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfgen import canvas
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT
from reportlab.platypus.flowables import Flowable
import io

# ── Page size 16:9 ──────────────────────────────────────────
W, H = 297*mm, 167*mm   # A4 paysage ~16:9

# ── Palette ─────────────────────────────────────────────────
BG        = colors.HexColor('#0f1419')   # Noir bleu
SURFACE   = colors.HexColor('#1a2332')   # Carte
ACCENT    = colors.HexColor('#e8703a')   # Orange cuivre
ACCENT2   = colors.HexColor('#f0a570')   # Orange clair
GREEN     = colors.HexColor('#4ade80')   # Vert succès
RED       = colors.HexColor('#f87171')   # Rouge erreur
BLUE      = colors.HexColor('#60a5fa')   # Bleu info
PURPLE    = colors.HexColor('#c084fc')   # Violet
YELLOW    = colors.HexColor('#fbbf24')   # Jaune
WHITE     = colors.HexColor('#f0ebe0')   # Blanc parchemin
GREY      = colors.HexColor('#64748b')   # Gris
LGREY     = colors.HexColor('#1e2d40')   # Fond cellule

# ── Fonts (built-in) ────────────────────────────────────────
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

TITLE_F = 'Helvetica-Bold'
BODY_F  = 'Helvetica'
CODE_F  = 'Courier'
BOLD_F  = 'Helvetica-Bold'


# ════════════════════════════════════════════════════════════
#  CANVAS BACKGROUND HELPER
# ════════════════════════════════════════════════════════════
def bg_page(canvas, doc):
    """Fond sombre + numéro de slide sur toutes les pages."""
    canvas.saveState()
    canvas.setFillColor(BG)
    canvas.rect(0, 0, W, H, fill=1, stroke=0)
    # Numéro de page
    n = doc.page
    if n > 1:
        canvas.setFillColor(GREY)
        canvas.setFont(BODY_F, 8)
        canvas.drawRightString(W - 16*mm, 8*mm, f"{n}")
    canvas.restoreState()


# ════════════════════════════════════════════════════════════
#  CUSTOM FLOWABLES
# ════════════════════════════════════════════════════════════
class SlideTitle(Flowable):
    """Bloc titre de slide : accent bar + titre + sous-titre."""
    def __init__(self, title, subtitle='', w=W-32*mm):
        Flowable.__init__(self)
        self.title    = title
        self.subtitle = subtitle
        self.w        = w
        self.height   = 36*mm if subtitle else 26*mm

    def draw(self):
        c = self.canv
        # Barre accent
        c.setFillColor(ACCENT)
        c.rect(0, self.height-5, 40*mm, 4, fill=1, stroke=0)
        # Titre
        c.setFillColor(WHITE)
        c.setFont(TITLE_F, 28)
        c.drawString(0, self.height-22, self.title)
        # Sous-titre
        if self.subtitle:
            c.setFillColor(ACCENT2)
            c.setFont(BODY_F, 13)
            c.drawString(0, self.height-38, self.subtitle)


class CoverSlide(Flowable):
    """Slide de couverture complète."""
    def __init__(self):
        Flowable.__init__(self)
        self.width  = W
        self.height = H

    def draw(self):
        c = self.canv
        # Fond
        c.setFillColor(BG)
        c.rect(0, 0, W, H, fill=1, stroke=0)
        # Bande accent gauche
        c.setFillColor(ACCENT)
        c.rect(0, 0, 8*mm, H, fill=1, stroke=0)
        # Cercles décoratifs
        c.setFillColor(colors.HexColor('#1a2332'))
        c.circle(W*0.78, H*0.5, 55*mm, fill=1, stroke=0)
        c.setFillColor(colors.HexColor('#243040'))
        c.circle(W*0.78, H*0.5, 40*mm, fill=1, stroke=0)
        c.setFillColor(ACCENT)
        c.circle(W*0.78, H*0.5, 28*mm, fill=1, stroke=0)
        c.setFillColor(BG)
        c.circle(W*0.78, H*0.5, 20*mm, fill=1, stroke=0)
        # Icône ASCII
        c.setFillColor(ACCENT)
        c.setFont(TITLE_F, 26)
        c.drawCentredString(W*0.78, H*0.5-9, "API")

        # Tag
        c.setFillColor(ACCENT)
        c.roundRect(16*mm, H-22*mm, 55*mm, 8*mm, 2*mm, fill=1, stroke=0)
        c.setFillColor(BG)
        c.setFont(BOLD_F, 8.5)
        c.drawString(19*mm, H-17.5*mm, "MASTER 1 SYSTEMES D'INFORMATION")

        # Titre principal
        c.setFillColor(WHITE)
        c.setFont(TITLE_F, 34)
        c.drawString(16*mm, H*0.62, "API REST")
        c.setFillColor(ACCENT)
        c.setFont(TITLE_F, 34)
        c.drawString(16*mm, H*0.62-38, "Bibliotheque")
        # Sous-titre
        c.setFillColor(colors.HexColor('#94a3b8'))
        c.setFont(BODY_F, 13)
        c.drawString(16*mm, H*0.62-62, "Architecture Orientee Services — PHP 8 / MySQL 8")

        # Ligne séparatrice
        c.setStrokeColor(ACCENT)
        c.setLineWidth(0.5)
        c.line(16*mm, H*0.25, 65*mm, H*0.25)

        # Infos bas
        c.setFillColor(GREY)
        c.setFont(BODY_F, 9)
        c.drawString(16*mm, H*0.18, "PHP natif  |  PDO  |  MySQL  |  REST  |  JSON  |  fetch()")


class CodeBlock(Flowable):
    """Bloc de code avec fond coloré."""
    def __init__(self, lines, w=None, lang='php', fontsize=7.5):
        Flowable.__init__(self)
        self.lines    = lines if isinstance(lines, list) else lines.split('\n')
        self.w        = w or (W - 32*mm)
        self.fontsize = fontsize
        self.lang     = lang
        lh            = fontsize * 1.55
        self.height   = len(self.lines) * lh + 12

    def draw(self):
        c    = self.canv
        lh   = self.fontsize * 1.55
        h    = len(self.lines) * lh + 12

        # Fond
        c.setFillColor(colors.HexColor('#0d1a26'))
        c.roundRect(0, 0, self.w, h, 3, fill=1, stroke=0)

        # Dot bar
        for i, col in enumerate([RED, YELLOW, GREEN]):
            c.setFillColor(col)
            c.circle(8 + i*12, h-6, 3, fill=1, stroke=0)

        # Label langue
        c.setFillColor(GREY)
        c.setFont(BODY_F, 6.5)
        c.drawRightString(self.w - 6, h-4, self.lang.upper())

        # Lignes
        KEYWORDS_PHP  = {'<?php','require_once','class','function','public','private','return',
                         'new','if','else','try','catch','foreach','echo','exit','null','true','false',
                         'string','int','array','void','bool','PDO','static','extends'}
        KEYWORDS_SQL  = {'SELECT','INSERT','UPDATE','DELETE','FROM','WHERE','AND','OR','CREATE',
                         'TABLE','DATABASE','NOT','NULL','DEFAULT','AUTO_INCREMENT','PRIMARY','KEY',
                         'ENGINE','CHARSET','INTO','VALUES','SET','LIMIT','ORDER','BY','USE','DROP'}

        for i, line in enumerate(self.lines):
            y = h - 18 - i*lh
            # Numéro de ligne
            c.setFillColor(colors.HexColor('#2d3f50'))
            c.setFont(CODE_F, self.fontsize-1.5)
            c.drawRightString(22, y, str(i+1))
            # Code
            self._draw_line(c, line, 26, y, self.fontsize)

    def _draw_line(self, c, line, x, y, fs):
        """Coloration syntaxique simple."""
        stripped = line.lstrip()
        indent   = len(line) - len(stripped)
        x_pos    = x + indent * (fs * 0.6)

        # Commentaires
        if stripped.startswith('//') or stripped.startswith('#') or stripped.startswith('--'):
            c.setFillColor(GREY)
            c.setFont(CODE_F, fs)
            c.drawString(x_pos, y, stripped)
            return

        # String complète en orange
        if stripped.startswith("'") or stripped.startswith('"'):
            c.setFillColor(ACCENT2)
            c.setFont(CODE_F, fs)
            c.drawString(x_pos, y, stripped)
            return

        # Mots-clés
        first_word = stripped.split('(')[0].split(' ')[0].replace('$','').strip()
        kw_php = {'require_once','class','function','public','private','return','new',
                  'if','else','try','catch','foreach','echo','exit','static','extends','use'}
        kw_sql = {'SELECT','INSERT','UPDATE','DELETE','FROM','WHERE','CREATE','TABLE',
                  'DATABASE','INTO','VALUES','SET','ORDER','BY','LIMIT','USE','DROP'}

        c.setFont(CODE_F, fs)
        if first_word in kw_php:
            c.setFillColor(PURPLE)
        elif first_word in kw_sql:
            c.setFillColor(BLUE)
        elif stripped.startswith('$'):
            c.setFillColor(GREEN)
        elif first_word.startswith('PDO') or first_word == 'new':
            c.setFillColor(YELLOW)
        else:
            c.setFillColor(WHITE)
        c.drawString(x_pos, y, stripped)


class Badge(Flowable):
    """Petit badge coloré inline."""
    def __init__(self, text, bg=ACCENT, fg=BG, w=None, fs=8):
        Flowable.__init__(self)
        self.text   = text
        self.bg     = bg
        self.fg     = fg
        self.fs     = fs
        self.width  = w or (len(text)*fs*0.55 + 14)
        self.height = fs + 8

    def draw(self):
        c = self.canv
        c.setFillColor(self.bg)
        c.roundRect(0, 0, self.width, self.height, 2, fill=1, stroke=0)
        c.setFillColor(self.fg)
        c.setFont(BOLD_F, self.fs)
        c.drawCentredString(self.width/2, 3.5, self.text)


# ════════════════════════════════════════════════════════════
#  STYLES PARAGRAPHE
# ════════════════════════════════════════════════════════════
def S(name, parent='Normal', **kw):
    return ParagraphStyle(name, **kw)

sNormal   = S('N',   fontName=BODY_F,  fontSize=10, textColor=WHITE,   leading=16, spaceAfter=4)
sSmall    = S('SM',  fontName=BODY_F,  fontSize=8.5,textColor=WHITE,   leading=14, spaceAfter=2)
sGrey     = S('G',   fontName=BODY_F,  fontSize=9,  textColor=GREY,    leading=14)
sAccent   = S('A',   fontName=BOLD_F,  fontSize=10, textColor=ACCENT,  leading=16)
sCode     = S('C',   fontName=CODE_F,  fontSize=8,  textColor=ACCENT2, leading=13, backColor=colors.HexColor('#0d1a26'), leftIndent=6, rightIndent=6)
sBullet   = S('B',   fontName=BODY_F,  fontSize=9.5,textColor=WHITE,   leading=15, leftIndent=12, spaceAfter=3)
sH2       = S('H2',  fontName=BOLD_F,  fontSize=13, textColor=ACCENT,  leading=18, spaceBefore=6, spaceAfter=4)
sMethod   = S('M',   fontName=BOLD_F,  fontSize=9,  textColor=BG,      leading=14)


def bul(text, color=ACCENT):
    """Bullet point helper."""
    return Paragraph(f'<font color="#{color.hexval().replace("0x","").replace("#","")}">▸</font>  {text}', sBullet)

def normal(text):
    return Paragraph(text, sNormal)

def small(text):
    return Paragraph(text, sSmall)

def grey(text):
    return Paragraph(text, sGrey)

def h2(text):
    return Paragraph(text, sH2)

def sp(n=4):
    return Spacer(1, n*mm)


# ════════════════════════════════════════════════════════════
#  MÉTHODE TABLE DES ROUTES
# ════════════════════════════════════════════════════════════
def route_table():
    data = [
        [
            Paragraph('<b>Méthode</b>', sMethod),
            Paragraph('<b>URI</b>', sMethod),
            Paragraph('<b>Action</b>', sMethod),
            Paragraph('<b>Code HTTP</b>', sMethod),
        ],
        ['GET',    '/api/books',      'Lister tous les livres',        '200 OK'],
        ['GET',    '/api/books/{id}', 'Récupérer un livre',            '200 / 404'],
        ['POST',   '/api/books',      'Créer un nouveau livre',        '201 Created'],
        ['PUT',    '/api/books/{id}', 'Modifier un livre',             '200 / 404'],
        ['DELETE', '/api/books/{id}', 'Supprimer un livre',            '200 / 404'],
    ]
    method_colors = {
        'GET':    colors.HexColor('#1d4ed8'),
        'POST':   colors.HexColor('#15803d'),
        'PUT':    colors.HexColor('#b45309'),
        'DELETE': colors.HexColor('#b91c1c'),
    }
    # colorize method cells
    for i in range(1, len(data)):
        m = data[i][0]
        col = method_colors.get(m, GREY)
        data[i][0] = Paragraph(f'<b>{m}</b>', ParagraphStyle('MC', fontName=BOLD_F, fontSize=9, textColor=colors.white, backColor=col))

    t = Table(data, colWidths=[22*mm, 52*mm, 72*mm, 32*mm])
    t.setStyle(TableStyle([
        ('BACKGROUND',  (0,0), (-1,0),  ACCENT),
        ('TEXTCOLOR',   (0,0), (-1,0),  BG),
        ('FONTNAME',    (0,0), (-1,0),  BOLD_F),
        ('FONTSIZE',    (0,0), (-1,0),  9),
        ('ROWBACKGROUNDS',(0,1),(-1,-1),[SURFACE, LGREY]),
        ('TEXTCOLOR',   (0,1), (-1,-1), WHITE),
        ('FONTNAME',    (0,1), (-1,-1), BODY_F),
        ('FONTSIZE',    (0,1), (-1,-1), 9),
        ('ALIGN',       (0,0), (-1,-1), 'LEFT'),
        ('VALIGN',      (0,0), (-1,-1), 'MIDDLE'),
        ('ROWHEIGHT',   (0,0), (-1,-1), 9*mm),
        ('LEFTPADDING', (0,0), (-1,-1), 5),
        ('GRID',        (0,0), (-1,-1), 0.3, colors.HexColor('#2d3f50')),
    ]))
    return t


def db_table():
    data = [
        [Paragraph('<b>Colonne</b>', sMethod), Paragraph('<b>Type SQL</b>', sMethod),
         Paragraph('<b>Contrainte</b>', sMethod), Paragraph('<b>Rôle</b>', sMethod)],
        ['id',         'INT UNSIGNED',   'PK AUTO_INC',    'Identifiant unique auto'],
        ['title',      'VARCHAR(255)',   'NOT NULL',       'Titre du livre'],
        ['author',     'VARCHAR(255)',   'NOT NULL',       "Nom de l'auteur"],
        ['genre',      'VARCHAR(100)',   "DEFAULT 'Non classé'", 'Genre littéraire'],
        ['year',       'YEAR',           'NOT NULL',       "Année de publication"],
        ['available',  'TINYINT(1)',     'DEFAULT 1',      '1=disponible  0=emprunté'],
        ['created_at', 'TIMESTAMP',      'AUTO NOW',       "Date/heure d'insertion"],
    ]
    for i in range(1, len(data)):
        data[i][0] = Paragraph(
            f'<font color="#{ACCENT2.hexval().replace("0x","").replace("#","")}"><b>{data[i][0]}</b></font>',
            sSmall
        )

        data[i][1] = Paragraph(
            f'<font color="#{BLUE.hexval().replace("0x","").replace("#","")}">{data[i][1]}</font>',
            sSmall
        )

        data[i][2] = Paragraph(
            f'<font color="#{GREEN.hexval().replace("0x","").replace("#","")}">{data[i][2]}</font>',
            sSmall
        )
        data[i][3] = Paragraph(data[i][3], sSmall)

    t = Table(data, colWidths=[28*mm, 36*mm, 42*mm, 72*mm])
    t.setStyle(TableStyle([
        ('BACKGROUND',  (0,0), (-1,0),  ACCENT),
        ('TEXTCOLOR',   (0,0), (-1,0),  BG),
        ('FONTNAME',    (0,0), (-1,0),  BOLD_F),
        ('FONTSIZE',    (0,0), (-1,0),  9),
        ('ROWBACKGROUNDS',(0,1),(-1,-1),[SURFACE, LGREY]),
        ('ROWHEIGHT',   (0,0), (-1,-1), 8.5*mm),
        ('ALIGN',       (0,0), (-1,-1), 'LEFT'),
        ('VALIGN',      (0,0), (-1,-1), 'MIDDLE'),
        ('LEFTPADDING', (0,0), (-1,-1), 6),
        ('GRID',        (0,0), (-1,-1), 0.3, colors.HexColor('#2d3f50')),
    ]))
    return t


def http_codes_table():
    codes = [
        ('200 OK',            GREEN,  'GET réussi, PUT réussi, DELETE réussi'),
        ('201 Created',       BLUE,   'POST — livre créé avec succès'),
        ('400 Bad Request',   YELLOW, 'JSON invalide, ID non numérique'),
        ('404 Not Found',     RED,    'ID inexistant en base de données'),
        ('405 Method Not Allowed', colors.HexColor('#f97316'), 'Mauvaise méthode sur une route'),
        ('422 Unprocessable', PURPLE, 'Champ obligatoire manquant ou invalide'),
        ('500 Server Error',  RED,    'Exception PDO / erreur MySQL'),
    ]
    data = [[Paragraph('<b>Code</b>', sMethod), Paragraph('<b>Situation déclenchante</b>', sMethod)]]
    for code, col, desc in codes:
        data.append([
            Paragraph(
                f'<font color="#{col.hexval().replace("0x","").replace("#","")}"><b>{code}</b></font>',
                sSmall
            ),
            Paragraph(desc, sSmall),
        ])
    t = Table(data, colWidths=[52*mm, 116*mm])
    t.setStyle(TableStyle([
        ('BACKGROUND',  (0,0), (-1,0),  ACCENT),
        ('TEXTCOLOR',   (0,0), (-1,0),  BG),
        ('FONTNAME',    (0,0), (-1,0),  BOLD_F),
        ('ROWBACKGROUNDS',(0,1),(-1,-1),[SURFACE, LGREY]),
        ('ROWHEIGHT',   (0,0), (-1,-1), 8*mm),
        ('ALIGN',       (0,0), (-1,-1), 'LEFT'),
        ('VALIGN',      (0,0), (-1,-1), 'MIDDLE'),
        ('LEFTPADDING', (0,0), (-1,-1), 6),
        ('GRID',        (0,0), (-1,-1), 0.3, colors.HexColor('#2d3f50')),
    ]))
    return t


def arch_table():
    data = [
        [Paragraph('<b>Couche</b>', sMethod), Paragraph('<b>Fichier</b>', sMethod), Paragraph('<b>Responsabilité</b>', sMethod)],
        ['Config',      'config/database.php',       'Connexion PDO, Singleton, gestion exceptions'],
        ['Modèle',      'models/Book.php',            'Requêtes SQL, CRUD, requêtes préparées'],
        ['Contrôleur',  'controllers/BookController.php', 'Validation, codes HTTP, réponses JSON'],
        ['Routeur',     'routes/router.php',          'Analyse URI, dispatch méthode/contrôleur'],
        ['Entrée',      'public/index.php',           'Point d\'entrée unique, Front Controller'],
        ['Frontend',    'frontend/ (3 fichiers)',     'HTML + CSS + JS (fetch API, SPA légère)'],
        ['BDD',         'database/schema.sql',        'DDL MySQL, données de test'],
    ]
    emoji_col = ['⚙️','📦','🎮','🗺️','🚪','🖥️','💾']
    for i in range(1, len(data)):
        data[i][0] = Paragraph(f'{emoji_col[i-1]}  <b>{data[i][0]}</b>', sSmall)
        data[i][1] = Paragraph(
            f'<font color="#{ACCENT2.hexval().replace("0x","").replace("#","")}">{data[i][1]}</font>',
            sSmall
        )
        data[i][2] = Paragraph(data[i][2], sSmall)
    t = Table(data, colWidths=[26*mm, 62*mm, 90*mm])
    t.setStyle(TableStyle([
        ('BACKGROUND',  (0,0), (-1,0),  ACCENT),
        ('TEXTCOLOR',   (0,0), (-1,0),  BG),
        ('FONTNAME',    (0,0), (-1,0),  BOLD_F),
        ('ROWBACKGROUNDS',(0,1),(-1,-1),[SURFACE, LGREY]),
        ('ROWHEIGHT',   (0,0), (-1,-1), 8.5*mm),
        ('ALIGN',       (0,0), (-1,-1), 'LEFT'),
        ('VALIGN',      (0,0), (-1,-1), 'MIDDLE'),
        ('LEFTPADDING', (0,0), (-1,-1), 6),
        ('GRID',        (0,0), (-1,-1), 0.3, colors.HexColor('#2d3f50')),
    ]))
    return t


# ════════════════════════════════════════════════════════════
#  SECTION HEADER FLOWABLE
# ════════════════════════════════════════════════════════════
def section_num(n, title, subtitle=''):
    """Bandeau de titre de slide."""
    elems = []
    # Numéro + titre
    elems.append(Spacer(1, 4*mm))
    num_style  = ParagraphStyle('NS', fontName=BOLD_F, fontSize=10, textColor=ACCENT, leading=14)
    title_style= ParagraphStyle('TS', fontName=BOLD_F, fontSize=22, textColor=WHITE,  leading=26, spaceBefore=0)
    sub_style  = ParagraphStyle('SS', fontName=BODY_F, fontSize=11, textColor=ACCENT2,leading=16, spaceBefore=2, spaceAfter=6)
    elems.append(Paragraph(f'0{n}', num_style))
    elems.append(Paragraph(title, title_style))
    if subtitle:
        elems.append(Paragraph(subtitle, sub_style))
    elems.append(HRFlowable(width='100%', thickness=0.5, color=ACCENT, spaceAfter=6*mm))
    return elems


# ════════════════════════════════════════════════════════════
#  BUILD PDF
# ════════════════════════════════════════════════════════════
OUT = '/home/claude/Presentation_SOA_API_REST.pdf'
ML, MR, MT, MB = 16*mm, 16*mm, 12*mm, 12*mm

doc = SimpleDocTemplate(
    OUT,
    pagesize=(W, H),
    leftMargin=ML, rightMargin=MR,
    topMargin=MT,  bottomMargin=MB,
    title='API REST Bibliothèque — SOA M1',
    author='Master 1 SI',
)

story = []

# ════════════════════════════════════════════════════════════
#  SLIDE 1 : COUVERTURE
# ════════════════════════════════════════════════════════════
story.append(CoverSlide())
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 2 : SOMMAIRE
# ════════════════════════════════════════════════════════════
story += section_num(0, 'Sommaire')

sections = [
    ('01', 'Présentation & Objectifs'),
    ('02', 'Architecture SOA & Principes REST'),
    ('03', 'Base de données MySQL'),
    ('04', 'Structure du projet'),
    ('05', 'Connexion PDO'),
    ('06', 'Modèle — Book.php'),
    ('07', 'Contrôleur REST'),
    ('08', 'Routeur PHP'),
    ('09', 'Endpoints & Tests API'),
    ('10', 'Frontend — Interface'),
    ('11', 'Sécurité'),
    ('12', 'Conclusion & Perspectives'),
]

col1 = [[
    Paragraph(
        f'<font color="#{ACCENT.hexval().replace("0x","").replace("#","")}"><b>{n}</b></font> {t}',
        sNormal
    )
] for n, t in sections[:6]]
col2 = [[Paragraph(f'<font color="#{ACCENT.hexval().replace("0x","").replace("#","")}"><b>{n}</b></font>  {t}', sNormal)] for n,t in sections[6:]]
toc = Table(
    [[Table([[r] for r in col1], colWidths=[115*mm]), Table([[r] for r in col2], colWidths=[115*mm])]],
    colWidths=[120*mm, 120*mm]
)
toc.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'), ('LEFTPADDING',(0,0),(-1,-1),0)]))
story.append(toc)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 3 : PRÉSENTATION & OBJECTIFS
# ════════════════════════════════════════════════════════════
story += section_num(1, 'Présentation & Objectifs', 'Gestion de Bibliothèque via une API REST')

left = [
    h2('Contexte'),
    bul('Projet Master 1 — Systèmes d\'Information'),
    bul('Unité : Architecture Orientée Services (SOA)'),
    bul('Sujet : Catalogue de bibliothèque'),
    sp(3),
    h2('Stack technique'),
    bul('<font color="#60a5fa">PHP 8</font>  sans framework'),
    bul('<font color="#60a5fa">MySQL 8</font>  — table unique'),
    bul('<font color="#60a5fa">PDO</font>  pour l\'accès aux données'),
    bul('<font color="#60a5fa">HTML/CSS/JS</font>  frontend natif'),
]
right = [
    h2('Objectifs'),
    bul('Exposer une ressource REST complète'),
    bul('Respecter les verbes HTTP (GET POST PUT DELETE)'),
    bul('Réponses JSON standardisées'),
    bul('Codes HTTP appropriés'),
    bul('Séparer les couches MVC'),
    sp(3),
    h2('Ressource exposée'),
    Paragraph('<font color="#f0a570"><b>/api/books</b></font>', ParagraphStyle('R', fontName=BOLD_F, fontSize=16, textColor=ACCENT2, leading=22)),
]
t = Table([[left, right]], colWidths=[120*mm, 120*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'), ('LEFTPADDING',(0,0),(-1,-1),0), ('RIGHTPADDING',(0,0),(-1,-1),8*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 4 : ARCHITECTURE SOA & REST
# ════════════════════════════════════════════════════════════
story += section_num(2, 'Architecture SOA & REST', '6 contraintes fondamentales de REST')

left = [
    h2('SOA en résumé'),
    bul('Services faiblement <b>couplés</b>'),
    bul('Interface bien <b>définie</b>'),
    bul('Communication via protocoles <b>standardisés</b>'),
    bul('REST = implémentation HTTP de SOA'),
    sp(3),
    h2('Flux d\'une requête'),
    CodeBlock([
        'Client HTTP',
        '  ↓ requête (méthode + URI + JSON)',
        'public/index.php',
        '  ↓',
        'routes/router.php',
        '  ↓',
        'controllers/BookController.php',
        '  ↓',
        'models/Book.php  →  MySQL',
        '  ↑ résultat JSON',
        'Client HTTP',
    ], w=105*mm, fontsize=7.5),
]
right_data = [
    ('Client-Serveur',   'Séparation frontend / backend'),
    ('Stateless',        'Pas de session côté serveur'),
    ('Cacheable',        'Réponses cachables'),
    ('Interface uniforme','Verbes HTTP standardisés'),
    ('Système en couches','Proxy/LB transparents'),
    ('Code-on-demand',   '(Optionnel)'),
]
right = [h2('Contraintes REST')]
for k, v in right_data:
    right.append(Paragraph(f'<font color="#{ACCENT.hexval().replace("0x","").replace("#","")}"><b>{k}</b></font>', sSmall))
    right.append(Paragraph(f'  {v}', sSmall))
    right.append(sp(1))

t = Table([[left, right]], colWidths=[120*mm, 120*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),8*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 5 : BASE DE DONNÉES
# ════════════════════════════════════════════════════════════
story += section_num(3, 'Base de données MySQL', 'Table unique : books')

story.append(db_table())
story.append(sp(4))

left = [
    h2('Choix techniques'),
    bul('ENGINE=<b>InnoDB</b> : transactions ACID'),
    bul('<b>utf8mb4</b> : vrai UTF-8 (emojis inclus)'),
    bul('<b>UNSIGNED</b> : ID toujours positif, plage doublée'),
    bul('<b>TINYINT(1)</b> : booléen MySQL standard'),
]
right = [
    h2('Extrait SQL'),
    CodeBlock([
        'CREATE TABLE books (',
        '  id        INT UNSIGNED AUTO_INCREMENT',
        '            PRIMARY KEY,',
        '  title     VARCHAR(255) NOT NULL,',
        '  author    VARCHAR(255) NOT NULL,',
        '  genre     VARCHAR(100) DEFAULT "Non classe",',
        '  year      YEAR NOT NULL,',
        '  available TINYINT(1) DEFAULT 1,',
        '  created_at TIMESTAMP DEFAULT NOW()',
        ') ENGINE=InnoDB CHARSET=utf8mb4;',
    ], w=115*mm, lang='sql'),
]
t = Table([[left, right]], colWidths=[100*mm, 140*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),6*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 6 : STRUCTURE DU PROJET
# ════════════════════════════════════════════════════════════
story += section_num(4, 'Structure du projet', 'Séparation des préoccupations (SoC)')

left = [
    CodeBlock([
        'project/',
        '├── config/',
        '│   └── database.php      ← PDO',
        '├── models/',
        '│   └── Book.php          ← CRUD SQL',
        '├── controllers/',
        '│   └── BookController.php← REST + JSON',
        '├── routes/',
        '│   └── router.php        ← dispatch',
        '├── public/',
        '│   ├── index.php         ← entrée unique',
        '│   └── .htaccess         ← rewrite',
        '├── frontend/',
        '│   ├── index.html',
        '│   ├── style.css',
        '│   └── app.js',
        '└── database/',
        '    └── schema.sql',
    ], w=110*mm, lang='bash'),
]
right = [
    h2('Rôle de chaque couche'),
    arch_table(),
]
t = Table([[left, right]], colWidths=[115*mm, 125*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),4*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 7 : CONNEXION PDO
# ════════════════════════════════════════════════════════════
story += section_num(5, 'Connexion PDO', 'config/database.php — Pattern Singleton')

left = [
    CodeBlock([
        '<?php',
        'class Database {',
        '  private string $host   = "localhost";',
        '  private string $dbName = "bibliotheque";',
        '  private ?PDO   $conn   = null;',
        '',
        '  public function connect(): PDO {',
        '    if ($this->conn !== null)',
        '      return $this->conn; // Singleton',
        '',
        '    $dsn = "mysql:host={$this->host};',
        '            dbname={$this->dbName};',
        '            charset=utf8mb4";',
        '    try {',
        '      $this->conn = new PDO($dsn,',
        '        $this->user, $this->pass, [',
        '        PDO::ATTR_ERRMODE =>',
        '          PDO::ERRMODE_EXCEPTION,',
        '        PDO::ATTR_DEFAULT_FETCH_MODE =>',
        '          PDO::FETCH_ASSOC,',
        '        PDO::ATTR_EMULATE_PREPARES =>',
        '          false,',
        '      ]);',
        '    } catch (PDOException $e) {',
        '      // Retourner erreur JSON 500',
        '    }',
        '    return $this->conn;',
        '  }',
        '}',
    ], w=120*mm, fontsize=7),
]
right = [
    h2('DSN décomposé'),
    CodeBlock([
        '"mysql:host=localhost;',
        ' dbname=bibliotheque;',
        ' charset=utf8mb4"',
        '',
        '// mysql:  → driver PDO',
        '// host    → serveur MySQL',
        '// dbname  → base de données',
        '// charset → encodage UTF-8',
    ], w=110*mm, fontsize=7.5),
    sp(3),
    h2('Options PDO essentielles'),
    bul('<b>ERRMODE_EXCEPTION</b> : lève des exceptions'),
    bul('<b>FETCH_ASSOC</b> : tableaux associatifs'),
    bul('<b>EMULATE_PREPARES false</b> : vraies requêtes'),
    sp(3),
    h2('Pattern Singleton'),
    bul('Une seule connexion MySQL par requête'),
    bul('Réutilisation si déjà ouverte'),
    bul('Économie de ressources serveur'),
]
t = Table([[left, right]], colWidths=[125*mm, 115*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),4*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 8 : MODÈLE
# ════════════════════════════════════════════════════════════
story += section_num(6, 'Modèle — Book.php', 'Couche d\'accès aux données : 5 méthodes CRUD')

left = [
    h2('Méthodes implémentées'),
    sp(1),
]
methods = [
    ('create(array $data)',    GREEN,  'INSERT — retourne l\'ID créé'),
    ('read(array $filters)',   BLUE,   'SELECT avec WHERE dynamique'),
    ('readOne(int $id)',       YELLOW, 'SELECT LIMIT 1 par ID'),
    ('update(int $id, ...)',   ACCENT, 'UPDATE après vérif existence'),
    ('delete(int $id)',        RED,    'DELETE après vérif existence'),
]
for name, col, desc in methods:
    left.append(Paragraph(
        f'<font color="#{col.hexval().replace("0x","").replace("#","")}"><b>{name}</b></font>',
        ParagraphStyle('MN', fontName=BOLD_F, fontSize=9, textColor=col, leading=14)
    ))
    left.append(Paragraph(f'  {desc}', sSmall))
    left.append(sp(1))

right = [
    h2('Requête préparée — Exemple'),
    CodeBlock([
        '// SECURISE contre injection SQL',
        '$sql = "INSERT INTO books',
        '        (title, author, genre,',
        '         year, available)',
        '        VALUES',
        '        (:title, :author, :genre,',
        '         :year, :available)";',
        '',
        '$stmt = $this->db->prepare($sql);',
        '',
        '// bindParam lie la valeur comme',
        '// DONNEE, jamais comme code SQL',
        '$stmt->bindParam(',
        "  ':title', $data['title'],",
        '  PDO::PARAM_STR',
        ');',
        '$stmt->execute();',
        '',
        'return [',
        "  'success' => true,",
        "  'id' => $this->db->lastInsertId()",
        '];',
    ], w=120*mm, fontsize=7),
]
t = Table([[left, right]], colWidths=[110*mm, 130*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),4*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 9 : CONTRÔLEUR
# ════════════════════════════════════════════════════════════
story += section_num(7, 'Contrôleur REST', 'controllers/BookController.php')

left = [
    h2('Cycle de traitement'),
    bul('1. Décoder le corps JSON <font color="#60a5fa">php://input</font>'),
    bul('2. Valider les champs obligatoires'),
    bul('3. Nettoyer les données (trim / intval)'),
    bul('4. Appeler la méthode du modèle'),
    bul('5. Envoyer la réponse JSON + code HTTP'),
    sp(3),
    h2('Lecture du JSON (POST/PUT)'),
    CodeBlock([
        '// $_POST ne fonctionne PAS pour JSON',
        '// Il faut lire le flux brut :',
        '$raw  = file_get_contents("php://input");',
        '$data = json_decode($raw, true);',
        '',
        'if (json_last_error() !== JSON_ERROR_NONE)',
        '  // retourner 400 Bad Request',
    ], w=110*mm, fontsize=7.5),
]
right = [
    h2('Méthode sendResponse()'),
    CodeBlock([
        'private function sendResponse(',
        '  array $data,',
        '  int $code = 200',
        '): void {',
        '',
        '  header(',
        '    "Content-Type:',
        '     application/json; charset=utf-8"',
        '  );',
        '  header("Access-Control-Allow-Origin: *");',
        '',
        '  http_response_code($code);',
        '',
        '  echo json_encode($data,',
        '    JSON_UNESCAPED_UNICODE |',
        '    JSON_PRETTY_PRINT',
        '  );',
        '  exit;',
        '}',
    ], w=120*mm, fontsize=7),
    sp(2),
    bul('<b>CORS</b> : permet appels depuis le frontend'),
    bul('<b>exit</b> : stoppe après envoi de la réponse'),
]
t = Table([[left, right]], colWidths=[115*mm, 125*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),4*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 10 : ROUTEUR
# ════════════════════════════════════════════════════════════
story += section_num(8, 'Routeur PHP', 'routes/router.php — Analyse URI + Dispatch')

left = [
    CodeBlock([
        '<?php',
        'class Router {',
        '  private string $method;',
        '  private string $uri;',
        '',
        '  public function __construct() {',
        '    $this->method =',
        '      $_SERVER["REQUEST_METHOD"];',
        '    $this->uri = rtrim(',
        '      parse_url(',
        '        $_SERVER["REQUEST_URI"],',
        '        PHP_URL_PATH',
        '      ), "/"',
        '    );',
        '  }',
        '',
        '  public function dispatch(): void {',
        '    // Route 1 : /api/books',
        '    if ($this->uri === "/api/books") {',
        '      match($this->method) {',
        '        "GET"  => $ctrl->getAll(),',
        '        "POST" => $ctrl->create(),',
        '        default=> $this->methodNotAllowed()',
        '      };',
        '    }',
        '    // Route 2 : /api/books/{id}',
        '    if (preg_match(',
        '      "#^/api/books/(\\d+)$#",',
        '      $this->uri, $m)) {',
        '      $id = (int) $m[1];',
        '      // dispatch GET/PUT/DELETE',
        '    }',
        '  }',
        '}',
    ], w=120*mm, fontsize=7),
]
right = [
    h2('Principe de fonctionnement'),
    bul('<b>REQUEST_METHOD</b> : GET, POST, PUT, DELETE'),
    bul('<b>parse_url()</b> : extrait le chemin sans query string'),
    bul('<b>rtrim()</b> : supprime les / de fin'),
    bul('<b>preg_match()</b> : regex capture l\'ID'),
    sp(3),
    h2('Gestion des erreurs'),
    bul('<font color="#f87171"><b>404 Not Found</b></font>  : URI inconnue'),
    bul('<font color="#fbbf24"><b>405 Method Not Allowed</b></font>  : URI valide, mauvaise méthode'),
    sp(3),
    h2('.htaccess Apache'),
    CodeBlock([
        'RewriteEngine On',
        'RewriteCond %{REQUEST_FILENAME} !-f',
        'RewriteCond %{REQUEST_FILENAME} !-d',
        'RewriteRule ^(.*)$ index.php [QSA,L]',
        '',
        '# Toutes les URIs → index.php',
        '# sauf vrais fichiers/dossiers',
    ], w=110*mm, lang='apache', fontsize=7.5),
]
t = Table([[left, right]], colWidths=[125*mm, 115*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),4*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 11 : ENDPOINTS & TESTS
# ════════════════════════════════════════════════════════════
story += section_num(9, 'Endpoints & Tests API', 'Démarrage : cd public && php -S localhost:8000')

story.append(route_table())
story.append(sp(4))

left = [
    h2('Tests curl'),
    CodeBlock([
        '# Lister tous les livres',
        'curl -X GET localhost:8000/api/books',
        '',
        '# Créer un livre',
        'curl -X POST localhost:8000/api/books \\',
        '  -H "Content-Type: application/json" \\',
        '  -d \'{"title":"Dune",',
        '        "author":"Frank Herbert",',
        '        "genre":"SF","year":1965}\'',
        '',
        '# Modifier le livre #1',
        'curl -X PUT localhost:8000/api/books/1 \\',
        '  -H "Content-Type: application/json" \\',
        '  -d \'{"title":"Dune (ed. rev.)",',
        '        "author":"Frank Herbert",',
        '        "genre":"SF","year":1966,',
        '        "available":0}\'',
    ], w=118*mm, fontsize=7),
]
right = [
    h2('Exemple de réponse JSON'),
    CodeBlock([
        '// GET /api/books → 200 OK',
        '{',
        '  "success": true,',
        '  "count": 10,',
        '  "data": [',
        '    {',
        '      "id": 1,',
        '      "title": "Le Petit Prince",',
        '      "author": "A. de Saint-Exupery",',
        '      "genre": "Roman",',
        '      "year": 1943,',
        '      "available": 1,',
        '      "created_at": "2025-01-15"',
        '    },',
        '    ...',
        '  ]',
        '}',
    ], w=115*mm, lang='json', fontsize=7.5),
]
t = Table([[left, right]], colWidths=[123*mm, 117*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),4*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 12 : CODES HTTP
# ════════════════════════════════════════════════════════════
story += section_num(9.5, 'Codes HTTP de réponse', 'Chaque situation a son code approprié')
story.append(http_codes_table())
story.append(sp(5))
story.append(normal('Les codes HTTP ne sont pas arbitraires — ils communiquent la nature du résultat au client (navigateur, Postman, application mobile) de manière standardisée.'))
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 13 : FRONTEND
# ════════════════════════════════════════════════════════════
story += section_num(10, 'Frontend — Interface', 'HTML + CSS + JavaScript (fetch API)')

left = [
    h2('3 fichiers indépendants'),
    bul('<b>index.html</b> : grille de livres, modals, testeur API'),
    bul('<b>style.css</b> : design éditorial, variables CSS, responsive'),
    bul('<b>app.js</b> : fetch(), validation, DOM, XSS protection'),
    sp(3),
    h2('Communication avec l\'API'),
    CodeBlock([
        '// CREATE — POST',
        'const res = await fetch("/api/books", {',
        '  method: "POST",',
        '  headers: {',
        '    "Content-Type": "application/json"',
        '  },',
        '  body: JSON.stringify({',
        '    title, author, genre,',
        '    year, available',
        '  })',
        '});',
        'const data = await res.json();',
        '',
        '// data.success === true → recharger',
        'if (data.success) loadBooks();',
    ], w=110*mm, lang='js', fontsize=7.5),
]
right = [
    h2('Fonctionnalités'),
    bul('Affichage en grille responsive'),
    bul('Filtres : recherche, genre, disponibilité'),
    bul('Modal d\'ajout avec validation client'),
    bul('Modal de modification pré-rempli'),
    bul('Confirmation avant suppression'),
    bul('Testeur d\'API intégré (comme Postman)'),
    bul('Notifications toast (succès / erreur)'),
    sp(3),
    h2('Protection XSS'),
    CodeBlock([
        'function escapeHtml(str) {',
        '  const map = {',
        "    '&':'&amp;', '<':'&lt;',",
        "    '>':'&gt;', '\"':'&quot;',",
        "    \"'\":'&#39;'",
        '  };',
        '  return String(str).replace(',
        '    /[&<>"\']/g,',
        '    m => map[m]',
        '  );',
        '}',
    ], w=110*mm, lang='js', fontsize=7.5),
]
t = Table([[left, right]], colWidths=[118*mm, 122*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),4*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 14 : SÉCURITÉ
# ════════════════════════════════════════════════════════════
story += section_num(11, 'Sécurité', 'Protections implémentées à chaque couche')

sec_items = [
    (GREEN,  'Injection SQL',     'PDO + requêtes préparées (bindParam)',
     'Données traitées comme valeurs, jamais comme code SQL. Impossible d\'injecter DROP TABLE.'),
    (BLUE,   'XSS Frontend',     'escapeHtml() avant affichage',
     'Caractères spéciaux convertis en entités HTML. <script> devient inoffensif.'),
    (YELLOW, 'Validation',       'Côté JS ET côté PHP',
     'JS = aide à l\'utilisateur (UX). PHP = vraie barrière de sécurité. Jamais faire confiance au client.'),
    (ACCENT, 'CORS',             'Headers Access-Control-Allow-*',
     'Permet au frontend de faire des requêtes cross-origin. En prod : restreindre à l\'URL exacte.'),
    (PURPLE, 'Erreurs',          'Messages génériques en production',
     'Ne jamais exposer les messages PDO bruts. Ils révèlent la structure de la base.'),
    (RED,    'Input Sanitization','trim() + intval() + filter_var()',
     'Nettoyage systématique avant toute utilisation des données entrantes.'),
]

rows = []
for col, title, short, detail in sec_items:
    rows.append([
       Paragraph(
            f'<font color="#{col.hexval().replace("0x","").replace("#","")}">●</font>  <b>{title}</b>',
            sSmall
        ),
        Paragraph(short, ParagraphStyle('SS2', fontName=BOLD_F, fontSize=8.5, textColor=col, leading=13)),
        Paragraph(detail, sSmall),
    ])

t = Table(rows, colWidths=[46*mm, 58*mm, 136*mm])
t.setStyle(TableStyle([
    ('ROWBACKGROUNDS', (0,0),(-1,-1), [SURFACE, LGREY]),
    ('ROWHEIGHT',      (0,0),(-1,-1), 11*mm),
    ('VALIGN',         (0,0),(-1,-1), 'MIDDLE'),
    ('LEFTPADDING',    (0,0),(-1,-1), 8),
    ('GRID',           (0,0),(-1,-1), 0.3, colors.HexColor('#2d3f50')),
]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 15 : CONCLUSION
# ════════════════════════════════════════════════════════════
story += section_num(12, 'Conclusion & Perspectives', 'Bilan et évolutions possibles')

left = [
    h2('Compétences acquises'),
    bul('Conception d\'une API REST complète en PHP natif'),
    bul('Architecture en couches : SoC, MVC adapté REST'),
    bul('PDO : DSN, requêtes préparées, Singleton'),
    bul('Codes HTTP appropriés (200/201/400/404/422/500)'),
    bul('CORS, validation, protection XSS'),
    bul('Frontend SPA légère consommant une API'),
    sp(4),
    h2('Difficultés surmontées'),
    bul('Gestion CORS pour requêtes cross-origin'),
    bul('php://input pour lecture du JSON PUT/DELETE'),
    bul('Routeur sans framework via regex'),
    bul('Encodage utf8mb4 cohérent à tous les niveaux'),
]
right = [
    h2('Perspectives d\'évolution'),
    bul('<font color="#60a5fa">Authentification</font>  JWT — routes protégées'),
    bul('<font color="#60a5fa">Pagination</font>  — ?page=1&limit=10'),
    bul('<font color="#60a5fa">Versioning</font>  — /api/v1/books'),
    bul('<font color="#60a5fa">Rate limiting</font>  — 100 req/min par IP'),
    bul('<font color="#60a5fa">Logs</font>  — journal des accès et erreurs'),
    bul('<font color="#60a5fa">Docker</font>  — déploiement containerisé'),
    bul('<font color="#60a5fa">Tests unitaires</font>  — PHPUnit'),
    sp(4),
    h2('Ce projet démontre'),
    Paragraph(
        'Une maîtrise des fondamentaux REST appliqués dans un contexte SOA réel, '
        'sans abstraction cachée par un framework.',
        ParagraphStyle('CQ', fontName=BODY_F, fontSize=9.5, textColor=ACCENT2, leading=15,
                       borderPad=8, borderColor=ACCENT, borderWidth=0.5, borderRadius=4)
    ),
]
t = Table([[left, right]], colWidths=[118*mm, 122*mm])
t.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),4*mm)]))
story.append(t)
story.append(PageBreak())


# ════════════════════════════════════════════════════════════
#  SLIDE 16 : MERCI / Q&A
# ════════════════════════════════════════════════════════════
class ThanksSlide(Flowable):
    def __init__(self):
        Flowable.__init__(self)
        self.width  = W
        self.height = H

    def draw(self):
        c = self.canv
        c.setFillColor(BG)
        c.rect(0, 0, W, H, fill=1, stroke=0)

        # Bande accent
        c.setFillColor(ACCENT)
        c.rect(0, 0, 8*mm, H, fill=1, stroke=0)

        # Grand cercle déco
        c.setFillColor(colors.HexColor('#1a2332'))
        c.circle(W*0.75, H*0.5, 60*mm, fill=1, stroke=0)
        c.setFillColor(colors.HexColor('#243040'))
        c.circle(W*0.75, H*0.5, 44*mm, fill=1, stroke=0)
        c.setFillColor(ACCENT)
        c.setFont(TITLE_F, 22)
        c.drawCentredString(W*0.75, H*0.5-8, 'Q & A')

        # Texte
        c.setFillColor(WHITE)
        c.setFont(TITLE_F, 40)
        c.drawString(16*mm, H*0.62, 'Merci !')

        c.setFillColor(ACCENT2)
        c.setFont(BODY_F, 14)
        c.drawString(16*mm, H*0.62-32, 'Questions & Démonstration')

        c.setStrokeColor(ACCENT)
        c.setLineWidth(0.5)
        c.line(16*mm, H*0.35, 70*mm, H*0.35)

        c.setFillColor(GREY)
        c.setFont(BODY_F, 9)
        items = [
            'github.com/votre-projet',
            'localhost:8000/api/books',
            'PHP 8  |  MySQL 8  |  PDO  |  REST  |  JSON',
        ]
        for i, item in enumerate(items):
            c.drawString(16*mm, H*0.28 - i*14, item)

story.append(ThanksSlide())

# ════════════════════════════════════════════════════════════
#  BUILD
# ════════════════════════════════════════════════════════════
doc.build(story, onFirstPage=bg_page, onLaterPages=bg_page)
print(f'PDF généré : {OUT}')
print(f'Nombre de slides : ~16')