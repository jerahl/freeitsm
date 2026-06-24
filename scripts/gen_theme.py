import re, subprocess, os

# ---- Neutral colour maps (semantic tints are deliberately excluded) ----
BG_PANEL = {'white','#fff','#ffffff','#fefefe','#fdfdfd','#fcfcfc','#fbfbfb','#fafafa','#f9f9f9','#f8f8f8'}
BG_RAISED = {'#f7f7f7','#f6f6f6','#f5f5f5','#f5f7fa','#f4f4f4','#f3f4f6','#f0f0f0','#f0f2f5','#f8f9fa',
             '#eeeeee','#eee','#ececec','#ebebeb','#eaeaea','#ededed','#e9ecef','#fafbfc','#f2f2f2','#f1f1f1'}
TEXT_DARK = {'#000','#000000','#111','#111111','#1a1a1a','#222','#222222','#212121','#262626','#2d2d2d',
             '#333','#333333','#3a3a3a','#444','#444444','#2c3e50','#34495e','#1e293b','#0f172a','#222f3e',
             '#1f2937','#374151','#252525','#2b2b2b','#3b3b3b','#1a202c','#0f0f0f'}
TEXT_MUTED = {'#555','#555555','#666','#666666','#777','#777777','#888','#888888','#999','#999999',
              '#6b7280','#64748b','#6c757d','#9ca3af','#94a3b8','#aaa','#aaaaaa','#a0a0a0','#808080',
              '#757575','#7f8c8d','#5f6368','#71717a','#737373','#8a8a8a','#969696'}
BORDER = {'#ddd','#dddddd','#e0e0e0','#eee','#eeeeee','#ccc','#cccccc','#e5e7eb','#e2e8f0','#dee2e6',
          '#d1d5db','#ebebeb','#eaeaea','#ededed','#d9d9d9','#cfcfcf','#e9ecef','#dcdcdc','#c0c0c0',
          '#bdbdbd','#bbb','#bbbbbb','#cbd5e1','#e5e5e5','#dadada','#d0d0d0','#e6e6e6','#e1e1e1'}

VAR_PANEL='var(--zbx-panel)'; VAR_RAISED='var(--zbx-bg-2)'
VAR_TEXT='var(--zbx-text)'; VAR_MUTED='var(--zbx-muted)'; VAR_BORDER='var(--zbx-border)'

def tokens(value):
    v=value.lower()
    return set(re.findall(r'#[0-9a-f]{3,6}\b', v)) | (set(['white']) if re.search(r'\bwhite\b', v) else set())

def parse_rules(css):
    css=re.sub(r'/\*.*?\*/','',css,flags=re.S)
    out=[]
    def scan(s):
        pos=0
        while True:
            b=s.find('{',pos)
            if b==-1: break
            prelude=s[pos:b].strip()
            depth=1; j=b+1
            while j<len(s) and depth>0:
                c=s[j]
                if c=='{':depth+=1
                elif c=='}':depth-=1
                j+=1
            body=s[b+1:j-1]
            at=prelude.split()[0].lower() if prelude else ''
            if at in ('@keyframes','@-webkit-keyframes','@font-face','@page','@import','@charset'):
                pass
            elif at in ('@media','@supports','@document','@-moz-document','@layer'):
                scan(body)
            elif prelude.startswith('@'):
                pass
            else:
                out.append((prelude, body))
            pos=j
    scan(css)
    return out

# (selector, out-property) -> value   (last write wins = source order)
overrides={}

def emit(sel, prop, val):
    overrides[(sel.strip(), prop)] = val

def valid_selector(sel):
    sel=sel.strip()
    if not sel or len(sel)>180: return False
    if any(c in sel for c in '{}<>?$@!;'): return False
    if '\n' in sel or '\t' in sel: return False
    if sel.endswith('%'): return False   # stray keyframe step
    if 'zbx-' in sel: return False        # Zabbix dashboard is already hand-themed
    return True

