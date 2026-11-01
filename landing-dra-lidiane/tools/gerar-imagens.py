"""Gera as ilustrações SVG da landing page (images/).

Rodar a partir da raiz do repositório:
    python3 landing-dra-lidiane/tools/gerar-imagens.py

São ilustrações no estilo da marca (tons nude, rosé e marrom), feitas para
ocupar o lugar das fotos até as fotos reais da clínica chegarem.
"""

from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "images"

SKIN = "#F2D5C4"
SKIN_SHADOW = "#E2B9A3"
SKIN_DEEP = "#C9927A"
LIPS = "#C9786E"
BLONDE = "#DDBE8F"
BLONDE_LIGHT = "#EED7AE"
BLONDE_ROOT = "#A9845A"
BROWN_HAIR = "#4A3127"
BROWN_HAIR_LIGHT = "#6E4A39"
ROSE = "#C99A7E"
ROSE_LIGHT = "#E9D3C6"
CREAM = "#F7F2EE"
DARK = "#151112"


def svg(w, h, body, defs=""):
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" '
        f'width="{w}" height="{h}" preserveAspectRatio="xMidYMid slice">'
        f"<defs>{defs}</defs>{body}</svg>\n"
    )


def common_defs(prefix):
    return f"""
<radialGradient id="{prefix}blush" cx="50%" cy="50%" r="50%">
  <stop offset="0" stop-color="#E79A8E" stop-opacity=".55"/>
  <stop offset="1" stop-color="#E79A8E" stop-opacity="0"/>
</radialGradient>
<linearGradient id="{prefix}face" x1="0" y1="0" x2="1" y2="0">
  <stop offset="0" stop-color="{SKIN_SHADOW}"/>
  <stop offset=".22" stop-color="{SKIN}"/>
  <stop offset=".78" stop-color="{SKIN}"/>
  <stop offset="1" stop-color="{SKIN_SHADOW}"/>
</linearGradient>
<linearGradient id="{prefix}neck" x1="0" y1="0" x2="0" y2="1">
  <stop offset="0" stop-color="{SKIN_DEEP}"/>
  <stop offset=".35" stop-color="{SKIN_SHADOW}"/>
  <stop offset="1" stop-color="{SKIN}"/>
</linearGradient>
<linearGradient id="{prefix}blonde" x1="0" y1="0" x2="0" y2="1">
  <stop offset="0" stop-color="{BLONDE_ROOT}"/>
  <stop offset=".25" stop-color="{BLONDE}"/>
  <stop offset="1" stop-color="{BLONDE_LIGHT}"/>
</linearGradient>
<linearGradient id="{prefix}brown" x1="0" y1="0" x2="0" y2="1">
  <stop offset="0" stop-color="{BROWN_HAIR}"/>
  <stop offset="1" stop-color="{BROWN_HAIR_LIGHT}"/>
</linearGradient>
"""


