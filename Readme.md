# HasPHP Framework

HasPHP is a high-performance PHP framework built on OpenSwoole, designed for building scalable and efficient web applications and APIs. It comes with built-in support for routing, database operations, and template rendering using Twig.

## Features

- 🚀 Built on OpenSwoole for high performance
- 🛣️ Simple and intuitive routing system
- 🗃️ Database abstraction with multiple driver support (SQLite included)
- 📝 Template engine support with Twig
- 🔐 JWT authentication support
- 🛠️ Command-line interface for common tasks
- 📦 PSR-4 autoloading

## Requirements

- PHP 8.0 or higher
- OpenSwoole extension
- Composer

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/your-username/hasphp.git
   cd hasphp
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Set up the database (SQLite by default):
   ```bash
   mkdir -p database
   touch database/database.sqlite
   ```

## Project Structure

```
hasphp/
├── app/                    # Application code
│   ├── Controllers/       # Controller classes
│   ├── Core/              # Framework core
│   │   ├── DB/            # Database components
│   │   │   └── Drivers/   # Database drivers
│   │   ├── Middleware/    # Middleware classes
│   │   └── Internal/      # Internal framework classes
├── cli/                   # Command-line tools
│   └── Commands/         # CLI command classes
├── database/              # Database files and migrations
│   └── migrations/       # Database migration files
├── routes/               # Route definitions
├── views/                # Template files
├── composer.json         # Composer configuration
└── Readme.md            # This file
```

## Usage

### Basic Routing

Define routes in `routes/web.php`:

```php
use Hasphp\App\Core\Router;

// Simple GET route
Router::get('/', 'HomeController@index');

// Route with OpenAPI documentation
Router::get('/hello', 'HomeController@hello', [], [
    'tags' => ['Home'],
    'summary' => 'Say Hello',
    'description' => 'Returns a plain text greeting',
    'responses' => [
        200 => ['type' => 'string']
    ]
]);

// POST route with request body validation
Router::post('/login', 'AuthController@login', [], [
    'tags' => ['Auth'],
    'summary' => 'User login',
    'description' => 'Authenticate user and return a token'
]);
```

### Database Operations

Example model usage:

```php
use Hasphp\App\Core\DB\Drivers\SQLiteDriver;

// Initialize database connection
$db = new SQLiteDriver();

// Execute a query
$stmt = $db->pdo()->query('SELECT * FROM users');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Execute a prepared statement
$stmt = $db->pdo()->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
$stmt->execute(['John Doe', 'john@example.com']);
```

### Running the Application

Start the OpenSwoole server:

```bash
php public/index.php
```

The application will be available at `http://localhost:9501` by default.

## Configuration

### Database

Edit the database connection settings in `app/Core/DB/Drivers/SQLiteDriver.php` for SQLite, or create a new driver class for other databases.

### Environment Variables

Create a `.env` file in the root directory with your environment-specific settings:

```
APP_ENV=development
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/your/database.sqlite
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- OpenSwoole for the high-performance coroutine server
- Twig for template engine
- Firebase JWT for authentication