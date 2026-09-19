#!/usr/bin/env python3
"""Generate a Postman collection (and local environment) from SYNAPSE's routes.

The collection is *derived*, never hand-maintained: this script reads
``backend/routes/api.php``, follows each route to its controller method,
resolves the FormRequest that validates it and turns the validation rules into
an example JSON body. Re-run it whenever routes change:

    python3 tools/generate_postman_collection.py

Outputs (into ``docs/postman/``):
  * SYNAPSE-API.postman_collection.json
  * SYNAPSE-Local.postman_environment.json
  * SYNAPSE-Mock.postman_environment.json
"""

from __future__ import annotations

import json
import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
BACKEND = ROOT / "backend"
ROUTES = BACKEND / "routes" / "api.php"
OUT_DIR = ROOT / "docs" / "postman"

COLLECTION_NAME = "SYNAPSE API"

# ---------------------------------------------------------------------------
# Route parsing
# ---------------------------------------------------------------------------

VERB_RE = re.compile(
    r"Route::(get|post|put|patch|delete)\(\s*'([^']+)'\s*,\s*\[\s*(\w+)::class\s*,\s*'(\w+)'\s*\]"
)
RESOURCE_RE = re.compile(r"Route::apiResource\(\s*'([^']+)'\s*,\s*(\w+)::class\s*\)")
ONLY_RE = re.compile(r"->only\(\[([^\]]*)\]\)")
NAME_RE = re.compile(r"->name\(\s*'([^']+)'\s*\)")
MIDDLEWARE_RE = re.compile(r"(?:->|::)middleware\(\[([^\]]*)\]+\)")
MIDDLEWARE_STR_RE = re.compile(r"->middleware\(\s*'([^']+)'\s*\)")
PREFIX_RE = re.compile(r"->prefix\(\s*'([^']+)'\s*\)")
GROUP_RE = re.compile(r"->group\(function")
USE_RE = re.compile(r"^use\s+([^;]+);", re.MULTILINE)
IMPORT_RE = re.compile(r"use\s+App\\Http\\Requests\\([\w\\]+?)(?:\s+as\s+(\w+))?;")
RULES_RE = re.compile(r"public function rules\(\): array\s*\{(.*?)\n    \}", re.DOTALL)
RULE_LINE_RE = re.compile(r"'([a-zA-Z0-9_.\*]+)'\s*=>\s*\[([^\]]*)\]")
METHOD_RE = re.compile(
    r"public function\s+(\w+)\s*\((.*?)\)\s*(?::\s*[\w\\<>|]+)?\s*\{", re.DOTALL
)

RESOURCE_ACTIONS = {
    "index": ("GET", ""),
    "store": ("POST", ""),
    "show": ("GET", "/{id}"),
    "update": ("PUT", "/{id}"),
    "destroy": ("DELETE", "/{id}"),
}


@dataclass
class Group:
    prefix: str = ""
    middleware: list[str] = field(default_factory=list)


@dataclass
class Route:
    method: str
    path: str
    controller: str
    action: str
    name: str = ""
    middleware: list[str] = field(default_factory=list)
    group_prefix: str = ""
    group_middleware: list[str] = field(default_factory=list)
    from_resource: bool = False  # declared with apiResource() -> real REST semantics

    @property
    def full_path(self) -> str:
        segments = [seg for seg in (self.group_prefix, self.path.lstrip("/")) if seg]
        joined = "/".join(segments)
        return ("/" + joined).replace("//", "/").rstrip("/") or "/"

    @property
    def folder(self) -> str:
        mw = " ".join(self.group_middleware)
        if "role:super_admin" in mw:
            return "05 · Super Admin (platform)"
        if "role:admin" in mw:
            return "04 · School Administrator"
        if "role:teacher" in mw:
            return "03 · Teacher"
        if "role:student" in mw:
            return "02 · Student"
        if "auth:sanctum" in mw:
            return "01 · Auth, Profile & Shared"
        return "00 · Public"