def face_front(p, *, x=0, y=0, s=1.0, hair="blonde", eyes="open", top="skin",
               top_color="#4B2E24", blazer=None, extras="", earrings=True):
    """Rosto de frente num quadro de 400x500 (centro x=200)."""
    hair_fill = f"url(#{p}{hair})"
    strand = BLONDE_LIGHT if hair == "blonde" else BROWN_HAIR_LIGHT
    shade = BLONDE_ROOT if hair == "blonde" else "#2E1E18"

    if top == "skin":
        body = f"""
<path d="M40 520 C55 440 115 405 172 392 Q200 404 228 392 C285 405 345 440 360 520 Z" fill="{SKIN}"/>
<path d="M120 430 Q150 445 175 440" stroke="{SKIN_SHADOW}" stroke-width="3" fill="none" stroke-linecap="round"/>
<path d="M280 430 Q250 445 225 440" stroke="{SKIN_SHADOW}" stroke-width="3" fill="none" stroke-linecap="round"/>"""
    else:
        body = f"""
<path d="M40 520 C55 440 115 405 172 392 Q200 404 228 392 C285 405 345 440 360 520 Z" fill="{SKIN}"/>
<path d="M95 520 C100 470 125 430 158 395 Q200 420 242 395 C275 430 300 470 305 520 Z" fill="{top_color}"/>
<path d="M158 395 Q200 420 242 395 L242 380 Q200 405 158 380 Z" fill="{top_color}"/>
<path d="M160 386 Q200 408 240 386" stroke="#000" stroke-opacity=".18" stroke-width="2" fill="none"/>"""
    if blazer:
        body += f"""
<path d="M30 520 C40 450 100 410 160 395 L200 520 Z" fill="{blazer}"/>
<path d="M370 520 C360 450 300 410 240 395 L200 520 Z" fill="{blazer}"/>
<path d="M160 395 L185 470 L150 455 Z" fill="#000" fill-opacity=".18"/>
<path d="M240 395 L215 470 L250 455 Z" fill="#000" fill-opacity=".18"/>"""

    if eyes == "open":
        eye = lambda cx: f"""
<path d="M{cx-21} 224 Q{cx} 208 {cx+21} 224 Q{cx} 233 {cx-21} 224 Z" fill="#FBF6F3"/>
<circle cx="{cx}" cy="222.5" r="7.5" fill="#6B4A3A"/>
<circle cx="{cx}" cy="222.5" r="3.6" fill="#2A1C17"/>
<circle cx="{cx+2.5}" cy="220" r="1.8" fill="#fff"/>
<path d="M{cx-23} 223 Q{cx} 204 {cx+23} 222" stroke="#3A2620" stroke-width="3" fill="none" stroke-linecap="round"/>
<path d="M{cx-18} 230 Q{cx} 236 {cx+18} 229" stroke="{SKIN_DEEP}" stroke-width="1.2" fill="none" opacity=".6"/>"""
    else:
        eye = lambda cx: f"""
<path d="M{cx-20} 224 Q{cx} 236 {cx+20} 224" stroke="#3A2620" stroke-width="3" fill="none" stroke-linecap="round"/>
<path d="M{cx-12} 230 l-3 6 M{cx-4} 232 l-1 6 M{cx+4} 232 l1 6 M{cx+12} 230 l3 6" stroke="#3A2620" stroke-width="1.4" stroke-linecap="round"/>"""

    brow_color = "#7A5A40" if hair == "blonde" else "#2E1E18"
    ear_rings = ""
    if earrings:
        ear_rings = f"""
<circle cx="121" cy="252" r="4" fill="#D9B77C"/><circle cx="279" cy="252" r="4" fill="#D9B77C"/>"""

    hair_back = f"""
<path d="M200 92 C112 92 88 165 96 245 C102 315 78 385 62 455 C110 470 150 450 165 420 L235 420 C250 450 290 470 338 455 C322 385 298 315 304 245 C312 165 288 92 200 92 Z" fill="{hair_fill}"/>"""
    hair_front = f"""
<path d="M200 98 C138 98 110 150 116 228 C126 176 162 136 214 126 C246 136 270 166 284 228 C292 150 262 98 200 98 Z" fill="{hair_fill}"/>
<path d="M118 205 C104 285 122 350 92 445 C138 430 150 360 142 262 Z" fill="{hair_fill}"/>
<path d="M282 205 C296 285 278 350 308 445 C262 430 250 360 258 262 Z" fill="{hair_fill}"/>
<path d="M205 110 C170 130 140 160 128 215 M220 116 C250 140 270 170 276 215 M124 260 C122 320 118 370 104 430 M276 260 C278 320 282 370 296 430" stroke="{strand}" stroke-width="2.5" fill="none" opacity=".7" stroke-linecap="round"/>
<path d="M195 104 C185 115 180 122 176 130" stroke="{shade}" stroke-width="3" fill="none" opacity=".5"/>"""
    if hair == "brown":
        # cabelo preso (coque) para a doutora de perfil/fundo escuro
        hair_back = f"""
<circle cx="200" cy="82" r="38" fill="{hair_fill}"/>
<path d="M200 96 C130 96 108 160 116 235 L284 235 C292 160 270 96 200 96 Z" fill="{hair_fill}"/>"""
        hair_front = f"""
<path d="M200 100 C140 100 114 150 118 222 C130 172 165 135 214 127 C245 136 270 166 282 222 C288 150 262 100 200 100 Z" fill="{hair_fill}"/>
<path d="M190 104 C160 120 136 160 126 210 M214 108 C246 128 266 160 276 208" stroke="{strand}" stroke-width="2" fill="none" opacity=".6"/>"""

    return f"""
<g transform="translate({x} {y}) scale({s})">
{hair_back}
{body}
<path d="M172 318 L172 392 Q200 410 228 392 L228 318 Z" fill="url(#{p}neck)"/>
<ellipse cx="120" cy="228" rx="10" ry="22" fill="{SKIN_SHADOW}"/>
<ellipse cx="280" cy="228" rx="10" ry="22" fill="{SKIN_SHADOW}"/>
{ear_rings}
<path d="M200 120 C255 120 280 160 280 215 C280 270 260 315 230 340 C218 350 208 355 200 355 C192 355 182 350 170 340 C140 315 120 270 120 215 C120 160 145 120 200 120 Z" fill="url(#{p}face)"/>
<circle cx="150" cy="268" r="26" fill="url(#{p}blush)"/>
<circle cx="250" cy="268" r="26" fill="url(#{p}blush)"/>
<path d="M142 200 Q162 186 187 196" stroke="{brow_color}" stroke-width="4.5" fill="none" stroke-linecap="round"/>
<path d="M213 196 Q238 186 258 200" stroke="{brow_color}" stroke-width="4.5" fill="none" stroke-linecap="round"/>
{eye(165)}{eye(235)}
<path d="M201 228 C199 245 195 258 193 266 Q200 274 210 268" stroke="{SKIN_DEEP}" stroke-width="2.2" fill="none" stroke-linecap="round"/>
<path d="M178 298 Q190 289 200 294 Q210 289 222 298 Q200 303 178 298 Z" fill="{LIPS}"/>
<path d="M178 298 Q200 320 222 298 Q200 303 178 298 Z" fill="#D88A80"/>
<path d="M192 307 Q200 310 208 307" stroke="#fff" stroke-opacity=".5" stroke-width="2" fill="none" stroke-linecap="round"/>
{hair_front}
{extras}
</g>"""


