#!/usr/bin/env python3
from __future__ import annotations
import argparse, pathlib

REPAIRS = {
"Interact Put Item":"Взаємодіяти / Покласти предмет",
"Items will be lost if checkout is not finished. Are you sure about proceeding to the next day?":"Предмети буде втрачено, якщо оформлення покупки не завершено. Ви впевнені, що хочете перейти до наступного дня?",
"Jump Checkout":"Стрибок / Оформлення покупки",
"There are items in the box. Are you sure about throwing it away?":"У коробці є предмети. Ви впевнені, що хочете їх викинути?",
"The card has high value. Are you sure about throwing it away?":"Картка має високу вартість. Ви впевнені, що хочете її викинути?",
"Canceling will reset the registered players. Are you sure about canceling the tournament?":"Скасування скине зареєстрованих гравців. Ви впевнені, що хочете скасувати турнір?",
}

def main():
    ap=argparse.ArgumentParser()
    ap.add_argument("input", type=pathlib.Path)
    ap.add_argument("output", type=pathlib.Path)
    a=ap.parse_args()
    lines=a.input.read_text(encoding="utf-8").splitlines()
    out=[]; repaired=[]; i=0
    while i < len(lines):
        line=lines[i]
        if "|" in line:
            src=line.split("|",1)[0]
            if src in REPAIRS:
                out.append(src+"|"+REPAIRS[src]); repaired.append(src)
                # The audited corruption is a two-physical-line logical record.
                # Drop the following line only when it is malformed, never a valid mapping.
                if i+1 < len(lines) and lines[i+1].strip() and lines[i+1].count("|") != 1:
                    i += 2; continue
        out.append(line)
        i += 1
    missing=[k for k in REPAIRS if k not in repaired]
    a.output.write_text("\n".join(out)+"\n", encoding="utf-8")
    print(f"repaired={len(repaired)} missing={len(missing)}")
    if missing:
        print("Missing source keys:")
        for k in missing: print(" -",k)
        return 1
    return 0
if __name__ == "__main__": raise SystemExit(main())