def statements(text: str) -> list[str]:
    """Join the route file into complete PHP statements (they wrap over lines)."""
    out: list[str] = []
    buf = ""
    for raw in text.splitlines():
        line = raw.strip()
        if not line or line.startswith(("//", "/*", "*", "*/", "|", "#", "<?php")):
            continue
        buf = f"{buf} {line}".strip()
        # A statement ends at `;`, but `->group(function () {` opens a block
        # and `});` / `}` closes one — both must flush too.
        if buf.endswith(";") or line.endswith("{"):
            out.append(buf)
            buf = ""
        elif line in {"}", "});", "});"} or re.fullmatch(r"\}\)*;?", line):
            out.append(buf)
            buf = ""
    return out


def parse_routes() -> list[Route]:
    text = ROUTES.read_text()
    # Controller short-name -> "App\Http\Controllers\Api\..." mapping.
    imports: dict[str, str] = {}
    for match in USE_RE.finditer(text):
        clause = match.group(1).strip()
        if " as " in clause:
            fqcn, _, alias = clause.partition(" as ")
            imports[alias.strip()] = fqcn.strip()
            continue
        imports[clause.split("\\")[-1]] = clause

    stack: list[Group] = [Group()]
    routes: list[Route] = []
    pending_group: Group | None = None

    for stmt in statements(text):
        if GROUP_RE.search(stmt):
            group = Group()
            mw_m = MIDDLEWARE_RE.search(stmt)
            if mw_m:
                group.middleware = [
                    p.strip().strip("'\"")
                    for p in mw_m.group(1).split(",")
                    if p.strip()
                ]
            mw_s = MIDDLEWARE_STR_RE.search(stmt)
            if mw_s:
                group.middleware.append(mw_s.group(1))
            pf = PREFIX_RE.search(stmt)
            if pf:
                group.prefix = pf.group(1)
            stack.append(group)
            continue

        if re.fullmatch(r"\}\);?", stmt):
            if len(stack) > 1:
                stack.pop()
            continue

        current = stack[-1]
        # Groups inherit prefix/middleware from their parents.
        prefix = "".join(g.prefix for g in stack[1:])
        middleware: list[str] = []
        for g in stack[1:]:
            middleware.extend(g.middleware)

        res = RESOURCE_RE.search(stmt)
        if res:
            resource, controller = res.group(1), res.group(2)
            only = ONLY_RE.search(stmt)
            actions = (
                [a.strip().strip("'\"") for a in only.group(1).split(",") if a.strip()]
                if only
                else list(RESOURCE_ACTIONS)
            )
            for action in actions:
                if action not in RESOURCE_ACTIONS:
                    continue
                method, suffix = RESOURCE_ACTIONS[action]
                routes.append(
                    Route(
                        method=method,
                        path=f"/{resource}{suffix}",
                        controller=controller,
                        action=action,
                        name=".".join(p for p in (prefix, resource, action) if p),
                        group_prefix=prefix,
                        group_middleware=middleware,
                        from_resource=True,
                    )
                )
            continue

        verb = VERB_RE.search(stmt)
        if verb:
            name = NAME_RE.search(stmt)
            routes.append(
                Route(
                    method=verb.group(1).upper(),
                    path=verb.group(2),
                    controller=verb.group(3),
                    action=verb.group(4),
                    name=name.group(1) if name else "",
                    group_prefix=prefix,
                    group_middleware=middleware,
                )
            )

    for route in routes:
        route.controller = imports.get(route.controller, route.controller)
    return routes


# ---------------------------------------------------------------------------
# FormRequest -> example body
# ---------------------------------------------------------------------------