def face_profile(p, *, hair_color="url(#PFXbrown)", top_color="#1C1615", bun=True):
    """Rosto de perfil (virado para a direita) num quadro de 400x500."""
    hair_color = hair_color.replace("PFX", p)
    bun_svg = f'<circle cx="150" cy="108" r="44" fill="{hair_color}"/>' if bun else ""
    return f"""
{bun_svg}
<path d="M60 520 C70 450 110 420 150 410 L225 405 C260 420 300 450 320 520 Z" fill="{top_color}"/>
<path d="M150 300 L150 420 Q190 440 228 415 L222 345 Z" fill="url(#{p}neck)"/>
<path d="M155 112 C210 96 262 122 272 175 C275 195 272 205 276 215 C280 226 286 242 298 262 C302 270 297 276 287 276 C285 282 287 288 283 292 C291 297 291 303 285 306 C289 312 287 318 281 320 C276 330 281 343 270 352 C255 362 235 362 220 358 C200 352 175 335 160 310 C130 270 110 160 155 112 Z" fill="{SKIN}"/>
<path d="M286 276 C280 279 276 279 272 276" stroke="{SKIN_DEEP}" stroke-width="2" fill="none" stroke-linecap="round"/>
<path d="M282 292 Q290 298 285 306 Q280 302 275 302 Z" fill="{LIPS}"/>
<path d="M284 307 Q288 313 281 320 Q276 314 276 309 Z" fill="#D88A80"/>
<circle cx="250" cy="282" r="24" fill="url(#{p}blush)"/>
<path d="M238 216 Q252 226 264 218" stroke="#3A2620" stroke-width="2.6" fill="none" stroke-linecap="round"/>
<path d="M244 222 l-2 5 M251 224 l0 5 M258 222 l2 5" stroke="#3A2620" stroke-width="1.2" stroke-linecap="round"/>
<path d="M234 198 Q252 190 268 196" stroke="#4A3127" stroke-width="3.5" fill="none" stroke-linecap="round"/>
<ellipse cx="180" cy="238" rx="13" ry="22" fill="{SKIN_SHADOW}"/>
<circle cx="180" cy="266" r="4" fill="#D9B77C"/>
<path d="M155 112 C210 96 255 115 262 158 C240 150 215 160 196 190 C186 205 175 215 168 240 C150 230 140 210 136 185 C130 150 135 125 155 112 Z" fill="{hair_color}"/>
<path d="M165 120 C200 112 235 120 252 146 M150 140 C160 170 165 200 168 232" stroke="#fff" stroke-opacity=".18" stroke-width="2" fill="none"/>"""


