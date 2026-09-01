#!/usr/bin/env python3
"""
Static sanity check for PHP files.

Checks bracket/paren/brace balance, unused `use` imports, and debug leftovers.
It is not a parser and cannot replace one — it exists because this sandbox has
no PHP binary, so `php -l` is unavailable.

The literal stripping is a single left-to-right tokenizer rather than a pair of
regexes, because either order of regex substitution is wrong: stripping comments
first turns the `//` inside 'https://...' into a comment start, and stripping
strings first turns an apostrophe in a comment into a string opener. Both
produce phantom or masked imbalances.
"""
import re
import sys
from pathlib import Path

DEBUG_PATTERNS = ["dd(", "dump(", "var_dump(", "ray(", "console.log("]
TODO_MARKERS = ["TODO", "FIXME", "XXX"]


def strip_literals(src: str) -> str:
    """Blank out comments and string contents, preserving newlines and structure."""
    out = []
    i = 0
    n = len(src)
    while i < n:
        c = src[i]
        nxt = src[i + 1] if i + 1 < n else ""

        if c == "/" and nxt == "*":
            end = src.find("*/", i + 2)
            i = n if end == -1 else end + 2
            continue
        if c == "/" and nxt == "/":
            end = src.find("\n", i + 2)
            i = n if end == -1 else end
            continue
        # `#` opens a comment, but not as `#[` (an attribute) or `#{`
        # (interpolation). Reading `#[Fillable([...])]` as a comment orphans the
        # attribute's closing brackets and reports a false imbalance.
        if c == "#" and nxt not in ("{", "["):
            end = src.find("\n", i + 1)
            i = n if end == -1 else end
            continue
        if c in ("'", '"'):
            quote = c
            i += 1
            while i < n:
                if src[i] == "\\":
                    i += 2
                    continue
                if src[i] == quote:
                    i += 1
                    break
                i += 1
            out.append("''")
            continue

        out.append(c)
        i += 1
    return "".join(out)


def check(path: Path):
    problems = []
    raw = path.read_text(encoding="utf-8", errors="replace")
    code = strip_literals(raw)

    for opener, closer, label in (("(", ")", "()"), ("[", "]", "[]"), ("{", "}", "{}")):
        balance = code.count(opener) - code.count(closer)
        if balance != 0:
            problems.append(f"unbalanced {label}: off by {balance}")

    imported = {}
    for line in raw.splitlines():
        stripped = line.strip()
        if stripped.startswith("use ") and ";" in stripped and "function " not in stripped:
            clause = stripped[4:].split(";")[0].strip()
            # A real import names a namespace or an alias. `use RefreshDatabase;`
            # inside a class body is a trait, and flagging it as an unused import
            # is wrong.
            if "\\" not in clause and " as " not in clause:
                continue
            if " as " in clause:
                alias = clause.split(" as ")[-1].strip()
            else:
                alias = clause.split("\\")[-1].strip()
            if alias and not alias.startswith("{"):
                imported[alias] = stripped

    # Search the *raw* source, not the comment-stripped code: an import referenced
    # only from a docblock (`@return array{data: LengthAwarePaginator}`) is a real
    # import and PHPStan agrees. Only the import statements themselves are
    # removed, so that a trait use like `use RefreshDatabase;` still counts as the
    # usage that justifies its import.
    after_imports = raw
    for statement in imported.values():
        after_imports = after_imports.replace(statement, "", 1)

    for alias, statement in imported.items():
        occurrences = after_imports.count(alias)
        if occurrences == 0:
            problems.append(f"unused import: {alias}")

    for marker in DEBUG_PATTERNS:
        # Word-bounded: a bare substring match reports `ray(` inside
        # `assertIsArray(` and `dd(` inside `added(`.
        if re.search(r"(?<![A-Za-z0-9_\\$>])" + re.escape(marker), code):
            problems.append(f"debug call left in code: {marker}")

    for marker in TODO_MARKERS:
        if re.search(r"(?<![A-Za-z0-9_])" + re.escape(marker) + r"(?![A-Za-z0-9_])", code):
            problems.append(f"marker left in code: {marker}")

    return problems


def main(argv):
    targets = [Path(a) for a in argv[1:]]
    if not targets:
        print("usage: scan.py <file-or-dir> [...]", file=sys.stderr)
        return 2

    files = []
    for target in targets:
        if target.is_dir():
            files.extend(sorted(target.rglob("*.php")))
        elif target.exists():
            files.append(target)
        else:
            print(f"{target}: no such path", file=sys.stderr)

    total = 0
    for path in files:
        for problem in check(path):
            print(f"{path}: {problem}")
            total += 1

    print(f"scanned {len(files)} files")
    print(f"{total} problems")
    return 1 if total else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