SAMPLE_VALUES: list[tuple[re.Pattern[str], Any]] = [
    (re.compile(r"confirmed$"), None),
    (re.compile(r"^email$"), "admin@synapse.test"),
    (re.compile(r"email$"), "admin@synapse.test"),
    (re.compile(r"^password$|password$"), "password123"),
    (re.compile(r"^token$"), "{{reset_token}}"),
    (re.compile(r"^phone$"), "+237 6 77 12 45 09"),
    (re.compile(r"^matricule$"), "ST2026101"),
    (re.compile(r"^staff_code$"), "TC-2026-014"),
    (re.compile(r"^code$"), "MAT"),
    (re.compile(r"^name$"), "Mary Bih"),
    (re.compile(r"name$"), "Mary Bih"),
    (re.compile(r"^title$"), "End of term assessment"),
    (re.compile(r"^slug$"), "aics"),
    (re.compile(r"^body$|^content$|^message$"), "Message body goes here."),
    (re.compile(r"^due_at$|^starts_at$|^ends_at$|^published_at$"), "2026-10-02T23:59:00Z"),
    (re.compile(r"_id$|^id$"), 1),
    (re.compile(r"^weight$"), 30),
    (re.compile(r"^sequence$|^position$"), 1),
]


def controller_file(controller_fqcn: str) -> Path | None:
    rel = controller_fqcn.replace("App\\", "app/").replace("\\", "/") + ".php"
    path = BACKEND / rel
    return path if path.exists() else None


def request_class_for(controller_fqcn: str, action: str) -> str | None:
    """Find the FormRequest type-hinted on a controller method, if any."""
    path = controller_file(controller_fqcn)
    if not path:
        return None
    source = path.read_text()
    imports = {
        (m.group(2) or m.group(1).split("\\")[-1]): m.group(1)
        for m in IMPORT_RE.finditer(source)
    }
    match = METHOD_RE.search(source)
    while match:
        if match.group(1) == action:
            for chunk in match.group(2).split(","):
                chunk = chunk.strip()
                if chunk.endswith("Request $request") or re.search(
                    r"(?<![\w\\])(\w*Request)\s+\$", chunk
                ):
                    short = re.sub(r".*?([\w]+Request).*", r"\1", chunk)
                    short = short.split("\\")[-1]
                    if short in imports:
                        return "App\\Http\\Requests\\" + imports[short]
        source = source[match.end():]
        match = METHOD_RE.search(source)
    return None


def rules_of(request_fqcn: str) -> list[tuple[str, list[str]]]:
    rel = request_fqcn.replace("App\\", "app/").replace("\\", "/") + ".php"
    path = BACKEND / rel
    if not path.exists():
        return []
    source = path.read_text()
    block = RULES_RE.search(source)
    if not block:
        return []
    rules: list[tuple[str, list[str]]] = []
    for line in block.group(1).splitlines():
        m = RULE_LINE_RE.search(line)
        if not m:
            continue
        field_name = m.group(1)
        if field_name.endswith(".*") or "*" in field_name:
            continue
        rules.append(
            (
                field_name,
                [r.strip().strip("'\"") for r in m.group(2).split(",") if r.strip()],
            )
        )
    return rules


def sample_value(field_name: str, rule_list: list[str]) -> Any:
    joined = " ".join(rule_list)
    if "nullable" in rule_list and "required" not in rule_list:
        # Keep optional fields visible but empty — Postman users delete them.
        pass
    if "boolean" in rule_list:
        return True
    if "array" in rule_list:
        return []
    if "numeric" in joined or "integer" in rule_list:
        return 1
    in_rule = re.search(r"(?:^|[|\s])in:([^'\"\s]+)", joined)
    if in_rule:
        return in_rule.group(1).split(",")[0]
    for pattern, value in SAMPLE_VALUES:
        if pattern.search(field_name):
            if pattern.pattern == r"confirmed$":
                continue
            return value
    if "date" in joined:
        return "2026-09-30"
    if "string" in rule_list:
        return "string"
    return None


def method_body(controller_fqcn: str, action: str) -> str:
    """Source of one controller method — used to read its real status code."""
    path = controller_file(controller_fqcn)
    if not path:
        return ""
    source = path.read_text()
    start = source.find(f"public function {action}(")
    if start == -1:
        return ""
    nxt = source.find("\n    public function", start + 10)
    return source[start : nxt if nxt != -1 else len(source)]