def bg_soft(color_a=CREAM, color_b="#EEE3DB", pid="bg"):
    return (
        f'<linearGradient id="{pid}" x1="0" y1="0" x2="1" y2="1">'
        f'<stop offset="0" stop-color="{color_a}"/><stop offset="1" stop-color="{color_b}"/>'
        "</linearGradient>"
    )


def write(name, content):
    (OUT / name).write_text(content, encoding="utf-8")
    print("ok", name)


# ---------------------------------------------------------------- logo
write("logo.svg", """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" width="120" height="120">
<circle cx="60" cy="60" r="56" fill="none" stroke="#C99A7E" stroke-width="2"/>
<circle cx="60" cy="60" r="50" fill="none" stroke="#C99A7E" stroke-width=".8" opacity=".6"/>
<text x="51" y="78" font-family="Georgia, 'Times New Roman', serif" font-size="56" font-style="italic" fill="#C99A7E" text-anchor="middle">L</text>
<text x="70" y="74" font-family="Georgia, 'Times New Roman', serif" font-size="40" fill="#C99A7E" text-anchor="middle" opacity=".85">D</text>
</svg>
""")

# ---------------------------------------------------------------- hero
p = "h_"
hero_extras = f"""
<path d="M300 520 C300 470 290 430 268 400 C255 382 248 360 252 338 C255 322 268 318 274 330 C278 340 274 352 276 362 C290 380 312 420 330 520 Z" fill="{SKIN}"/>
<path d="M252 338 C246 328 236 318 230 322 C224 326 232 336 240 344" fill="{SKIN}" stroke="{SKIN_SHADOW}" stroke-width="2"/>
<path d="M256 334 C258 322 268 316 272 324" stroke="{SKIN_SHADOW}" stroke-width="2" fill="none"/>
<rect x="268" y="392" width="16" height="7" rx="3" transform="rotate(-50 276 396)" fill="#D9B77C"/>"""
write("hero-dra-lidiane.svg", svg(400, 520, f"""
<rect width="400" height="520" fill="url(#hbg)"/>
<path d="M-20 120 C80 60 150 160 260 90 S420 60 440 30" stroke="#fff" stroke-width="40" fill="none" opacity=".45"/>
<path d="M-20 300 C90 240 170 330 280 260 S420 230 440 200" stroke="#fff" stroke-width="28" fill="none" opacity=".35"/>
{face_front(p, top="clothes", top_color="#4B2E24", extras=hero_extras)}
""", common_defs(p) + bg_soft("#F3EEEA", "#E6DCD5", "hbg")))

# ---------------------------------------------------------------- tratamentos
def card(name, extras="", eyes="closed", hair="brown", profile=False):
    p = name[:3] + "_"
    if profile:
        body = face_profile(p, top_color=SKIN, bun=True)
    else:
        body = face_front(p, x=10, y=10, s=0.95, hair=hair, eyes=eyes, top="skin", earrings=False)
    write(f"tratamento-{name}.svg", svg(400, 300, f"""
<rect width="400" height="300" fill="url(#{p}bg)"/>
<g transform="translate(40 -40) scale(.8)">{body}</g>
{extras}
""", common_defs(p) + bg_soft("#F5EFEB", "#E8DCD3", f"{p}bg")))

DOT = lambda cx, cy: f'<circle cx="{cx}" cy="{cy}" r="3.2" fill="#fff" stroke="{ROSE}" stroke-width="1.6"/>'
# Coordenadas finais = 40 + 0.8*(10 + 0.95*x), -40 + 0.8*(10 + 0.95*y)
def fx(x): return 40 + 0.8 * (10 + 0.95 * x)
def fy(y): return -40 + 0.8 * (10 + 0.95 * y)

card("harmonizacao", f"""
<g stroke="{ROSE}" stroke-width="1.3" stroke-dasharray="4 4" fill="none" opacity=".9">
  <line x1="{fx(120)}" y1="{fy(200)}" x2="{fx(280)}" y2="{fy(200)}"/>
  <line x1="{fx(120)}" y1="{fy(270)}" x2="{fx(280)}" y2="{fy(270)}"/>
  <line x1="{fx(200)}" y1="{fy(110)}" x2="{fx(200)}" y2="{fy(360)}"/>
  <path d="M{fx(130)} {fy(240)} L{fx(200)} {fy(355)} L{fx(270)} {fy(240)}"/>
</g>""", eyes="closed", hair="brown")

