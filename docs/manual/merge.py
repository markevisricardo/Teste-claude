"""Junta a capa e o corpo do manual num só PDF, mantendo marcadores e links."""
import sys
from pypdf import PdfWriter

cover, body, out = sys.argv[1:4]
writer = PdfWriter()
writer.append(cover)
writer.append(body, import_outline=True)
writer.add_metadata({"/Title": "Post Flow — Manual e Tutorial", "/Author": "Post Flow", "/Subject": "Instalação, configuração e uso do Post Flow"})
writer.page_mode = "/UseOutlines"
with open(out, "wb") as f:
    writer.write(f)