def success_status(controller_fqcn: str, action: str, method: str) -> int:
    """The status the controller actually returns, read from its source."""
    if method == "GET":
        return 200
    body = method_body(controller_fqcn, action)
    for code in (201, 202, 204, 200):
        if re.search(rf"\b{code}\b", body):
            return code
    return 200


def example_body(controller_fqcn: str, action: str) -> dict[str, Any] | None:
    request_fqcn = request_class_for(controller_fqcn, action)
    if not request_fqcn:
        return None
    body: dict[str, Any] = {}
    for field_name, rule_list in rules_of(request_fqcn):
        if "sometimes" in rule_list and "required" not in rule_list:
            continue
        value = sample_value(field_name, rule_list)
        if "." in field_name:
            head, _, tail = field_name.partition(".")
            body.setdefault(head, {})[tail] = value
            continue
        body[field_name] = value
    return body or None


# ---------------------------------------------------------------------------
# Postman building blocks
# ---------------------------------------------------------------------------

SAVE_TOKEN_SCRIPT = """// Capture the Sanctum token and the caller's own ids.
pm.test('Status is 200 OK', () => pm.response.to.have.status(200));
pm.test('Returns a bearer token', () => {
    const body = pm.response.json();
    pm.expect(body.token).to.be.a('string').and.to.have.lengthOf.above(20);
});

const body = pm.response.json();
pm.collectionVariables.set('token', body.token);
pm.collectionVariables.set('user_id', body.user.id);
pm.collectionVariables.set('school_id', body.user.school ? body.user.school.id : '');
"""

COMMON_TESTS = """// Every SYNAPSE endpoint answers JSON, fast, and with an expected status.
pm.test('Status code is {{EXPECTED}}', () => {
    pm.response.to.have.status({{EXPECTED}});
});
pm.test('Responds as JSON', () => {
    pm.expect(pm.response.headers.get('Content-Type')).to.include('json');
});
pm.test('Responds in under 800ms', () => {
    pm.expect(pm.response.responseTime).to.be.below(800);
});
"""

AUTH_TESTS = """pm.test('Rejects the request without a token', () => {
    pm.response.to.have.status(401);
});
pm.test('Explains why', () => {
    pm.expect(pm.response.json().message).to.include('Unauthenticated');
});
"""

LIST_TESTS = """
pm.test('Returns a paginated envelope', () => {
    const body = pm.response.json();
    pm.expect(body).to.have.property('data');
    pm.expect(body.meta).to.have.property('total');
    pm.expect(body.data.length).to.be.at.most(body.meta.per_page);
});
"""

CREATED_TESTS = """
pm.test('Returns 201 Created', () => pm.response.to.have.status(201));
pm.test('Echoes the new record with an id', () => {
    const data = pm.response.json().data;
    pm.expect(data).to.have.property('id');
});
pm.test('Id is available for the next request', () => {
    const data = pm.response.json().data;
    if (data && data.id) pm.collectionVariables.set('{{ID_VAR}}', data.id);
});
"""

VALIDATION_TESTS = """pm.test('Unprocessable Entity (422)', () => pm.response.to.have.status(422));
pm.test('Names every invalid field', () => {
    const errors = pm.response.json().errors;
    pm.expect(errors).to.be.an('object');
    pm.expect(Object.keys(errors)).to.include('{{FIELD}}');
});
"""

ID_VAR_BY_RESOURCE = {
    "students": "student_id",
    "teachers": "teacher_id",
    "classes": "class_id",
    "subjects": "subject_id",
    "academic-years": "academic_year_id",
    "exams": "exam_id",
    "schools": "school_id",
    "events": "event_id",
}


def postman_path(path: str) -> str:
    """Laravel ``{student}`` -> Postman ``:student``."""
    return re.sub(r"\{(\w+)(?::\w+)?\}", r":\1", path)


LITERAL_PARAMS = {"id", "code", "token", "slug", "type", "status", "sequence"}


def variable_name(path_param: str) -> str:
    """`quizAttempt` -> `quiz_attempt_id`, but `code`/`slug` stay as they are."""
    if path_param in LITERAL_PARAMS:
        return path_param
    snake = re.sub(r"(?<!^)(?=[A-Z])", "_", path_param).lower()
    return f"{snake}_id"