card("botox", "".join(DOT(fx(x), fy(y)) for x, y in [(170, 160), (200, 152), (230, 160), (185, 180), (215, 180), (140, 215), (260, 215)]) + f"""
<g transform="translate(300 60) rotate(35)">
  <rect x="0" y="0" width="60" height="14" rx="3" fill="#fff" stroke="{ROSE}" stroke-width="2"/>
  <rect x="6" y="3" width="30" height="8" fill="{ROSE_LIGHT}"/>
  <line x1="60" y1="7" x2="86" y2="7" stroke="#8C7266" stroke-width="2"/>
  <rect x="-10" y="-4" width="10" height="22" rx="2" fill="{ROSE}"/>
</g>""", eyes="open", hair="blonde")

card("preenchimento", "".join(DOT(fx(x), fy(y)) for x, y in [(185, 296), (200, 292), (215, 296), (192, 310), (208, 310), (150, 262), (250, 262)]) + f"""
<ellipse cx="{fx(200)}" cy="{fy(302)}" rx="40" ry="20" fill="none" stroke="{ROSE}" stroke-width="1.3" stroke-dasharray="3 4"/>""",
     eyes="closed", hair="blonde")

sparkle = lambda cx, cy, r: f'<path d="M{cx} {cy-r} Q{cx+2} {cy-2} {cx+r} {cy} Q{cx+2} {cy+2} {cx} {cy+r} Q{cx-2} {cy+2} {cx-r} {cy} Q{cx-2} {cy-2} {cx} {cy-r} Z" fill="#fff"/>'
card("bioestimuladores", f"""
<circle cx="{fx(150)}" cy="{fy(262)}" r="30" fill="#fff" opacity=".35"/>
<circle cx="{fx(250)}" cy="{fy(262)}" r="30" fill="#fff" opacity=".35"/>
{sparkle(fx(140), fy(240), 9)}{sparkle(fx(262), fy(250), 7)}{sparkle(fx(230), fy(150), 6)}{sparkle(320, 70, 10)}{sparkle(85, 100, 7)}""",
     eyes="closed", hair="brown")

card("rinomodelacao", f"""
<g transform="translate(40 -40) scale(.8)">
  <path d="M275 214 C279 226 286 242 296 260" stroke="#fff" stroke-width="3" fill="none" stroke-dasharray="4 5"/>
  <circle cx="275" cy="214" r="4" fill="#fff" stroke="{ROSE}" stroke-width="2"/>
  <circle cx="284" cy="238" r="4" fill="#fff" stroke="{ROSE}" stroke-width="2"/>
  <circle cx="296" cy="260" r="4" fill="#fff" stroke="{ROSE}" stroke-width="2"/>
</g>""", profile=True)

card("fios-pdo", f"""
<g stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round" opacity=".95">
  <path d="M{fx(125)} {fy(200)} C{fx(140)} {fy(250)} {fx(160)} {fy(290)} {fx(180)} {fy(320)}"/>
  <path d="M{fx(130)} {fy(185)} C{fx(150)} {fy(235)} {fx(170)} {fy(265)} {fx(185)} {fy(285)}"/>
  <path d="M{fx(275)} {fy(200)} C{fx(260)} {fy(250)} {fx(240)} {fy(290)} {fx(220)} {fy(320)}"/>
  <path d="M{fx(270)} {fy(185)} C{fx(250)} {fy(235)} {fx(230)} {fy(265)} {fx(215)} {fy(285)}"/>
</g>
<g stroke="{ROSE}" stroke-width="1" fill="none" stroke-dasharray="2 4">
  <path d="M{fx(125)} {fy(200)} C{fx(140)} {fy(250)} {fx(160)} {fy(290)} {fx(180)} {fy(320)}"/>
  <path d="M{fx(275)} {fy(200)} C{fx(260)} {fy(250)} {fx(240)} {fy(290)} {fx(220)} {fy(320)}"/>
</g>""", eyes="closed", hair="blonde")

# ---------------------------------------------------------------- experiência (perfil, fundo escuro)
p = "e_"
write("experiencia-dra-lidiane.svg", svg(400, 520, f"""
<rect width="400" height="520" fill="url(#ebg)"/>
<path d="M-40 380 C80 300 200 420 440 260" stroke="#fff" stroke-width="60" fill="none" opacity=".03"/>
<g transform="translate(-20 10)">{face_profile(p, top_color="#1F1918")}</g>
<path d="M120 440 Q170 470 230 440" stroke="#D9B77C" stroke-width="1.5" fill="none" opacity=".7"/>
""", common_defs(p) + bg_soft("#221B1A", "#0E0B0B", "ebg")))

