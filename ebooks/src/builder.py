"""Gera os e-books da Coleção Acelerador de Clínicas em HTML (para impressão em PDF)."""
import html, importlib, sys, os, json

SITE = "aceleradordeclinicas.com.br"
BRAND = "Acelerador de Clínicas"

COLECAO = [
    (1, "DNA Comercial", "Defina o que vender e como explicar", "#7B4DE0"),
    (2, "Referência na Cidade", "Seja encontrado no Google, nas IAs e na imprensa", "#2F6BF0"),
    (3, "Vitrine Magnética", "Instagram que vende a consulta", "#E0457B"),
    (4, "Recepção que Fatura", "Converta interessados em pacientes", "#0E9C9C"),
    (5, "Consulta Assinatura", "Entregue uma consulta de alto valor", "#C8932B"),
    (6, "Resgate de Ouro", "Reative quem já confiou em você", "#E07B1F"),
]


def vol(n):
    return next(c for c in COLECAO if c[0] == n)


CSS = open(os.path.join(os.path.dirname(__file__), "style.css"), encoding="utf-8").read()


def ul(items, cls=""):
    return f'<ul class="{cls}">' + "".join(f"<li>{i}</li>" for i in items) + "</ul>"


def cover(e):
    n, nome, _, cor = vol(e["vol"])
    return f"""
<section class="cover">
  <div class="cover-shape s1"></div><div class="cover-shape s2"></div>
  <div class="cover-top">
    <div class="brandmark"><span class="bm-dot"></span>{BRAND}</div>
    <div class="cover-vol">Coleção Acelerador de Clínicas · Volume {n:02d}</div>
  </div>
  <div class="cover-mid">
    <div class="cover-kicker">{e['metodo_nome']}</div>
    <h1 class="cover-title">{e['titulo']}</h1>
    <p class="cover-sub">{e['subtitulo']}</p>
  </div>
  <div class="cover-puv">
    <div class="puv-label">Proposta Única de Valor</div>
    <p>{e['puv']}</p>
  </div>
  <div class="cover-foot">
    <div>Uma metodologia <b>{BRAND}</b></div>
    <div class="site">{SITE}</div>
  </div>
</section>"""


def jornada(atual):
    out = '<div class="journey">'
    for n, nome, desc, cor in COLECAO:
        cls = "j-item on" if n == atual else "j-item"
        out += f'<div class="{cls}" style="--c:{cor}"><span class="j-n">{n:02d}</span><span class="j-t">{nome}</span><span class="j-d">{desc}</span></div>'
    return out + "</div>"


def intro(e):
    paras = "".join(f"<p>{p}</p>" for p in e["porque"])
    return f"""
<section class="page-start">
  <div class="eyebrow">Antes de começar</div>
  <h2>Por que este e-book existe</h2>
  {paras}
  <div class="puv-box">
    <div class="puv-label">A promessa deste volume (PUV)</div>
    <p class="puv-big">{e['puv']}</p>
    <p class="puv-dif"><b>O que torna este método único:</b> {e['diferencial']}</p>
  </div>
  <div class="two-col">
    <div class="card"><h4>Para quem é</h4>{ul(e['para_quem'], 'check')}</div>
    <div class="card"><h4>O que você conquista</h4>{ul(e['conquista'], 'arrow')}</div>
  </div>
</section>"""


def diagnostico(e):
    qs = "".join(f'<li><span class="box"></span>{q}</li>' for q in e["diag_perguntas"])
    bands = "".join(
        f'<div class="band"><div class="band-r">{r}</div><div><b>{t}</b><br>{d}</div></div>'
        for r, t, d in e["diag_faixas"]
    )
    return f"""
<section class="page-start">
  <div class="eyebrow">Diagnóstico rápido · 3 minutos</div>
  <h2>Onde sua clínica está hoje</h2>
  <p>Marque cada afirmação verdadeira. Cada marcação vale 1 ponto. A pontuação diz por onde começar — e o que ainda não adianta fazer.</p>
  <ol class="diag">{qs}</ol>
  <div class="bands">{bands}</div>
</section>"""


