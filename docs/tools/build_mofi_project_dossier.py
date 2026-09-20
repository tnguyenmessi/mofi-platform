from pathlib import Path
from math import ceil

from docx import Document
from docx.enum.section import WD_SECTION_START
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor
from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "docs" / "word"
SCRATCH = ROOT / ".document-qa" / "mofi-dossier"
OUT.mkdir(parents=True, exist_ok=True)
SCRATCH.mkdir(parents=True, exist_ok=True)

DOCX_PATH = OUT / "MOFI_Ho_so_du_an_va_huong_dan_su_dung.docx"

NAVY = "102A43"
BLUE = "087CF0"
PALE_BLUE = "EAF3FB"
PALE_GRAY = "F5F7F9"
MID_GRAY = "D9E1E8"
TEXT = "172B4D"
MUTED = "5F7185"
GREEN = "157A5B"
RED = "B33A4A"
WHITE = "FFFFFF"


def image_color(value: str) -> str:
    """PIL expects a leading hash for hexadecimal colors."""
    return value if value.startswith("#") else f"#{value}"


def font_path(name: str) -> str:
    candidates = [
        Path("C:/Windows/Fonts") / name,
        Path("C:/Windows/Fonts") / name.lower(),
    ]
    for candidate in candidates:
        if candidate.exists():
            return str(candidate)
    return "C:/Windows/Fonts/arial.ttf"


FONT_REG = font_path("arial.ttf")
FONT_BOLD = font_path("arialbd.ttf")


def set_run_font(run, name="Arial", size=None, bold=None, italic=None, color=None):
    run.font.name = name
    run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), name)
    run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), name)
    run._element.get_or_add_rPr().rFonts.set(qn("w:eastAsia"), name)
    if size is not None:
        run.font.size = Pt(size)
    if bold is not None:
        run.bold = bold
    if italic is not None:
        run.italic = italic
    if color is not None:
        run.font.color.rgb = RGBColor.from_string(color)


def set_style_font(style, name="Arial", size=10.5, bold=None, color="000000"):
    style.font.name = name
    style._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), name)
    style._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), name)
    style._element.get_or_add_rPr().rFonts.set(qn("w:eastAsia"), name)
    style.font.size = Pt(size)
    style.font.color.rgb = RGBColor.from_string(color)
    if bold is not None:
        style.font.bold = bold