def substitute_ids(url: str) -> str:
    """Replace path params with collection variables seeded by the seeder."""

    def repl(match: re.Match[str]) -> str:
        param = match.group(1)
        return "{{" + variable_name(param) + "}}"

    return re.sub(r":(\w+)", repl, url)


LIST_QUERY = [("per_page", "15"), ("page", "1"), ("search", "")]
SEMESTER_QUERY = [("semester_id", "{{semester_id}}")]


def build_url(route: Route) -> str:
    path = postman_path(route.full_path)
    path = substitute_ids(path)
    url = "{{base_url}}/api" + path
    query = []
    if route.method == "GET" and route.action == "index":
        query.extend(LIST_QUERY)
    if route.method in {"GET", "POST"} and "gradebook" in path:
        query.extend(SEMESTER_QUERY)
    if query:
        url += "?" + "&".join(f"{k}={v}" for k, v in query)
    return url


def build_request(route: Route, seen: set[str]) -> dict[str, Any]:
    method = route.method
    action = route.action
    # Read the status off the controller: a create answers 201, an action
    # (chat, draft, publish, review…) answers 200. Never guessed.
    expected = success_status(route.controller, action, method)

    tests = COMMON_TESTS.replace("{{EXPECTED}}", str(expected))
    if method == "GET" and action == "index":
        tests += LIST_TESTS
    if expected == 201 and route.from_resource:
        resource = route.path.strip("/").split("/")[-1]
        tests += CREATED_TESTS.replace(
            "{{ID_VAR}}", ID_VAR_BY_RESOURCE.get(resource, "last_created_id")
        )

    body: dict[str, Any] | None = None
    if method in {"POST", "PUT", "PATCH"}:
        body = example_body(route.controller, action) or {
            "note": "Add the request payload — see the FormRequest rules."
        }

    name = route.name.replace("api.", "") if route.name else route.full_path
    label = f"{name}"

    item: dict[str, Any] = {
        "name": label,
        "request": {
            "method": method,
            "header": [
                {"key": "Accept", "value": "application/json"},
                {"key": "X-Requested-With", "value": "XMLHttpRequest"},
            ],
            "url": {
                "raw": build_url(route),
                "host": ["{{base_url}}"],
                "path": ["api"] + build_url(route).split("/api/")[-1].split("?")[0].split("/"),
                "query": [
                    {"key": k, "value": v, "disabled": v == ""}
                    for k, v in (
                        LIST_QUERY
                        if route.method == "GET" and route.action == "index"
                        else SEMESTER_QUERY
                        if route.method in {"GET", "POST"} and "gradebook" in route.full_path
                        else []
                    )
                ],
            },
            "description": (
                f"`{method} /api{route.full_path}`\n\n"
                f"Controller: `{route.controller.split(chr(92))[-1]}@{action}`\n"
                f"Route name: `{route.name}`\n"
                f"Middleware: {', '.join(route.group_middleware) or '—'}"
            ),
        },
        "event": [
            {"listen": "test", "script": {"type": "text/javascript", "exec": tests.splitlines()}}
        ],
        "_route": route,  # stripped before writing
    }

    if body is not None:
        item["request"]["header"].append(
            {"key": "Content-Type", "value": "application/json"}
        )
        item["request"]["body"] = {
            "mode": "raw",
            "raw": json.dumps(body, indent=2),
            "options": {"raw": {"language": "json"}},
        }

    if route.name == "api.login":
        item["event"] = [
            {
                "listen": "test",
                "script": {"type": "text/javascript", "exec": SAVE_TOKEN_SCRIPT.splitlines()},
            }
        ]

    if route.name in {"api.user", "api.logout"}:
        item["request"]["auth"] = {"type": "noauth"}
        item["event"] = [
            {
                "listen": "pre-request",
                "script": {
                    "type": "text/javascript",
                    "exec": ["// No token: this request proves the guard is on."],
                },
            },
            {
                "listen": "test",
                "script": {"type": "text/javascript", "exec": AUTH_TESTS.splitlines()},
            },
        ]

    seen.add(method + route.full_path)
    return item