# ---------------------------------------------------------------- sobre (blazer, fundo escuro)
p = "s_"
write("sobre-dra-lidiane.svg", svg(400, 520, f"""
<rect width="400" height="520" fill="url(#sbg)"/>
<circle cx="200" cy="230" r="190" fill="#fff" opacity=".04"/>
{face_front(p, top="clothes", top_color="#141010", blazer="#6B4430", eyes="open")}
<path d="M168 408 Q200 440 232 408" stroke="#D9B77C" stroke-width="1.6" fill="none"/>
<circle cx="200" cy="433" r="4" fill="#D9B77C"/>
""", common_defs(p) + bg_soft("#1E1817", "#0D0A0A", "sbg")))

# ---------------------------------------------------------------- antes e depois
def before_after(name, eyes, hair, after_extra, before_extra=""):
    pa, pb = name[:3] + "a_", name[:3] + "b_"
    defs = common_defs(pa) + common_defs(pb) + f"""
<clipPath id="{name}L"><rect x="0" y="0" width="200" height="300"/></clipPath>
<clipPath id="{name}R"><rect x="200" y="0" width="200" height="300"/></clipPath>"""
    write(f"antes-depois-{name}.svg", svg(400, 300, f"""
<rect width="400" height="300" fill="#EEE2D9"/>
<g clip-path="url(#{name}L)"><rect width="200" height="300" fill="#E9DCD2"/>
  <g transform="translate(72 -10) scale(.64)">{face_front(pa, hair=hair, eyes=eyes, top="skin", earrings=False, extras=before_extra)}</g></g>
<g clip-path="url(#{name}R)"><rect x="200" width="200" height="300" fill="#F4ECE6"/>
  <g transform="translate(72 -10) scale(.64)">{face_front(pb, hair=hair, eyes=eyes, top="skin", earrings=False, extras=after_extra)}</g></g>
<line x1="200" y1="0" x2="200" y2="300" stroke="#fff" stroke-width="3"/>
<rect x="12" y="12" width="56" height="22" rx="11" fill="#fff" opacity=".85"/>
<text x="40" y="27" font-family="Arial, sans-serif" font-size="11" fill="#6B4A3A" text-anchor="middle" letter-spacing="1">ANTES</text>
<rect x="324" y="12" width="64" height="22" rx="11" fill="{ROSE}"/>
<text x="356" y="27" font-family="Arial, sans-serif" font-size="11" fill="#fff" text-anchor="middle" letter-spacing="1">DEPOIS</text>
""", defs))

lines_before = f'<path d="M165 172 Q200 166 235 172 M170 184 Q200 179 230 184" stroke="{SKIN_DEEP}" stroke-width="1.6" fill="none" opacity=".7"/><path d="M178 286 Q170 300 172 318 M222 286 Q230 300 228 318" stroke="{SKIN_DEEP}" stroke-width="1.6" fill="none" opacity=".6"/>'
glow = '<ellipse cx="150" cy="255" rx="22" ry="12" fill="#fff" opacity=".35"/><ellipse cx="250" cy="255" rx="22" ry="12" fill="#fff" opacity=".35"/>'
before_after("harmonizacao", "open", "blonde", glow, lines_before)
before_after("preenchimento", "closed", "brown",
             f'<path d="M174 298 Q190 285 200 291 Q210 285 226 298 Q200 304 174 298 Z" fill="{LIPS}"/><path d="M174 298 Q200 326 226 298 Q200 304 174 298 Z" fill="#D88A80"/>{glow}',
             f'<path d="M182 298 Q200 294 218 298 Q200 302 182 298 Z" fill="#B87268"/><path d="M182 298 Q200 308 218 298 Z" fill="#C98A82"/>')
before_after("botox", "open", "brown", glow, lines_before)

# ---------------------------------------------------------------- avatar
write("avatar.svg", svg(80, 80, f"""
<circle cx="40" cy="40" r="40" fill="#EDE1D8"/>
<g transform="translate(4 2) scale(.18)">{face_front("av_", hair="blonde", top="clothes", top_color="#C99A7E", earrings=False)}</g>
""", common_defs("av_")))