def raciocinio(e):
    chain = ""
    for i, (lab, txt) in enumerate(e["raciocinio"]):
        chain += f'<div class="chain-item"><div class="chain-n">{i+1}</div><div><div class="chain-lab">{lab}</div><p>{txt}</p></div></div>'
    return f"""
<section class="page-start">
  <div class="eyebrow">Linha de raciocínio</div>
  <h2>A lógica por trás do método</h2>
  <p>Antes da técnica, o porquê. Se você entender esta sequência, consegue adaptar o método a qualquer especialidade, tamanho de equipe ou cidade.</p>
  <div class="chain">{chain}</div>
  <div class="tese"><span>Tese central</span>{e['tese']}</div>
</section>"""


def metodo(e):
    cells = "".join(
        f'<div class="m-cell"><div class="m-letter">{l}</div><div class="m-name">{n}</div><div class="m-desc">{d}</div></div>'
        for l, n, d, *_ in [(s["letra"], s["nome"], s["resumo"]) for s in e["passos"]]
    )
    return f"""
<section class="page-start">
  <div class="eyebrow">O método</div>
  <h2>{e['metodo_nome']}</h2>
  <p>{e['metodo_intro']}</p>
  <div class="m-grid">{cells}</div>
  <p class="muted small">Regra de ouro: cada etapa pressupõe a anterior resolvida. Pular etapas é a forma mais rápida de concluir que “isso não funciona na minha área”.</p>
</section>"""


def passos(e):
    out = ""
    for i, s in enumerate(e["passos"]):
        ferr = ""
        if s.get("ferramenta"):
            t, body = s["ferramenta"]
            ferr = f'<div class="tool"><div class="tool-h">{t}</div>{body}</div>'
        out += f"""
<section class="step {'page-start' if i % 1 == 0 else ''}">
  <div class="step-head"><div class="step-letter">{s['letra']}</div>
    <div><div class="eyebrow">Etapa {i+1} de {len(e['passos'])}</div><h2>{s['nome']}</h2></div></div>
  <div class="obj"><b>Objetivo:</b> {s['objetivo']}</div>
  <h4>Raciocínio</h4>
  <p>{s['raciocinio']}</p>
  <h4>Como aplicar</h4>
  {ul(s['aplicar'], 'steps')}
  {ferr}
  <div class="erro"><b>Erro comum:</b> {s['erro']}</div>
</section>"""
    return out


def roadmap(e):
    cols = ""
    for per, nome, itens, entrega in e["roadmap"]:
        cols += f'<div class="rm-col"><div class="rm-per">{per}</div><div class="rm-nome">{nome}</div>{ul(itens)}<div class="rm-ent"><b>Entregável:</b> {entrega}</div></div>'
    return f"""
<section class="page-start">
  <div class="eyebrow">Roadmap de implementação</div>
  <h2>{e.get('roadmap_titulo', 'Seu plano de execução')}</h2>
  <p>{e.get('roadmap_intro', 'Execute na ordem. Cada fase termina com um entregável concreto — se ele não existir, a fase não acabou.')}</p>
  <div class="rm">{cols}</div>
</section>"""


def aplicabilidade(e):
    perfis = "".join(f"<tr><td><b>{p}</b></td><td>{c}</td></tr>" for p, c in e["perfis"])
    papeis = "".join(f"<tr><td><b>{p}</b></td><td>{c}</td></tr>" for p, c in e["papeis"])
    inds = "".join(f"<tr><td><b>{m}</b></td><td>{q}</td><td>{o}</td></tr>" for m, q, o in e["indicadores"])
    return f"""
<section class="page-start">
  <div class="eyebrow">Aplicabilidade</div>
  <h2>Como aplicar na sua realidade</h2>
  <h3>Por perfil de clínica</h3>
  <table><thead><tr><th style="width:28%">Perfil</th><th>Como adaptar</th></tr></thead><tbody>{perfis}</tbody></table>
  <h3 class="mt">Quem faz o quê</h3>
  <table><thead><tr><th style="width:28%">Papel</th><th>Responsabilidade</th></tr></thead><tbody>{papeis}</tbody></table>
  <div class="keep"><h3 class="mt">Indicadores que provam que está funcionando</h3>
  <table><thead><tr><th style="width:26%">Indicador</th><th>O que responde</th><th style="width:26%">Como medir</th></tr></thead><tbody>{inds}</tbody></table></div>
</section>"""


def modelos(e):
    cards = ""
    for t, txt, obs in e["modelos"]:
        o = f'<div class="script-obs"><b>Por que funciona:</b> {obs}</div>' if obs else ""
        cards += f'<div class="script"><div class="script-h">{t}</div><div class="script-b">{txt}</div>{o}</div>'
    return f"""
<section class="page-start">
  <div class="eyebrow">Modelos prontos</div>
  <h2>Copie, adapte e use hoje</h2>
  <p>Troque apenas o que está entre colchetes. Ajuste o tom ao seu público — e valide sempre com as regras do seu conselho profissional.</p>
  {cards}
</section>"""


