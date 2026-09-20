#!/usr/bin/env bash
# Render every diagram in src/ to png/ (and svg/ if --svg is passed).
#
#   ./render.sh            # PNG
#   ./render.sh --svg      # PNG + SVG
#
# Needs Java 11+ and plantuml.jar (https://plantuml.com/download).
# Set PLANTUML_JAR if the jar is not in one of the default locations.
# Graphviz is NOT required: every diagram declares `!pragma layout smetana`.
set -euo pipefail
cd "$(dirname "$0")"

JAR="${PLANTUML_JAR:-}"
for candidate in "$HOME/.tools/plantuml.jar" /usr/share/plantuml/plantuml.jar /opt/plantuml/plantuml.jar ./plantuml.jar; do
  [[ -z "$JAR" && -f "$candidate" ]] && JAR="$candidate"
done
[[ -f "$JAR" ]] || { echo "plantuml.jar not found — set PLANTUML_JAR" >&2; exit 1; }

JAVA_BIN="${JAVA_BIN:-java}"
if ! command -v "$JAVA_BIN" >/dev/null 2>&1; then
  # Java runtime shipped by the `jdk4py` pip package (used in the Arena sandbox).
  JAVA_BIN="$(python3 -c 'import jdk4py; print(jdk4py.JAVA)' 2>/dev/null || true)"
fi
[[ -x "$JAVA_BIN" ]] || { echo "java not found — set JAVA_BIN" >&2; exit 1; }

run() { "$JAVA_BIN" -Djava.awt.headless=true -DPLANTUML_LIMIT_SIZE=16384 -jar "$JAR" -charset UTF-8 "$@"; }

mkdir -p png
run -tpng -o ../png src/*.puml
if [[ "${1:-}" == "--svg" ]]; then
  mkdir -p svg
  run -tsvg -o ../svg src/*.puml
fi
echo "Rendered $(ls png/*.png | wc -l) diagram(s) into png/"
