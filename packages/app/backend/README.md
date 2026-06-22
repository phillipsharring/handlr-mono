# Handlr App Skeleton

![Handlr](handlr.png)

Starter project for building applications with [Handlr Framework](https://github.com/phillipsharring/handlr-framework), a lightweight PHP middleware-style framework.

## Installation

### Using the Handlr Installer (recommended)
```bash
composer global require phillipsharring/handlr-installer:dev-main --prefer-stable

handlr new my-project
```

### Using Composer
```bash
composer create-project phillipsharring/handlr-app my-project
```

### Cloning the repository
```bash
git clone git@github.com:phillipsharring/handlr-app.git my-project
cd my-project
rm -rf .git
composer install
git init
```

## Project Structure

```
app/                  Application code (PSR-4: App\)
  Auth/               Authentication (login, signup, logout, data layer)
  Events/             Event listener registration
  Users/              User CRUD handlers
  Roles/              Role & permission CRUD handlers
bootstrap.php         DI container setup, config loading, event registration
migrations/           Database migrations
seeds/                Database seeders
public/               Web root (index.php)
logs/                 Application logs
tests/                Pest test suite
```

## Configuration

Copy `.env.example` to `.env` and configure your database connection:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_app
DB_USERNAME=root
DB_PASSWORD=
```

## Development

### Local Server
```bash
composer run dev
```
Starts PHP's built-in server at `http://localhost:8000`.

### Database
```bash
composer run migrate          # Run next pending migration
composer run migrate:fresh    # Drop all tables and re-run all migrations
composer run migrate:rollback # Roll back the last migration
composer run seed             # Run seeders
composer run seed:fresh       # Truncate seeded tables and re-seed
composer run fresh            # migrate:fresh + seed:fresh
```

### Code Generation
```bash
composer run make:migration CreateItemsTable
composer run make:record Item
composer run make:table ItemsTable
composer run make:handler CreateItem
composer run make:pipe PostCreateItem
composer run make:scaffold Item    # All of the above at once
```

### Tests
```bash
composer run test
```

## Architecture

This skeleton follows the Handlr convention of organizing code by **feature**, not by type:

```
app/
  Users/
    CreateUser/
      PostCreateUser.php         (Pipe  - HTTP layer)
      CreateUserHandler.php      (Handler  - business logic)
      CreateUserInput.php        (HandlerInput  - validated input)
    Read/
      GetUsersList.php           (Pipe  - list endpoint)
      GetOneUser.php             (Pipe  - detail endpoint)
    Data/
      UsersTable.php             (Table  - CRUD)
      UsersAdminQuery.php        (Query  - complex reads)
    Domain/
      UserRecord.php             (Record  - domain object)
```

**Pipes** handle HTTP (extract input, call handler, format response).
**Handlers** contain business logic (receive typed input, return result).
Routes wire pipes together in `app/routes.php`.

## What's Included

- User, role, and permission management (CRUD + assignment)
- Session-based authentication (login, logout, signup)
- CSRF protection with token rotation
- CORS handling (same-origin)
- Event system with listener registration
- Database migrations for users, roles, permissions, and sessions

## License

MIT