def conexao(e):
    c = e["conexao"]
    n, nome, desc, cor = vol(c["vol"])
    sec = ""
    if c.get("secundario"):
        sn, sl = c["secundario"]
        s = vol(sn)
        sec = f'<div class="sec-ref" style="--c:{s[3]}"><b>Também combina com o Volume {sn:02d} · {s[1]}:</b> {sl}</div>'
    paras = "".join(f"<p>{p}</p>" for p in c["como"])
    return f"""
<section class="page-start">
  <div class="eyebrow">Conexão com a coleção</div>
  <h2>Leia em seguida</h2>
  <div class="xref" style="--c:{cor}">
    <div class="xref-tag">Volume {n:02d} · Coleção Acelerador de Clínicas</div>
    <div class="xref-title">{nome}</div>
    <div class="xref-desc">{desc}</div>
  </div>
  <h3>Como ele potencializa o que você acabou de aprender</h3>
  {paras}
  <h4>Combinações práticas</h4>
  {ul(c['combos'], 'arrow')}
  {sec}
  <h3 class="mt">Onde este volume entra na jornada da clínica</h3>
  <p class="muted">A coleção segue a ordem em que um paciente descobre, decide e volta. Cada volume resolve um elo — e conversa com os outros.</p>
  {jornada(e['vol'])}
</section>"""


def fechamento(e):
    paras = "".join(f"<p>{p}</p>" for p in e["fechamento"])
    return f"""
<section class="page-start">
  <div class="eyebrow">Para terminar</div>
  <h2>{e.get('fechamento_titulo', 'O que separa quem executa de quem só lê')}</h2>
  {paras}
  <div class="compliance"><b>Nota ética e legal:</b> este material é educativo sobre gestão, comunicação e vendas em saúde. Não constitui orientação clínica nem parecer jurídico. Regras de publicidade variam por conselho (CFM, CFO, CFN, COFFITO e outros) e mudam com o tempo — confirme cada peça antes de publicar e trate dados de pacientes conforme a LGPD.</div>
</section>
<section class="back">
  <div class="cover-shape s1"></div>
  <div class="back-in">
    <div class="brandmark big"><span class="bm-dot"></span>{BRAND}</div>
    <p class="back-tag">Estratégia, processo e vendas para clínicas e consultórios que querem crescer com previsibilidade — e sem perder a essência do cuidado.</p>
    <div class="back-cta">
      <div class="back-cta-h">Quer implementar o {e['metodo_curto']} com acompanhamento?</div>
      <p>Agende um diagnóstico da sua clínica: onde o faturamento está vazando, o que priorizar nos próximos 90 dias e qual volume da coleção aplicar primeiro.</p>
      <div class="back-site">{SITE}</div>
    </div>
    <div class="back-list">{''.join(f'<span style="--c:{c[3]}">{c[0]:02d} · {c[1]}</span>' for c in COLECAO)}</div>
    <div class="back-sig">© {BRAND} · Coleção Acelerador de Clínicas · Volume {e['vol']:02d} — {e['titulo']}</div>
  </div>
</section>"""


def build(mod_name, outdir):
    e = importlib.import_module(mod_name).EBOOK
    n, nome, desc, cor = vol(e["vol"])
    body = (cover(e) + intro(e) + diagnostico(e) + raciocinio(e) + metodo(e) + passos(e)
            + roadmap(e) + aplicabilidade(e) + modelos(e) + conexao(e) + fechamento(e))
    doc = f"""<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">
<title>{e['titulo']} · {BRAND}</title>
<style>:root{{--accent:{cor};}}{CSS}</style></head><body>{body}</body></html>"""
    fname = f"Vol{n:02d}-{e['slug']}"
    path = os.path.join(outdir, fname + ".html")
    open(path, "w", encoding="utf-8").write(doc)
    return {"html": path, "pdf": os.path.join(outdir, fname + ".pdf"), "titulo": e["titulo"], "vol": n}


if __name__ == "__main__":
    sys.path.insert(0, os.path.dirname(__file__))
    outdir = sys.argv[1]
    res = [build(m, outdir) for m in sys.argv[2:]]
    print(json.dumps(res, ensure_ascii=False))