def set_cell_shading(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = tc_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tc_pr.append(shd)
    shd.set(qn("w:fill"), fill)


def set_cell_margins(cell, top=100, start=120, bottom=100, end=120):
    tc = cell._tc
    tc_pr = tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for margin, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tc_mar.find(qn(f"w:{margin}"))
        if node is None:
            node = OxmlElement(f"w:{margin}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def set_table_borders(table, color=MID_GRAY, size="5"):
    tbl_pr = table._tbl.tblPr
    borders = tbl_pr.first_child_found_in("w:tblBorders")
    if borders is None:
        borders = OxmlElement("w:tblBorders")
        tbl_pr.append(borders)
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        tag = borders.find(qn(f"w:{edge}"))
        if tag is None:
            tag = OxmlElement(f"w:{edge}")
            borders.append(tag)
        tag.set(qn("w:val"), "single")
        tag.set(qn("w:sz"), size)
        tag.set(qn("w:space"), "0")
        tag.set(qn("w:color"), color)


def mark_header_row(row):
    tr_pr = row._tr.get_or_add_trPr()
    if tr_pr.find(qn("w:tblHeader")) is None:
        tr_pr.append(OxmlElement("w:tblHeader"))
    if tr_pr.find(qn("w:cantSplit")) is None:
        tr_pr.append(OxmlElement("w:cantSplit"))


def prevent_row_split(row):
    tr_pr = row._tr.get_or_add_trPr()
    if tr_pr.find(qn("w:cantSplit")) is None:
        tr_pr.append(OxmlElement("w:cantSplit"))


def remove_paragraph_border(paragraph):
    p_pr = paragraph._p.get_or_add_pPr()
    border = p_pr.find(qn("w:pBdr"))
    if border is not None:
        p_pr.remove(border)


def add_page_number(paragraph):
    paragraph.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    run = paragraph.add_run("MOFI  |  ")
    set_run_font(run, size=8.5, color=MUTED)
    field = OxmlElement("w:fldSimple")
    field.set(qn("w:instr"), "PAGE")
    paragraph._p.append(field)


def add_hyperlink(paragraph, text, url):
    part = paragraph.part
    rel_id = part.relate_to(url, "http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink", is_external=True)
    hyperlink = OxmlElement("w:hyperlink")
    hyperlink.set(qn("r:id"), rel_id)
    run = OxmlElement("w:r")
    r_pr = OxmlElement("w:rPr")
    color = OxmlElement("w:color")
    color.set(qn("w:val"), BLUE)
    r_pr.append(color)
    underline = OxmlElement("w:u")
    underline.set(qn("w:val"), "single")
    r_pr.append(underline)
    run.append(r_pr)
    text_node = OxmlElement("w:t")
    text_node.text = text
    run.append(text_node)
    hyperlink.append(run)
    paragraph._p.append(hyperlink)


def add_rich_paragraph(doc, lead, body, style=None):
    paragraph = doc.add_paragraph(style=style)
    first = paragraph.add_run(lead)
    set_run_font(first, bold=True, color=TEXT)
    second = paragraph.add_run(body)
    set_run_font(second, color=TEXT)
    return paragraph


def add_bullet(doc, text, level=0):
    paragraph = doc.add_paragraph(style="List Bullet" if level == 0 else "List Bullet 2")
    paragraph.paragraph_format.space_after = Pt(3)
    run = paragraph.add_run(text)
    set_run_font(run, size=10.5, color=TEXT)
    return paragraph


def add_number(doc, text):
    paragraph = doc.add_paragraph(style="List Number")
    paragraph.paragraph_format.space_after = Pt(4)
    run = paragraph.add_run(text)
    set_run_font(run, size=10.5, color=TEXT)
    return paragraph


def add_step_list(doc, items):
    for index, text in enumerate(items, start=1):
        paragraph = doc.add_paragraph()
        paragraph.paragraph_format.left_indent = Inches(0.25)
        paragraph.paragraph_format.first_line_indent = Inches(-0.25)
        paragraph.paragraph_format.space_after = Pt(4)
        number = paragraph.add_run(f"{index}.  ")
        set_run_font(number, size=10.5, bold=True, color=TEXT)
        body = paragraph.add_run(text)
        set_run_font(body, size=10.5, color=TEXT)
    return None


def add_table(doc, headers, rows, widths=None, font_size=9.2, header_fill=NAVY):
    table = doc.add_table(rows=1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    set_table_borders(table)
    if widths is None:
        widths = [1 / len(headers)] * len(headers)
    total_width = 7.0
    header = table.rows[0]
    mark_header_row(header)
    for index, (cell, value) in enumerate(zip(header.cells, headers)):
        cell.width = Inches(total_width * widths[index])
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        set_cell_shading(cell, header_fill)
        set_cell_margins(cell)
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.LEFT
        p.paragraph_format.space_before = Pt(3)
        p.paragraph_format.space_after = Pt(3)
        r = p.add_run(value)
        set_run_font(r, size=font_size, bold=True, color=WHITE)
    for row_index, values in enumerate(rows):
        row = table.add_row()
        prevent_row_split(row)
        for index, (cell, value) in enumerate(zip(row.cells, values)):
            cell.width = Inches(total_width * widths[index])
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            set_cell_shading(cell, PALE_GRAY if row_index % 2 == 0 else WHITE)
            set_cell_margins(cell)
            p = cell.paragraphs[0]
            p.paragraph_format.space_before = Pt(2)
            p.paragraph_format.space_after = Pt(2)
            p.paragraph_format.line_spacing = 1.05
            r = p.add_run(str(value))
            set_run_font(r, size=font_size, color=TEXT)
    doc.add_paragraph().paragraph_format.space_after = Pt(1)
    return table


def add_section_heading(doc, text, level=1):
    paragraph = doc.add_heading(text, level=level)
    paragraph.paragraph_format.keep_with_next = True
    paragraph.paragraph_format.space_before = Pt(12 if level == 1 else 7)
    paragraph.paragraph_format.space_after = Pt(5)
    for run in paragraph.runs:
        set_run_font(run, size={1: 16, 2: 12.5, 3: 11}[level], bold=True, color="000000")
    return paragraph


def add_body(doc, text, bold_lead=None):
    paragraph = doc.add_paragraph()
    paragraph.paragraph_format.space_after = Pt(6)
    paragraph.paragraph_format.line_spacing = 1.12
    if bold_lead and text.startswith(bold_lead):
        lead = paragraph.add_run(bold_lead)
        set_run_font(lead, bold=True, color=TEXT)
        rest = paragraph.add_run(text[len(bold_lead):])
        set_run_font(rest, color=TEXT)
    else:
        run = paragraph.add_run(text)
        set_run_font(run, color=TEXT)
    return paragraph


def add_code_line(doc, text):
    paragraph = doc.add_paragraph()
    paragraph.paragraph_format.left_indent = Inches(0.22)
    paragraph.paragraph_format.space_after = Pt(2)
    run = paragraph.add_run(text)
    set_run_font(run, name="Courier New", size=8.8, color=TEXT)
    return paragraph


def new_doc():
    doc = Document()
    section = doc.sections[0]
    section.page_width = Inches(8.5)
    section.page_height = Inches(11)
    section.top_margin = Inches(0.7)
    section.bottom_margin = Inches(0.65)
    section.left_margin = Inches(0.75)
    section.right_margin = Inches(0.75)
    section.header_distance = Inches(0.3)
    section.footer_distance = Inches(0.3)

    for style_name in ["Normal", "Title", "Subtitle", "Heading 1", "Heading 2", "Heading 3", "List Bullet", "List Bullet 2", "List Number"]:
        style = doc.styles[style_name]
        set_style_font(style, size=10.5, color=TEXT)
        style.paragraph_format.line_spacing = 1.12
        style.paragraph_format.space_after = Pt(5)
    set_style_font(doc.styles["Title"], size=27, bold=True, color="000000")
    set_style_font(doc.styles["Subtitle"], size=12, color=MUTED)
    for style_name, size in [("Heading 1", 16), ("Heading 2", 12.5), ("Heading 3", 11)]:
        style = doc.styles[style_name]
        set_style_font(style, size=size, bold=True, color="000000")
        style.paragraph_format.keep_with_next = True
    doc.styles["List Bullet"].paragraph_format.left_indent = Inches(0.22)
    doc.styles["List Bullet"].paragraph_format.first_line_indent = Inches(-0.12)
    doc.styles["List Bullet 2"].paragraph_format.left_indent = Inches(0.42)
    doc.styles["List Number"].paragraph_format.left_indent = Inches(0.25)

    footer = section.footer.paragraphs[0]
    add_page_number(footer)
    for border in list(doc.styles.element.iter(qn("w:pBdr"))):
        border.getparent().remove(border)
    for border in list(doc.element.iter(qn("w:pBdr"))):
        border.getparent().remove(border)
    doc.core_properties.author = "MOFI"
    doc.core_properties.title = "Hồ sơ dự án MOFI và hướng dẫn sử dụng"
    doc.core_properties.subject = "Hồ sơ giới thiệu, công nghệ, nghiệp vụ, hướng dẫn và thuật toán MOFI"
    return doc


def draw_arrow(draw, start, end, fill=NAVY, width=5):
    fill = image_color(fill)
    draw.line([start, end], fill=fill, width=width)
    x1, y1 = start
    x2, y2 = end
    if abs(x2 - x1) >= abs(y2 - y1):
        direction = 1 if x2 >= x1 else -1
        points = [(x2, y2), (x2 - direction * 20, y2 - 11), (x2 - direction * 20, y2 + 11)]
    else:
        direction = 1 if y2 >= y1 else -1
        points = [(x2, y2), (x2 - 11, y2 - direction * 20), (x2 + 11, y2 - direction * 20)]
    draw.polygon(points, fill=fill)


def draw_box(draw, box, title, subtitle=None, fill=PALE_BLUE, outline=NAVY, title_size=28, subtitle_size=19):
    fill = image_color(fill)
    outline = image_color(outline)
    draw.rounded_rectangle(box, radius=18, fill=fill, outline=outline, width=4)
    x1, y1, x2, y2 = box
    title_font = ImageFont.truetype(FONT_BOLD, title_size)
    body_font = ImageFont.truetype(FONT_REG, subtitle_size)
    title_box = draw.textbbox((0, 0), title, font=title_font)
    tx = x1 + ((x2 - x1) - (title_box[2] - title_box[0])) / 2
    ty = y1 + 24
    draw.text((tx, ty), title, font=title_font, fill=image_color(TEXT))
    if subtitle:
        lines = subtitle.split("\n")
        line_height = subtitle_size + 7
        for i, line in enumerate(lines):
            bbox = draw.textbbox((0, 0), line, font=body_font)
            draw.text(((x1 + x2 - (bbox[2] - bbox[0])) / 2, y1 + 78 + i * line_height), line, font=body_font, fill=image_color(MUTED))


def make_architecture_diagram():
    im = Image.new("RGB", (1800, 820), image_color(WHITE))
    draw = ImageDraw.Draw(im)
    draw.text((50, 28), "Kiến trúc xử lý MOFI", font=ImageFont.truetype(FONT_BOLD, 38), fill=image_color(TEXT))
    boxes = [
        ((55, 240, 325, 420), "Browser", "React 19\nTypeScript"),
        ((405, 240, 700, 420), "Laravel 13", "Routes, session, CSRF\nvalidation, policies"),
        ((785, 240, 1080, 420), "Domain services", "Ledger, valuation\nalerts, paper trading"),
        ((1165, 240, 1455, 420), "PostgreSQL", "Supabase\ntransactions and data"),
        ((1540, 240, 1745, 420), "Railway", "HTTPS deploy\nhealth check"),
    ]
    for box, title, subtitle in boxes:
        draw_box(draw, box, title, subtitle)
    for a, b in [((325, 330), (405, 330)), ((700, 330), (785, 330)), ((1080, 330), (1165, 330)), ((1455, 330), (1540, 330))]:
        draw_arrow(draw, a, b)
    line_font = ImageFont.truetype(FONT_REG, 20)
    draw.text((510, 480), "Inertia truyền props và form request giữa Laravel và React", font=line_font, fill=image_color(MUTED))
    draw.text((1145, 515), "Dữ liệu demo được seed; browser không kết nối trực tiếp DB", font=line_font, fill=image_color(MUTED))
    draw.line((140, 610, 1660, 610), fill=image_color(MID_GRAY), width=3)
    draw.text((140, 645), "Nguồn tham khảo crypto công khai", font=line_font, fill=image_color(GREEN))
    draw.text((140, 685), "chỉ dùng cho panel thị trường công khai; danh mục demo dùng fixture mô phỏng", font=line_font, fill=image_color(MUTED))
    path = SCRATCH / "architecture.png"
    im.save(path)
    return path


def make_business_flow_diagram():
    im = Image.new("RGB", (1900, 650), image_color(WHITE))
    draw = ImageDraw.Draw(im)
    draw.text((50, 28), "Luồng nghiệp vụ tài sản và giao dịch mô phỏng", font=ImageFont.truetype(FONT_BOLD, 38), fill=image_color(TEXT))
    titles = [
        ("1 Ý định", "User nhập\ngiao dịch", PALE_BLUE),
        ("2 Kiểm tra", "Owner, dữ liệu\nvalidation", "EEF6F0"),
        ("3 Giữ chỗ", "Cash hoặc\nquantity", "FFF5E8"),
        ("4 Khớp lệnh", "Paper order\nOPEN -> FILLED", "F3EEFA"),
        ("5 Sổ cái", "Transaction\nimmutable", "EEF6F0"),
        ("6 Tính lại", "Cash, Q, basis\nP/L, value", PALE_BLUE),
        ("7 Hiển thị", "Dashboard và\nreceipt", "F4F6F8"),
    ]
    width = 225
    gap = 38
    x = 38
    for i, (title, subtitle, fill) in enumerate(titles):
        draw_box(draw, (x, 210, x + width, 410), title, subtitle, fill=fill, title_size=24, subtitle_size=20)
        if i < len(titles) - 1:
            draw_arrow(draw, (x + width, 310), (x + width + gap, 310), fill=NAVY, width=4)
        x += width + gap
    draw.text((180, 500), "Mọi bước ghi dữ liệu nằm trong DB transaction; lỗi thì rollback toàn bộ.", font=ImageFont.truetype(FONT_REG, 23), fill=image_color(MUTED))
    path = SCRATCH / "business-flow.png"
    im.save(path)
    return path


def make_erd_diagram():
    im = Image.new("RGB", (1800, 1150), image_color(WHITE))
    draw = ImageDraw.Draw(im)
    draw.text((50, 30), "ERD rút gọn cho bản demo", font=ImageFont.truetype(FONT_BOLD, 38), fill=image_color(TEXT))
    nodes = {
        "users": (690, 110, 1110, 230),
        "portfolios": (120, 360, 540, 480),
        "instruments": (1260, 360, 1680, 480),
        "transactions": (690, 610, 1110, 750),
        "market_prices": (1260, 610, 1680, 750),
        "orders": (120, 850, 540, 970),
        "goals and tasks": (690, 850, 1110, 970),
        "assets alerts\nwatchlist": (1260, 850, 1680, 970),
    }
    for name, box in nodes.items():
        draw_box(draw, box, name, "PK id\nowner scoped", fill=PALE_BLUE, title_size=26, subtitle_size=18)
    links = [
        ((900, 230), (330, 360), "users 1:N"),
        ((900, 230), (1470, 360), "users 1:N"),
        ((330, 480), (900, 610), "portfolio 1:N"),
        ((1470, 480), (900, 610), "instrument 1:N"),
        ((1470, 480), (1470, 610), "instrument 1:N"),
        ((330, 480), (330, 850), "portfolio 1:N"),
        ((900, 230), (900, 850), "user 1:N"),
        ((900, 230), (1470, 850), "user 1:N"),
    ]
    label_font = ImageFont.truetype(FONT_REG, 18)
    for start, end, label in links:
        draw_arrow(draw, start, end, fill=MUTED, width=3)
        lx = int((start[0] + end[0]) / 2) - 45
        ly = int((start[1] + end[1]) / 2) - 18
        draw.text((lx, ly), label, font=label_font, fill=image_color(MUTED))
    draw.text((90, 1040), "Bản demo giữ dữ liệu nghiệp vụ trong Laravel và PostgreSQL; quyền truy cập luôn giới hạn theo user.", font=label_font, fill=image_color(MUTED))
    path = SCRATCH / "erd.png"
    im.save(path)
    return path


def add_picture(doc, path, width=6.9, caption=None):
    paragraph = doc.add_paragraph()
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = paragraph.add_run()
    picture = run.add_picture(str(path), width=Inches(width))
    picture._inline.docPr.set("descr", caption or "Sơ đồ trong tài liệu MOFI")
    if caption:
        cap = doc.add_paragraph()
        cap.alignment = WD_ALIGN_PARAGRAPH.CENTER
        cap.paragraph_format.space_after = Pt(8)
        r = cap.add_run(caption)
        set_run_font(r, size=8.5, italic=True, color=MUTED)


def add_title_page(doc):
    for _ in range(2):
        doc.add_paragraph()
    p = doc.add_paragraph(style="Title")
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run("Hồ sơ dự án MOFI")
    set_run_font(run, size=30, bold=True, color="000000")
    p2 = doc.add_paragraph(style="Subtitle")
    p2.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p2.add_run("Giới thiệu, công nghệ, nghiệp vụ và hướng dẫn sử dụng")
    set_run_font(run, size=14, color=MUTED)
    doc.add_paragraph()
    p3 = doc.add_paragraph()
    p3.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p3.add_run("Nền tảng quản lý tài sản và đầu tư cá nhân")
    set_run_font(r, size=16, bold=True, color=NAVY)
    p4 = doc.add_paragraph()
    p4.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p4.add_run("Bản demo full-stack đang vận hành trên Railway")
    set_run_font(r, size=11.5, color=MUTED)
    doc.add_paragraph()
    status = doc.add_paragraph()
    status.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = status.add_run("Phiên bản hồ sơ 1.0  |  Ngày cập nhật 20 tháng 9 năm 2026")
    set_run_font(r, size=10, color=MUTED)
    doc.add_paragraph()
    p5 = doc.add_paragraph()
    p5.alignment = WD_ALIGN_PARAGRAPH.CENTER
    add_hyperlink(p5, "https://mofi-platform-demo-production.up.railway.app", "https://mofi-platform-demo-production.up.railway.app")
    doc.add_paragraph()
    note = doc.add_paragraph()
    note.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = note.add_run("Tài liệu dành cho người duyệt dự án và người cần hiểu cách vận hành hệ thống.")
    set_run_font(r, size=10.5, color=TEXT)
    doc.add_page_break()


def add_contents(doc):
    add_section_heading(doc, "Mục lục", 1)
    items = [
        "Tóm tắt điều hành",
        "1 Bức tranh dự án",
        "2 Trạng thái hiện tại và phạm vi",
        "3 Công nghệ và kiến trúc",
        "4 Quy trình nghiệp vụ",
        "5 Hướng dẫn sử dụng từng bước",
        "6 Thuật toán và logic quan trọng",
        "7 Dữ liệu, API và phân quyền",
        "8 Kiểm thử và tiêu chí nghiệm thu",
        "9 Giới hạn hiện tại và lộ trình",
        "Phụ lục Kịch bản trình bày ngắn",
    ]
    for index, item in enumerate(items):
        p = doc.add_paragraph()
        p.paragraph_format.left_indent = Inches(0.18)
        p.paragraph_format.space_after = Pt(3)
        r = p.add_run(item)
        set_run_font(r, size=11, color=TEXT, bold=index == 0)
    add_body(doc, "Cách đọc tài liệu: phần đầu dành cho quyết định và phạm vi; phần giữa mô tả cách người dùng thao tác; phần cuối giải thích dữ liệu, công thức và các điều kiện cần nếu phát triển thành sản phẩm thật.")


def build_document():
    doc = new_doc()
    add_title_page(doc)
    add_contents(doc)
    doc.add_page_break()

    add_section_heading(doc, "Tóm tắt điều hành", 1)
    add_body(doc, "MOFI hiện là một bản demo full-stack có thể mở bằng trình duyệt và sử dụng các luồng chính từ đăng nhập, dashboard, quản lý tài sản, bảng giá chứng khoán mô phỏng, đặt lệnh paper trading, mục tiêu, cảnh báo, học tập và khu vực quản trị. Frontend và backend đã nằm trong cùng một ứng dụng Laravel kết hợp React/Inertia; dữ liệu được lưu trong PostgreSQL trên Supabase.")
    add_body(doc, "Kết luận vận hành: web đang chạy bình thường trong phạm vi demo. Bảng Market hiện hiển thị mã, bid/ask, giá khớp cuối, tham chiếu, trần/sàn, khối lượng, ngày giờ mô phỏng và tự đồng bộ theo tick; `/transactions` chỉ nhận nạp/rút, còn mua/bán đi qua Market để giữ đúng quy trình khớp lệnh. Tuy nhiên, không nên mô tả MOFI hiện tại là hệ thống giao dịch tài chính production. Tiền, giá và lệnh trong workspace là dữ liệu mô phỏng; chưa có kết nối broker, thanh toán, KYC, tiền thật hoặc cơ chế giám sát production đầy đủ.")
    add_rich_paragraph(doc, "Đường dẫn đang chạy: ", "", None)
    p = doc.paragraphs[-1]
    add_hyperlink(p, "https://mofi-platform-demo-production.up.railway.app", "https://mofi-platform-demo-production.up.railway.app")
    add_table(doc, ["Hạng mục", "Kết luận hiện tại", "Ghi chú"], [
        ("Mở web", "Đạt", "Railway service online; /up, / và /login đã trả HTTP 200."),
        ("Frontend", "Đạt trong demo", "React 19, TypeScript, Inertia, các route public và workspace."),
        ("Backend", "Đạt trong demo", "Laravel route, session auth, validation, policies, services và API."),
        ("Database", "Đạt trong demo", "PostgreSQL trên Supabase; migration và seed đã chạy qua pooler IPv4."),
        ("Giao dịch", "Mô phỏng", "Nạp/rút ở Transactions; mua/bán ở Market với paper order, reservation, execution và P/L; không gửi lệnh ra broker."),
        ("AI và chiến lược", "Mô phỏng", "Copilot rule-based; strategy và simulation dùng dữ liệu/kịch bản có sẵn."),
        ("Production tài chính", "Chưa đạt", "Cần broker, nguồn giá được cấp phép, KYC, thanh toán, monitoring, backup và đối soát."),
    ], widths=[0.22, 0.22, 0.56], font_size=9.2)

    add_section_heading(doc, "1 Bức tranh dự án", 1)
    add_section_heading(doc, "Mục tiêu", 2)
    add_body(doc, "MOFI giúp người dùng nhìn được bức tranh tài chính cá nhân trong một workspace thống nhất: tiền mặt, danh mục đầu tư, tài sản thủ công, mục tiêu, thị trường và các việc cần làm. Bản demo được xây để chứng minh rằng các thao tác chính đi qua backend, được kiểm tra quyền và lưu xuống database, thay vì chỉ là giao diện tĩnh.")
    add_section_heading(doc, "Giá trị đối với người dùng", 2)
    add_bullet(doc, "Theo dõi tổng tài sản và phân bổ từ cùng một nguồn dữ liệu.")
    add_bullet(doc, "Ghi nhận nạp/rút tiền mô phỏng tại Transactions; giao dịch mua/bán được tạo từ lệnh Market và chỉ ghi vào ledger khi có execution.")
    add_bullet(doc, "Quan sát giá mẫu, tạo danh sách theo dõi và cảnh báo theo ngưỡng.")
    add_bullet(doc, "Tách rõ phần hoạt động thật trong demo, phần mô phỏng và phần giới thiệu cho tương lai.")
    add_section_heading(doc, "Các nhóm người dùng", 2)
    add_table(doc, ["Vai trò", "Nhu cầu", "Khu vực chính"], [
        ("Khách", "Hiểu sản phẩm và xem thông tin công khai.", "Landing, Products, About, Pricing, Policies"),
        ("Thành viên", "Quản lý dữ liệu cá nhân và mô phỏng đầu tư.", "Dashboard, Assets, Portfolio, Market, Goals, Alerts"),
        ("Quản trị viên", "Kiểm tra dữ liệu seed và sức khỏe demo.", "Admin, Admin health"),
    ], widths=[0.2, 0.42, 0.38])

    add_section_heading(doc, "2 Trạng thái hiện tại và phạm vi", 1)
    add_section_heading(doc, "Những phần đã có thể sử dụng", 2)
    add_bullet(doc, "Landing page, trang giới thiệu và trang chính sách có thể điều hướng không bị dead link.")
    add_bullet(doc, "Đăng ký, đăng nhập, đăng xuất và cập nhật thông tin tài khoản; mật khẩu được hash ở backend.")
    add_bullet(doc, "Dashboard đọc số liệu từ portfolio, transactions, market prices và manual assets.")
    add_bullet(doc, "Assets cho phép thêm, sửa, xóa tài sản thủ công; Goals, Tasks, Watchlist và Community có lưu dữ liệu theo user.")
    add_bullet(doc, "Transactions có bộ lọc và xuất CSV; lịch sử vẫn hiển thị BUY/SELL đã khớp để đối soát, nhưng form tạo mới chỉ có DEPOSIT/WITHDRAW. Paper Trading có reservation, execution, trạng thái OPEN, PARTIALLY_FILLED, FILLED và CANCELLED.")
    add_bullet(doc, "Market dùng fixture giá mô phỏng cho phần danh mục; panel crypto công khai có thể tham khảo nguồn live được cho phép, không dùng để tính danh mục demo.")
    add_bullet(doc, "Alerts chỉ phát notification khi điều kiện chuyển từ false sang true; Copilot trả lời theo luật xác định và không tự nhận là LLM.")
    add_section_heading(doc, "Những phần chưa nên hiểu là đã hoàn tất production", 2)
    add_bullet(doc, "Chưa kết nối tài khoản chứng khoán hoặc broker thật; không có lệnh mua bán tiền thật.")
    add_bullet(doc, "Chưa có quy trình thanh toán, KYC/AML, quản lý khiếu nại, đối soát broker hoặc báo cáo pháp lý.")
    add_bullet(doc, "Nguồn dữ liệu thị trường trong workspace chính là dữ liệu mô phỏng; chưa có cam kết SLA, bản quyền dữ liệu hoặc độ trễ production.")
    add_bullet(doc, "Chưa có LLM trả phí cho Copilot, backtest thật, scheduler cảnh báo email/SMS hoặc hệ thống monitoring production hoàn chỉnh.")
    add_body(doc, "Trong tài liệu này, từ hoạt động có nghĩa là tính năng đã có xử lý và lưu dữ liệu trong môi trường demo. Từ mô phỏng có nghĩa là kết quả dùng fixture hoặc kịch bản giả, không tạo nghĩa vụ tài chính ngoài hệ thống.")

    doc.add_page_break()
    add_section_heading(doc, "3 Công nghệ và kiến trúc", 1)
    add_table(doc, ["Lớp", "Công nghệ", "Vai trò"], [
        ("Backend", "PHP 8.4, Laravel 13", "Route, session, CSRF, validation, policy, controller và service."),
        ("Frontend", "React 19, TypeScript, Inertia.js", "Workspace, form, trạng thái UI và điều hướng trong cùng repository."),
        ("Build và biểu đồ", "Vite, Recharts, Tailwind CSS", "Đóng gói frontend, biểu đồ phân bổ/lịch sử và hệ thống style."),
        ("Database", "PostgreSQL trên Supabase", "Users, portfolios, transactions, instruments, market prices và bảng workspace."),
        ("Deploy", "Railway, Railpack, FrankenPHP/Railway runtime", "Build frontend, migrate/seed trước deploy, chạy Laravel và health check."),
        ("Quản lý mã nguồn", "Composer, npm, GitHub", "Cài dependency, build, kiểm thử và lưu lịch sử thay đổi."),
    ], widths=[0.17, 0.32, 0.51])
    add_picture(doc, make_architecture_diagram(), caption="Sơ đồ kiến trúc logic của bản demo MOFI.")
    add_section_heading(doc, "Nguyên tắc kiến trúc", 2)
    add_bullet(doc, "Browser gửi intent; Laravel là nơi kiểm tra owner, quyền, validation và quyết định ghi dữ liệu.")
    add_bullet(doc, "React không tính giá vốn hoặc số dư tài chính bằng JavaScript float; số tiền và giá được xử lý bằng decimal ở server.")
    add_bullet(doc, "Laravel là lớp duy nhất dùng credential database; browser không truy cập trực tiếp các bảng nghiệp vụ trên Supabase.")
    add_bullet(doc, "Các service chính tách riêng việc ghi giao dịch, tính summary, đánh giá cảnh báo, replay market và paper trading để có thể kiểm thử độc lập.")
    add_section_heading(doc, "Triển khai hiện tại", 2)
    add_body(doc, "Railway chạy toàn bộ ứng dụng Laravel và frontend đã build. Lệnh pre-deploy chạy migration cùng các seeder demo và admin theo cấu hình môi trường; endpoint /up được dùng làm health check. PostgreSQL chạy trên Supabase qua Session Pooler IPv4 để phù hợp với runtime Railway.")

    add_section_heading(doc, "4 Quy trình nghiệp vụ", 1)
    add_picture(doc, make_business_flow_diagram(), caption="Luồng từ ý định người dùng đến số liệu hiển thị trên dashboard.")
    add_section_heading(doc, "Quy trình ghi nhận giao dịch", 2)
    add_number(doc, "Người dùng chọn một trong hai luồng: nạp/rút tiền tại Transactions, hoặc đặt lệnh mua/bán tại Market.")
    add_number(doc, "Với nạp/rút, backend chỉ nhận loại DEPOSIT/WITHDRAW và tự thiết lập ngày, dòng tiền và các trường hệ thống.")
    add_number(doc, "Với paper order, backend kiểm tra session, ownership, mã VN/VND được phép giao dịch, số tiền hoặc số lượng khả dụng và điều kiện market/limit.")
    add_number(doc, "Portfolio được khóa theo dòng trong database; lệnh BUY giữ cash, lệnh SELL giữ quantity. Khi quote đạt điều kiện, hệ thống tạo execution theo từng mức bid/ask.")
    add_number(doc, "Mỗi execution tạo dòng BUY/SELL immutable trong ledger; lỗi ở bất kỳ bước nào thì rollback toàn bộ transaction database.")
    add_number(doc, "PortfolioSummary tính lại cash, cost basis, securities value và P/L; dashboard và lịch sử nhận kết quả mới sau khi tải lại dữ liệu.")
    add_section_heading(doc, "Quy trình thị trường và cảnh báo", 2)
    add_body(doc, "Người dùng tìm mã trong catalog, thêm vào watchlist rồi tạo điều kiện GTE hoặc LTE. Khi bấm kiểm tra dữ liệu mô phỏng, hệ thống lấy quote đúng ngày demo, khóa rule và đánh giá điều kiện. Chỉ chuyển trạng thái false sang true mới tạo notification; nếu điều kiện vẫn true thì không tạo trùng. Khi điều kiện trở lại false, rule có thể kích hoạt lại ở lần true tiếp theo.")
    add_section_heading(doc, "Quy trình mục tiêu và công việc", 2)
    add_body(doc, "Goal lưu target, số tiền đã dành và deadline để hiển thị tiến độ. Trong bản demo, số tiền đã dành chỉ là số liệu kế hoạch, không reserve cash và không cộng thêm vào total assets. Task lưu title, deadline và trạng thái complete; bộ đếm dashboard lấy từ danh sách task chưa hoàn thành.")
    add_section_heading(doc, "Quy trình Copilot và mô phỏng", 2)
    add_body(doc, "Copilot nhận các câu hỏi đã được hỗ trợ như tóm tắt danh mục, tỷ trọng và tiến độ mục tiêu, sau đó đọc dữ liệu hiện tại rồi trả lời bằng phép tính deterministic. Simulation áp dụng cú sốc giá lên quantity hiện có để ước tính kết quả; không sửa transaction, cash, manual asset hoặc portfolio trong database.")

    add_section_heading(doc, "5 Hướng dẫn sử dụng từng bước", 1)
    add_section_heading(doc, "Bước 1 Mở hệ thống và đăng nhập", 2)
    add_step_list(doc, [
        "Mở trình duyệt và truy cập đường dẫn MOFI được cung cấp.",
        "Ở landing page, chọn Đăng nhập hoặc Đăng ký. Tài khoản demo do người quản lý môi trường cung cấp; thông tin mật khẩu không ghi trong tài liệu này.",
        "Sau khi đăng nhập, hệ thống chuyển đến /dashboard. Tài khoản mới có portfolio rỗng; tài khoản demo có fixture đã seed.",
    ])
    add_section_heading(doc, "Bước 2 Đọc dashboard", 2)
    add_bullet(doc, "Kiểm tra tổng tài sản, cash, securities value và total P/L.")
    add_bullet(doc, "Đọc ngày mô phỏng và trạng thái dữ liệu; không nhầm ngày mô phỏng với realtime.")
    add_bullet(doc, "Xem allocation, portfolio history, market, goals, watchlist, alerts và tasks.")
    add_bullet(doc, "Nếu thiếu quote, giao diện phải hiển thị trạng thái chưa đủ định giá thay vì tự đổi thành 0.")
    add_section_heading(doc, "Bước 3 Quản lý tiền và tài sản", 2)
    add_step_list(doc, [
        "Mở Tiền và tài sản để xem cash, holdings và manual assets.",
        "Chọn thêm tài sản thủ công, nhập tên, loại, giá trị VND và ngày định giá.",
        "Sửa hoặc xóa bản ghi khi cần; việc xóa tài sản không tự sinh một khoản tiền nạp vào portfolio.",
    ])
    add_section_heading(doc, "Bước 4 Ghi nhận deposit và giao dịch mô phỏng", 2)
    add_step_list(doc, [
        "Mở Nạp / rút tiền. Chọn Nạp tiền ảo hoặc Rút tiền ảo; màn hình này không tạo BUY/SELL.",
        "Nhập số tiền VND. Hệ thống hiển thị preview, nhưng server mới là nơi kiểm tra số dư và quyết định cash delta.",
        "Bấm xác nhận một lần. Nếu kết nối bị gián đoạn, retry bằng cùng request key để không tạo dòng tiền trùng.",
        "Mở Market để mua/bán cổ phiếu; mở lại Transactions để xem lịch sử nạp/rút và các execution BUY/SELL đã được ghi.",
    ])
    add_body(doc, "Kịch bản trình bày nhanh: nạp tiền mô phỏng, vào Market đặt một lệnh nhỏ, chờ tick khớp, reload rồi đối chiếu cash, quantity, basis, P/L và lịch sử. Thử SELL vượt quantity để minh họa validation.")
    add_section_heading(doc, "Bước 5 Paper trading trên Market", 2)
    add_step_list(doc, [
        "Mở Thị trường và chọn mã MOFI. Bảng giá hiển thị sổ lệnh ba mức, giá khớp gần nhất, tham chiếu, trần/sàn, khối lượng và ngày giờ mô phỏng.",
        "Chọn Mua hoặc Bán, loại MP (market) hoặc LO (limit), rồi nhập khối lượng. Lệnh mua ăn Ask 1 đến Ask 3; lệnh bán ăn Bid 1 đến Bid 3.",
        "Bản demo quy đổi 5 giây thật thành 5 phút mô phỏng; trình duyệt polling bảng giá mỗi 2 giây, server chỉ tiến một tick khi đủ 5 giây.",
        "Theo dõi trạng thái OPEN, PARTIALLY_FILLED, FILLED hoặc CANCELLED. Market order có thể khớp nhiều mức; limit chỉ khớp khi giá đối ứng đạt điều kiện.",
        "Hủy lệnh OPEN hoặc PARTIALLY_FILLED để giải phóng phần cash/quantity còn giữ; thao tác hủy lặp lại không tạo thay đổi phụ.",
    ])
    add_section_heading(doc, "Bước 6 Watchlist và alerts", 2)
    add_step_list(doc, [
        "Tìm mã theo symbol hoặc tên rồi bấm Theo dõi.",
        "Tạo alert với điều kiện giá lớn hơn hoặc bằng, hoặc nhỏ hơn hoặc bằng một ngưỡng dương.",
        "Bấm Kiểm tra dữ liệu mô phỏng; mở Notifications để xem notification mới và đánh dấu đã đọc.",
    ])
    add_section_heading(doc, "Bước 7 Goals và Tasks", 2)
    add_step_list(doc, [
        "Tạo goal với tên, target lớn hơn 0, số tiền đã dành và deadline nếu có.",
        "Cập nhật saved amount để xem progress; progress bar dừng ở 100% nhưng nhãn có thể lớn hơn 100%.",
        "Tạo task cá nhân, đánh dấu complete và kiểm tra bộ đếm trên dashboard.",
    ])
    add_section_heading(doc, "Bước 8 Copilot, Strategies, Simulation và Learn", 2)
    add_bullet(doc, "Copilot: chọn câu hỏi gợi ý để xem summary có căn cứ; câu hỏi ngoài nhóm hỗ trợ sẽ báo giới hạn.")
    add_bullet(doc, "Strategies: xem chiến lược mẫu và tỷ trọng minh họa; không gọi đây là backtest thật.")
    add_bullet(doc, "Simulation: chọn shock giá, xem kết quả trước và sau; reload để xác nhận portfolio không bị thay đổi.")
    add_bullet(doc, "Learn: mở bài học, đánh dấu hoàn thành và kiểm tra tiến độ sau reload.")
    add_section_heading(doc, "Bước 9 Admin", 2)
    add_step_list(doc, [
        "Đăng nhập bằng tài khoản có role admin rồi mở /admin; user thường không được phép truy cập.",
        "Xem số lượng users, instruments, market prices và health checks; admin demo hiện chủ yếu là read-only.",
        "Không sử dụng tài khoản admin để trình diễn giao dịch thành viên.",
    ])

    doc.add_page_break()
    add_section_heading(doc, "6 Thuật toán và logic quan trọng", 1)
    add_section_heading(doc, "Tính cash và giá trị danh mục", 2)
    add_table(doc, ["Chỉ số", "Công thức", "Ý nghĩa"], [
        ("Cash", "cash = sum(cash_delta)", "Tổng các dòng tiền đã ghi; deposit không phải lợi nhuận."),
        ("Securities value", "S = sum(quantity_i x quote_i)", "Định giá theo quote hợp lệ gần nhất trong ngày mô phỏng."),
        ("Portfolio value", "W = cash + S", "Giá trị danh mục đầu tư, chưa cộng manual assets."),
        ("Total assets", "A = W + manual_assets", "Tổng tài sản hiển thị trên dashboard."),
        ("Total P/L", "P = realized + unrealized + net_dividend", "Không tính deposit, withdraw hoặc thay đổi manual asset là P/L."),
    ], widths=[0.22, 0.4, 0.38], font_size=9.1)
    add_body(doc, "Nếu một vị thế không có quote hợp lệ, hệ thống trả trạng thái partial hoặc null cho phần tổng định giá liên quan. Quy tắc này ngăn lỗi phổ biến là biến một dữ liệu thiếu thành giá trị 0 rồi làm sai tổng tài sản.")
    add_section_heading(doc, "Giá vốn và lãi lỗ mua bán", 2)
    add_table(doc, ["Tình huống", "Cập nhật", "Điểm cần nhớ"], [
        ("BUY", "Q_new = Q_old + q; B_new = B_old + gross + fee + tax", "Phí và thuế mua đi vào cost basis."),
        ("SELL", "basis_sold = round8(B_old x q / Q_old)", "Nếu bán hết, dùng toàn bộ basis còn lại để tránh số dư làm tròn."),
        ("SELL P/L", "realized = gross - fee - tax - basis_sold", "Cash nhận ròng khác với gross."),
        ("Sau SELL", "Q_new = Q_old - q; B_new = B_old - basis_sold", "Không tạo thêm một cash entry riêng cho phần P/L."),
        ("DIVIDEND", "net_income = gross - fee - tax", "Tăng cash và net dividend, không tăng cost basis."),
    ], widths=[0.18, 0.48, 0.34], font_size=9.1)
    add_section_heading(doc, "Ví dụ tính nhanh", 2)
    add_body(doc, "Mua 100 cổ phiếu ở 100.000 VND, phí 1.000 VND và thuế 0: giá vốn là 10.001.000 VND. Bán 40 cổ phiếu ở 110.000 VND, phí 2.000 VND và thuế 1.000 VND: tiền bán ròng là 4.397.000 VND; basis phần bán là 4.000.400 VND; realized P/L là 396.600 VND. Phần basis còn lại là 6.000.600 VND.")
    add_section_heading(doc, "Idempotency và an toàn ghi dữ liệu", 2)
    add_bullet(doc, "Form tạo request_key; database giữ unique key theo portfolio để retry không nhân đôi giao dịch.")
    add_bullet(doc, "Cùng request_key và cùng payload trả lại receipt cũ; cùng key nhưng payload khác trả lỗi xung đột 409.")
    add_bullet(doc, "Portfolio row được khóa bằng lockForUpdate trước khi kiểm tra cash hoặc quantity, giúp chống hai request đồng thời dùng cùng số dư.")
    add_bullet(doc, "Lỗi validation trả 422, truy cập bản ghi của user khác trả 404 và lỗi bên trong rollback toàn bộ transaction.")
    add_section_heading(doc, "Reservation và execution của paper trading", 2)
    add_body(doc, "Lệnh BUY giữ cash khả dụng theo giá giới hạn hoặc mức trần dành cho market order; lệnh SELL giữ quantity khả dụng. Khi quote mô phỏng đạt điều kiện market hoặc limit, hệ thống đi qua Ask 1-3 hoặc Bid 1-3 theo thứ tự giá, tạo một execution cho mỗi phần khớp, cập nhật filled_quantity và điều chỉnh reservation còn lại. Lệnh limit chưa đạt vẫn OPEN; lệnh thiếu thanh khoản chuyển PARTIALLY_FILLED; phần còn lại có thể hủy.")
    add_section_heading(doc, "Nhịp thời gian mô phỏng", 2)
    add_body(doc, "Một phiên demo gồm các tick từ 09:00 đến 11:25 và 13:00 đến 14:55 theo ngày mô phỏng, tổng cộng 54 tick với cấu hình hiện tại. Mỗi tick tăng 5 phút mô phỏng sau 5 giây thực. Khi hết tick, server chuyển phiên sang CLOSED và khóa nút đặt lệnh, tương tự trạng thái đóng cửa thị trường; việc mở phiên ngày khác là thao tác vận hành demo, không tự sửa lịch sử đã ghi.")
    add_section_heading(doc, "Simulation không làm bẩn dữ liệu", 2)
    add_body(doc, "Kết quả shock giá được tính theo quantity hiện tại và giá sau shock: simulated_value = quantity x quote x (1 - rate). Cash, manual assets và transaction history giữ nguyên. Đây là phép what-if read-only, không phải một giao dịch mới.")
    add_section_heading(doc, "Quyền riêng tư dữ liệu", 2)
    add_bullet(doc, "Mọi truy vấn private đều lọc theo user_id hoặc policy owner.")
    add_bullet(doc, "Role admin không nhận từ client; register/settings không thể tự nâng quyền.")
    add_bullet(doc, "Secret, password và credential database chỉ nằm trong biến môi trường, không đưa vào tài liệu, frontend hoặc Git.")

    doc.add_page_break()
    add_section_heading(doc, "7 Dữ liệu API và phân quyền", 1)
    add_picture(doc, make_erd_diagram(), caption="ERD rút gọn của các nhóm dữ liệu chính trong bản demo.")
    add_section_heading(doc, "Các nhóm bảng chính", 2)
    add_table(doc, ["Nhóm", "Dữ liệu", "Quan hệ và mục đích"], [
        ("Identity", "users, sessions", "Đăng nhập, role và phiên làm việc."),
        ("Portfolio", "portfolios, transactions", "Sổ tiền và giao dịch immutable theo owner."),
        ("Market", "instruments, market_prices", "Catalog mã và giá demo theo ngày mô phỏng."),
        ("Paper trading", "orders, order_reservations, executions", "Lệnh mô phỏng, giữ chỗ và khớp lệnh."),
        ("Planning", "goals, tasks, manual_assets", "Mục tiêu, việc và tài sản ngoài danh mục."),
        ("Engagement", "watchlist_items, alert_rules, notifications", "Theo dõi mã, điều kiện giá và thông báo."),
        ("Learning", "learning_progress, community_posts, copilot_questions", "Tiến độ học, cộng đồng và lịch sử Copilot."),
    ], widths=[0.18, 0.37, 0.45])
    add_section_heading(doc, "Route chính", 2)
    add_table(doc, ["Khu vực", "Route tiêu biểu", "Quyền"], [
        ("Public", "/, /products, /about, /pricing, /policies", "Khách truy cập."),
        ("Auth", "/login, /register, /settings", "Guest hoặc user tùy route."),
        ("Workspace", "/dashboard, /assets, /portfolio, /transactions, /goals, /market", "Auth; dữ liệu theo user."),
        ("Workspace nâng cao", "/watchlist, /alerts, /notifications, /tasks, /copilot, /strategies, /simulation, /learn, /community", "Auth; mock/active theo module."),
        ("API", "/api/v1/portfolios/.../summary, transactions, orders, market/live", "Session, throttle và owner scope."),
        ("Admin", "/admin, /admin/health", "Auth và role admin."),
    ], widths=[0.18, 0.57, 0.25], font_size=8.9)
    add_section_heading(doc, "Luồng API giao dịch", 2)
    add_code_line(doc, "GET  /api/v1/portfolios/{portfolio}/summary")
    add_code_line(doc, "GET  /api/v1/portfolios/{portfolio}/transactions")
    add_code_line(doc, "POST /api/v1/portfolios/{portfolio}/transactions  # DEPOSIT/WITHDRAW")
    add_code_line(doc, "POST /api/v1/portfolios/{portfolio}/orders       # BUY/SELL paper order")
    add_code_line(doc, "POST /api/v1/portfolios/{portfolio}/orders/advance")
    add_code_line(doc, "POST /api/v1/orders/{order}/cancel")
    add_body(doc, "API trả decimal dưới dạng chuỗi trong JSON để không làm mất độ chính xác khi đi qua JavaScript. Các endpoint private đều cần session, CSRF phù hợp với thao tác ghi và kiểm tra owner.")

    add_section_heading(doc, "8 Kiểm thử và tiêu chí nghiệm thu", 1)
    add_section_heading(doc, "Bằng chứng kỹ thuật hiện tại", 2)
    add_table(doc, ["Hạng mục", "Kết quả ghi nhận", "Ý nghĩa"], [
        ("PHPUnit với SQLite", "66 tests; 65 passed; 1 skipped; 935 assertions", "Luồng auth, ownership, ledger, orders, quote board và workspace đã có kiểm thử; 1 test được skip theo điều kiện môi trường."),
        ("TypeScript", "Đạt", "Kiểm tra kiểu frontend không lỗi trong lần nghiệm thu."),
        ("Vite production build", "Đạt", "Frontend build được cho deploy."),
        ("PostgreSQL concurrency", "Có test riêng; không tính vào SQLite run", "Cần chạy khi bật cờ và trỏ vào database kiểm thử PostgreSQL riêng; không chạy destructive test trên DB demo dùng chung."),
        ("Browser QA live", "Đạt trong phạm vi smoke test", "Đã kiểm tra URL public, login session, bảng giá mô phỏng, trạng thái tick/đóng cửa và form Transactions chỉ nạp/rút."),
        ("Deployment", "Đang online", "Railway health check /up và trang chính trả HTTP 200."),
    ], widths=[0.25, 0.33, 0.42], font_size=9.0)
    add_section_heading(doc, "Acceptance checklist đề nghị khi trình diễn", 2)
    for item in [
        "Mở landing và chỉ ra nhãn dữ liệu mô phỏng.",
        "Đăng nhập demo, kiểm tra dashboard và ngày mô phỏng.",
        "Deposit -> vào Market đặt paper order -> chờ tick -> reload -> đối chiếu cash, quantity, basis và P/L.",
        "Mở Transactions xác nhận form chỉ có DEPOSIT/WITHDRAW; lịch sử vẫn có execution BUY/SELL để đối soát.",
        "Thử limit không đạt, market partial fill và hủy phần còn lại để minh họa reservation.",
        "Tạo goal, watchlist, alert và task; kiểm tra dữ liệu còn sau reload.",
        "Mở Simulation để cho thấy số liệu what-if không làm thay đổi portfolio.",
        "Mở admin bằng tài khoản admin riêng và cho thấy user thường không có quyền.",
    ]:
        add_bullet(doc, item)
    add_body(doc, "Khi chia sẻ link với sếp, nên nói chính xác: đây là demo full-stack có backend và database thật trong môi trường demo, còn nghiệp vụ tài chính thật và tích hợp broker vẫn là phạm vi phát triển tiếp theo.")

    add_section_heading(doc, "9 Giới hạn hiện tại và lộ trình", 1)
    add_table(doc, ["Giai đoạn", "Việc cần làm", "Điều kiện hoàn thành"], [
        ("Sau demo", "Tách staging và production, backup/restore, monitoring, queue worker và scheduler.", "Có runbook vận hành, alert lỗi và rollback kiểm chứng."),
        ("Provider", "Chọn nguồn giá được cấp phép, chuẩn hóa freshness, retry và rate limit.", "Có provider contract, import run và audit dữ liệu."),
        ("Broker", "Tích hợp broker sandbox trước, sau đó mới xem xét giao dịch thật.", "Có credential vault, đối soát, idempotency và xử lý lỗi khớp lệnh."),
        ("Compliance", "KYC/AML, điều khoản, quyền riêng tư, retention, export/delete và quy trình hỗ trợ.", "Được review nghiệp vụ và pháp lý trước khi nhận dữ liệu thật."),
        ("Copilot", "Nếu cần, thêm provider AI read-only có timeout, giới hạn chi phí và guardrail.", "Có log source/metric, không sinh khuyến nghị mua bán thiếu căn cứ."),
        ("Analytics", "Benchmark, hiệu suất theo kỳ, drill-down cost basis và báo cáo.", "Metric contract rõ, test fixture và xử lý missing quote."),
    ], widths=[0.19, 0.48, 0.33], font_size=8.9)
    add_body(doc, "Ưu tiên hợp lý là giữ paper trading và dữ liệu mô phỏng ổn định, sau đó xây staging/provider/broker theo từng cổng kiểm soát. Không nên nối broker hoặc tiền thật chỉ để làm giao diện giống sản phẩm thương mại.")

    doc.add_page_break()
    add_section_heading(doc, "Phụ lục Kịch bản trình bày ngắn", 1)
    add_body(doc, "Thời lượng gợi ý khoảng bảy phút. Mục tiêu là cho thấy sản phẩm có câu chuyện rõ, dữ liệu đi qua backend và giới hạn demo được nói minh bạch.")
    add_table(doc, ["Thời lượng", "Thao tác", "Thông điệp cần nói"], [
        ("0:00 - 1:00", "Landing và giới thiệu", "MOFI là workspace quản lý tài sản; dữ liệu workspace là mô phỏng."),
        ("1:00 - 2:00", "Đăng nhập và dashboard", "Các KPI, chart và table lấy từ cùng summary backend."),
        ("2:00 - 3:30", "Nạp tiền, đặt lệnh Market và reload", "Ghi dữ liệu vào DB demo; chờ tick khớp rồi reload để đối soát."),
        ("3:30 - 4:30", "SELL vượt quantity", "Validation và ownership bảo vệ sổ giao dịch."),
        ("4:30 - 5:30", "Goal, watchlist, alert, task", "Các module workspace có trạng thái và lưu theo user."),
        ("5:30 - 6:30", "Copilot và simulation", "Rule-based và what-if; không giả là LLM hay giao dịch thật."),
        ("6:30 - 7:00", "Admin, architecture và roadmap", "Đã có nền tảng kỹ thuật; broker, compliance và production ops là bước sau."),
    ], widths=[0.18, 0.35, 0.47], font_size=9.0)
    add_section_heading(doc, "Thông điệp kết luận", 2)
    add_body(doc, "MOFI đã vượt qua mức mockup giao diện: frontend, backend, database, authentication, nghiệp vụ mô phỏng và deployment đã nối thành một hệ thống có thể thao tác. Giá trị tiếp theo không phải là thêm nhãn tính năng, mà là xây các cổng production còn thiếu: dữ liệu được cấp phép, broker sandbox, an toàn vận hành, compliance và quan sát hệ thống.")

    # Keep the final paragraph readable and avoid a trailing empty page.
    for paragraph in doc.paragraphs:
        remove_paragraph_border(paragraph)
    doc.save(DOCX_PATH)
    print("DOCX_CREATED")


if __name__ == "__main__":
    build_document()