def build_collection(routes: list[Route]) -> dict[str, Any]:
    folders: dict[str, list[dict[str, Any]]] = {}
    seen: set[str] = set()
    for route in routes:
        item = build_request(route, seen)
        folders.setdefault(route.folder, []).append(item)

    ordered = sorted(folders)
    items = []
    for folder in ordered:
        children = []
        for item in folders[folder]:
            item.pop("_route", None)
            children.append(item)
        items.append({"name": folder, "item": children})

    total = sum(len(v) for v in folders.values())
    collection: dict[str, Any] = {
        "info": {
            "_postman_id": "8f1c0e2a-4b7d-4f9c-9a1e-2d6c5b8a3f10",
            "name": COLLECTION_NAME,
            "description": (
                "Every REST endpoint exposed by SYNAPSE, generated from "
                "`backend/routes/api.php` by `tools/generate_postman_collection.py`.\n\n"
                f"{total} requests, grouped by the role that may call them. Expect "
                "401/403 where the guard is doing its job — those are the security "
                "tests, not failures.\n\n"
                "Getting started: pick the `SYNAPSE · Local` environment, run "
                "`Auth → login`, and the token is captured automatically for every "
                "other request."
            ),
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        },
        "auth": {
            "type": "bearer",
            "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}],
        },
        "item": items,
        "variable": [
            {"key": "base_url", "value": "http://localhost:8000"},
            {"key": "token", "value": ""},
            {"key": "user_id", "value": ""},
            {"key": "school_id", "value": "1"},
            {"key": "student_id", "value": "1"},
            {"key": "teacher_id", "value": "2"},
            {"key": "class_id", "value": "1"},
            {"key": "subject_id", "value": "1"},
            {"key": "academic_year_id", "value": "2"},
            {"key": "semester_id", "value": "1"},
            {"key": "exam_id", "value": "1"},
            {"key": "event_id", "value": "1"},
            {"key": "last_created_id", "value": ""},
            {"key": "reset_token", "value": ""},
            {"key": "code", "value": "SYN-2026-8FQ2K1"},
            {"key": "id", "value": "1"},
        ],
    }
    return collection


def build_environment(name: str, base_url: str) -> dict[str, Any]:
    return {
        "name": name,
        "values": [
            {"key": "base_url", "value": base_url, "type": "default", "enabled": True},
            {"key": "school_id", "value": "1", "type": "default", "enabled": True},
            {"key": "student_id", "value": "1", "type": "default", "enabled": True},
            {"key": "teacher_id", "value": "2", "type": "default", "enabled": True},
            {"key": "class_id", "value": "1", "type": "default", "enabled": True},
            {"key": "subject_id", "value": "1", "type": "default", "enabled": True},
            {"key": "academic_year_id", "value": "2", "type": "default", "enabled": True},
        ],
        "_postman_variable_scope": "environment",
        "_postman_exported_using": "SYNAPSE/generate_postman_collection.py",
    }


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    routes = parse_routes()
    collection = build_collection(routes)
    (OUT_DIR / "SYNAPSE-API.postman_collection.json").write_text(
        json.dumps(collection, indent=2) + "\n"
    )
    (OUT_DIR / "SYNAPSE-Local.postman_environment.json").write_text(
        json.dumps(build_environment("SYNAPSE · Local", "http://localhost:8000"), indent=2) + "\n"
    )
    (OUT_DIR / "SYNAPSE-Mock.postman_environment.json").write_text(
        json.dumps(build_environment("SYNAPSE · Mock", "http://127.0.0.1:8099"), indent=2) + "\n"
    )
    folders = {
        item["name"]: len(item["item"]) for item in collection["item"]
    }
    print(f"routes parsed : {sum(folders.values())}")
    for name, count in folders.items():
        print(f"  {name}: {count}")


if __name__ == "__main__":
    main()
