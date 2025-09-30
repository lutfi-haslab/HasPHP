-- HasPHP Demo Database Schema

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255),
    bio TEXT,
    active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Posts table
CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content TEXT NOT NULL,
    excerpt TEXT,
    user_id INTEGER NOT NULL,
    published BOOLEAN DEFAULT 0,
    featured BOOLEAN DEFAULT 0,
    views_count INTEGER DEFAULT 0,
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Comments table
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    approved BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tags table
CREATE TABLE IF NOT EXISTS tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    color VARCHAR(7) DEFAULT '#3B82F6',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Post-Tag pivot table
CREATE TABLE IF NOT EXISTS post_tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    UNIQUE(post_id, tag_id)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_posts_user_id ON posts(user_id);
CREATE INDEX IF NOT EXISTS idx_posts_published ON posts(published);
CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts(slug);
CREATE INDEX IF NOT EXISTS idx_comments_post_id ON comments(post_id);
CREATE INDEX IF NOT EXISTS idx_comments_user_id ON comments(user_id);
CREATE INDEX IF NOT EXISTS idx_post_tags_post_id ON post_tags(post_id);
CREATE INDEX IF NOT EXISTS idx_post_tags_tag_id ON post_tags(tag_id);

-- Insert demo data
INSERT OR IGNORE INTO users (id, name, email, password, bio, active, created_at, updated_at) VALUES 
(1, 'John Doe', 'john@hasphp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Full-stack developer and HasPHP framework contributor', 1, datetime('now'), datetime('now')),
(2, 'Jane Smith', 'jane@hasphp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Frontend designer and UX specialist', 1, datetime('now'), datetime('now')),
(3, 'Mike Johnson', 'mike@hasphp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Backend developer and database expert', 1, datetime('now'), datetime('now'));

INSERT OR IGNORE INTO tags (id, name, slug, color) VALUES
(1, 'PHP', 'php', '#777BB4'),
(2, 'Framework', 'framework', '#FF6B6B'),
(3, 'Tutorial', 'tutorial', '#4ECDC4'),
(4, 'Performance', 'performance', '#45B7D1'),
(5, 'API', 'api', '#96CEB4');

INSERT OR IGNORE INTO posts (id, title, slug, content, excerpt, user_id, published, featured, published_at) VALUES
(1, 'Welcome to HasPHP Framework', 'welcome-to-hasphp-framework', 
 'HasPHP is a modern, high-performance PHP framework that combines the best of Laravel''s developer experience with the speed of OpenSwoole. This post introduces you to the key features and benefits of using HasPHP for your next project.

## Key Features

- **High Performance**: Built on OpenSwoole for lightning-fast response times
- **Laravel-like Syntax**: Familiar API for Laravel developers
- **Modern Architecture**: PSR-4 autoloading, dependency injection, and service containers
- **Multi-Database Support**: SQLite, MySQL, and PostgreSQL out of the box

## Getting Started

Setting up HasPHP is simple. Just clone the repository and run the setup commands...', 
 'An introduction to HasPHP, a modern PHP framework combining Laravel''s elegance with OpenSwoole''s performance.', 
 1, 1, 1, datetime('now')),

(2, 'Building APIs with HasPHP', 'building-apis-with-hasphp',
 'Creating robust APIs is a breeze with HasPHP. This tutorial walks you through building a complete REST API from scratch, including authentication, validation, and error handling.

## Setting Up Routes

HasPHP uses a simple and intuitive routing system:

```php
Router::get(''/api/posts'', ''PostController@index'');
Router::post(''/api/posts'', ''PostController@store'');
```

## Model Relationships

Define relationships just like in Laravel:

```php
public function user() {
    return $this->belongsTo(User::class);
}
```',
 'Learn how to build powerful REST APIs using HasPHP''s intuitive routing and ORM system.',
 1, 1, 0, datetime('now', '-1 day')),

(3, 'Database Performance Tips', 'database-performance-tips',
 'Optimizing database performance is crucial for any web application. Here are some tips and tricks for getting the most out of your HasPHP database queries.

## Query Optimization

- Use proper indexing
- Avoid N+1 queries with eager loading
- Use query builder for complex queries
- Consider caching for frequently accessed data

## Connection Pooling

HasPHP supports connection pooling out of the box, which can significantly improve performance in high-traffic scenarios.',
 'Essential tips for optimizing database performance in HasPHP applications.',
 3, 1, 0, datetime('now', '-2 days')),

(4, 'Frontend Integration Guide', 'frontend-integration-guide',
 'HasPHP seamlessly integrates with modern frontend frameworks and libraries. This guide shows you how to build SPAs with HasPHP as the backend.

## Alpine.js Integration

HasPHP provides built-in support for Alpine.js, making it easy to add interactivity to your pages without the complexity of a full SPA framework.

## Tailwind CSS

Style your applications with Tailwind CSS, which integrates perfectly with HasPHP''s component system.',
 'Complete guide to integrating modern frontend tools with HasPHP.',
 2, 1, 0, datetime('now', '-3 days'));

INSERT OR IGNORE INTO post_tags (post_id, tag_id) VALUES
(1, 1), (1, 2), (1, 3),
(2, 1), (2, 5), (2, 3),
(3, 1), (3, 4),
(4, 1), (4, 2);

INSERT OR IGNORE INTO comments (post_id, user_id, content) VALUES
(1, 2, 'Great introduction! I''m excited to try HasPHP on my next project.'),
(1, 3, 'The performance improvements over traditional PHP frameworks are impressive.'),
(2, 2, 'This API tutorial is exactly what I needed. Thanks!'),
(2, 3, 'The Laravel-like syntax makes migration from existing projects much easier.'),
(3, 1, 'These performance tips saved me hours of debugging. Excellent post!'),
(4, 1, 'Alpine.js integration is a game-changer for quick interactive features.');