def handle(sel, body):
    if not valid_selector(sel):
        return
    for decl in body.split(';'):
        if ':' not in decl: continue
        prop, _, val = decl.partition(':')
        prop=prop.strip().lower(); val=val.strip()
        if not prop or not val or '<' in val: continue
        toks=tokens(val)
        if not toks: continue
        has_img = ('gradient' in val.lower()) or ('url(' in val.lower())
        if prop in ('background','background-color'):
            if has_img: continue
            if toks & BG_PANEL: emit(sel,'background-color',VAR_PANEL)
            elif toks & BG_RAISED: emit(sel,'background-color',VAR_RAISED)
        elif prop=='color':
            if toks & TEXT_DARK: emit(sel,'color',VAR_TEXT)
            elif toks & TEXT_MUTED: emit(sel,'color',VAR_MUTED)
        elif prop in ('border','border-color'):
            if toks & (BORDER|BG_RAISED|BG_PANEL): emit(sel,'border-color',VAR_BORDER)
        elif prop in ('border-top','border-right','border-bottom','border-left'):
            if toks & (BORDER|BG_RAISED|BG_PANEL): emit(sel,prop+'-color',VAR_BORDER)
        elif prop in ('border-top-color','border-right-color','border-bottom-color','border-left-color'):
            if toks & (BORDER|BG_RAISED|BG_PANEL): emit(sel,prop,VAR_BORDER)
        elif prop=='outline':
            if toks & (BORDER|BG_RAISED|BG_PANEL): emit(sel,'outline-color',VAR_BORDER)

# ---- Collect CSS sources ----
css_files=[f for f in subprocess.check_output(['bash','-lc','ls assets/css/*.css']).decode().split()
           if 'zabbix-theme' not in f]
php_files=subprocess.check_output(['grep','-rl','<style>','--include=*.php','.']).decode().split()

for f in css_files:
    for sel,body in parse_rules(open(f,encoding='utf-8',errors='surrogateescape').read()):
        for s in sel.split(','): handle(s, body)

for f in php_files:
    src=open(f,encoding='utf-8',errors='surrogateescape').read()
    for m in re.finditer(r'<style[^>]*>(.*?)</style>', src, flags=re.S|re.I):
        for sel,body in parse_rules(m.group(1)):
            for s in sel.split(','): handle(s, body)

# ---- Group selectors by (property,value) for compact output ----
from collections import defaultdict
groups=defaultdict(list)
for (sel,prop),val in overrides.items():
    groups[(prop,val)].append(sel)

order=[('background-color',VAR_PANEL),('background-color',VAR_RAISED),
       ('color',VAR_TEXT),('color',VAR_MUTED),
       ('border-color',VAR_BORDER)]
def sortkey(k):
    return order.index(k) if k in order else len(order)

lines=["/**",
" * Zabbix dark theme — GENERATED override layer",
" * Auto-generated by scripts/gen_theme (do not hand-edit).",
" * One targeted override per source selector that hardcoded a neutral light",
" * background, dark text, or light border. Semantic tints are left untouched.",
" * Load AFTER zabbix-theme.css.",
" */",""]
total=0
for key in sorted(groups, key=sortkey):
    prop,val=key
    sels=sorted(set(groups[key]))
    total+=len(sels)
    # also emit side-border props that landed outside the 5 canonical groups
    lines.append(",\n".join(sels)+" {")
    lines.append(f"    {prop}: {val} !important;")
    lines.append("}")
    lines.append("")
# any leftover side-specific border props
leftover=defaultdict(list)
for (sel,prop),val in overrides.items():
    if (prop,val) not in groups: pass
# (handled above since groups covers all)

open('assets/css/zabbix-theme-generated.css','w',encoding='utf-8').write("\n".join(lines))
print("rules (selector overrides):", len(overrides))
print("grouped declarations:", sum(len(v) for v in groups.values()))
print("css bytes:", os.path.getsize('assets/css/zabbix-theme-generated.css'))
