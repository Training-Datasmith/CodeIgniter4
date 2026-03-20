# CodeIgniter 4 Framework Architecture

## Purpose

CodeIgniter 4 is a lightweight, high-performance PHP framework aimed at developers who need a small
footprint, no configuration, and clear documentation. It targets PHP 8.1+ and provides a cohesive set
of HTTP, database, routing, validation, and CLI tools without requiring a dependency injection container.

## Directory Structure

```
system/
  Config/            Framework-level configuration classes and registrar
  Controller.php     Base controller (thin — most logic lives in services/models)
  Database/          Database abstraction and query builder
    Base_Builder.php   Platform-agnostic query builder — SELECT/INSERT/UPDATE/DELETE
    Base_Connection.php Abstract database connection with lazy initialisation
    Base_Result.php    Cursor-like result set wrapper
    MySQLi/            MySQLi-specific driver implementations
    Postgre/           PostgreSQL-specific driver implementations
    SQLite3/           SQLite 3 driver implementations
    SQLSRV/            SQL Server driver implementations
    OCI8/              Oracle OCI8 driver implementations
    Migration.php      Single migration base class
    Migration_Runner.php Discovers and applies pending migrations
    Seeder.php         Base class for database seed files
  Events/            Synchronous publish-subscribe event bus
  Exceptions/        Base and shared exception hierarchy
  HTTP/              Request/response layer (PSR-7-inspired but not PSR-7)
    Incoming_Request.php  Server-side HTTP request (headers, body, files, locale)
    Response.php          Outgoing HTTP response with cookie/header helpers
    Redirect_Response.php Fluent redirect builder
    Download_Response.php Force-download response type
    URI.php               URI parser and builder
    Site_URI.php          URI extended with app base-URL awareness
    Negotiate.php         Content negotiation (media, charset, language, encoding)
    Content_Security_Policy.php  CSP header builder
    Cors.php              CORS policy evaluator
    Method.php            HTTP method enum (backed: string)
    Message_Trait.php     Shared header/body logic for Request and Response
    Files/                Uploaded file collection and single-file abstraction
  Router/            Route collection, auto-routing, and route matching
  Security/          CSRF, sanitisation helpers, and Content Security Policy
  Session/           Session handler abstraction (files, database, Redis, Memcached)
  Validation/        Rule-based input validation engine
```

## Key Design Decisions

- **No DI container by default**: Services are resolved through `Config\Services` (a static registry
  of factory methods). This keeps the framework simple but couples callsites to the registry.
- **Lazy database connection**: `Base_Connection` does not open the socket in `__construct()` — the
  connection is established on the first query call, avoiding overhead for CLI commands that skip the DB.
- **Mutable request/response**: Unlike PSR-7, CodeIgniter's `Request` and `Response` are mutable objects.
  This simplifies code but means middleware must be careful not to share state unintentionally.
- **Driver hierarchy**: Each database engine provides `Connection`, `Builder`, `Result`, `Forge`,
  `PreparedQuery`, and `Utils` subclasses — all sharing a common abstract base with engine-specific
  overrides confined to a single subdirectory.
- **HTTP Method enum**: `Method` is a string-backed enum providing IDE-discoverable constants for all
  standard HTTP verbs, replacing the old class-constant approach.

## Extension Points

| Mechanism | How to extend |
|-----------|---------------|
| Custom DB driver | Extend `Base_Connection`, `Base_Builder`, etc. in a new namespace |
| Custom validation rule | Implement `RuleInterface` or register a closure in `Validation` config |
| Custom filter (middleware) | Implement `FilterInterface`, register in `app/Config/Filters.php` |
| Custom exception handler | Extend `BaseExceptionHandler`, configure in `app/Config/Exceptions.php` |
| Custom session handler | Implement `SessionHandlerInterface`, configure in `app/Config/Session.php` |
| Database migration | Extend `Migration`, place in `app/Database/Migrations/` |
| Database seeder | Extend `Seeder`, place in `app/Database/Seeds/` |

## Dependency Flow

```
Entry point (index.php)
  └─ CodeIgniter (bootstrap)
       ├─ Config\Routes → Router
       ├─ Config\Filters → FilterRunner (before/after request)
       └─ Controller
            ├─ IncomingRequest  (HTTP layer)
            ├─ Response         (HTTP layer)
            └─ Model
                 └─ BaseConnection (Database)
                      └─ BaseBuilder (Query Builder)
```

## Key Classes

- `CodeIgniter\HTTP\IncomingRequest` — encapsulates all data sent to the server (headers, body, files, locale)
- `CodeIgniter\HTTP\Response` — outgoing response with fluent cookie, header and body helpers
- `CodeIgniter\Database\BaseConnection` — abstract driver managing connection lifecycle and query execution
- `CodeIgniter\Database\BaseBuilder` — SQL query builder producing safe, parameterised SQL
- `CodeIgniter\Database\Migration` — base for schema-change migration scripts