# ---------------------------------------------------------------- clínica
write("clinica.svg", svg(600, 300, f"""
<rect width="600" height="300" fill="url(#cbg)"/>
<rect x="300" y="0" width="300" height="300" fill="#2F4A35"/>
<g fill="#3D6145" opacity=".9">
  {''.join(f'<ellipse cx="{300+ (i*37)%300}" cy="{(i*53)%300}" rx="18" ry="9" transform="rotate({(i*47)%180} {300+(i*37)%300} {(i*53)%300})"/>' for i in range(60))}
</g>
<g fill="#4F7A57" opacity=".8">
  {''.join(f'<ellipse cx="{305+ (i*29)%290}" cy="{(i*41)%300}" rx="14" ry="6" transform="rotate({(i*71)%180} {305+(i*29)%290} {(i*41)%300})"/>' for i in range(50))}
</g>
<circle cx="450" cy="130" r="66" fill="none" stroke="#E8C998" stroke-width="3"/>
<text x="440" y="158" font-family="Georgia, serif" font-size="74" font-style="italic" fill="#E8C998" text-anchor="middle">L</text>
<text x="472" y="152" font-family="Georgia, serif" font-size="52" fill="#E8C998" text-anchor="middle">D</text>
<rect x="0" y="230" width="600" height="70" fill="#E7DDD4"/>
<path d="M60 300 L70 210 Q75 190 95 190 L175 190 Q195 190 200 210 L210 300 Z" fill="#fff"/>
<path d="M78 215 Q135 200 192 215 L195 240 L75 240 Z" fill="#F4EEE9"/>
<rect x="225" y="190" width="40" height="70" rx="4" fill="#fff" opacity=".9"/>
<path d="M245 190 C230 140 210 100 180 70 M245 190 C255 140 270 110 290 90 M245 190 C240 150 250 120 245 80" stroke="#7C8C6A" stroke-width="2.5" fill="none"/>
{''.join(f'<g transform="translate({x} {y})"><circle r="11" fill="#fff"/><circle r="5" fill="#F2E6DA"/><circle r="2" fill="#E2B08A"/></g>' for x, y in [(180,70),(195,88),(170,95),(290,90),(280,108),(298,118),(245,80),(236,98),(256,104),(210,120),(270,140)])}
""", bg_soft("#F3ECE6", "#E3D6CC", "cbg")))

# ---------------------------------------------------------------- mapa
streets = []
for i, x in enumerate(range(20, 420, 70)):
    streets.append(f'<rect x="{x}" y="0" width="{14 if i % 2 else 9}" height="400" fill="#fff"/>')
for i, y in enumerate(range(30, 420, 80)):
    streets.append(f'<rect x="0" y="{y}" width="420" height="{14 if i % 2 else 9}" fill="#fff"/>')
blocks = "".join(
    f'<rect x="{x+16}" y="{y+16}" width="{48}" height="{58}" rx="3" fill="{c}"/>'
    for x in range(20, 420, 70) for y in range(30, 420, 80)
    for c in [["#E4E0D8", "#DDE5D3", "#E8E3DA"][(x + y) % 3]]
)
write("mapa.svg", svg(420, 400, f"""
<rect width="420" height="400" fill="#ECE8E1"/>
{blocks}
{''.join(streets)}
<path d="M0 360 C120 300 240 330 420 250" stroke="#F6D88E" stroke-width="12" fill="none"/>
<path d="M0 360 C120 300 240 330 420 250" stroke="#EAC56C" stroke-width="1" fill="none"/>
<g transform="translate(205 180)">
  <ellipse cx="0" cy="34" rx="14" ry="4" fill="#000" opacity=".2"/>
  <path d="M0 34 C-6 20 -22 8 -22 -8 A22 22 0 1 1 22 -8 C22 8 6 20 0 34 Z" fill="#D9473B"/>
  <circle cx="0" cy="-8" r="8" fill="#fff"/>
</g>
<rect x="232" y="160" width="132" height="36" rx="6" fill="#fff" opacity=".95"/>
<text x="242" y="176" font-family="Arial, sans-serif" font-size="11" fill="#D9473B" font-weight="bold">Dra. Lidiane</text>
<text x="242" y="190" font-family="Arial, sans-serif" font-size="10" fill="#555">Estética Facial · Capivari</text>
"""))
