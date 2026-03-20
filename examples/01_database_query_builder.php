<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 — Database query builder example.
 *
 * Demonstrates the most common query builder patterns: SELECT with conditions,
 * INSERT, UPDATE, DELETE and prepared queries using CodeIgniter's database layer.
 *
 * Prerequisites:
 *   - CodeIgniter 4 installed via Composer
 *   - A MySQL/MariaDB database named `ci4_example` with a `posts` table:
 *
 *       CREATE TABLE posts (
 *           id     INT AUTO_INCREMENT PRIMARY KEY,
 *           title  VARCHAR(255) NOT NULL,
 *           body   TEXT,
 *           status VARCHAR(20) NOT NULL DEFAULT 'draft',
 *           created_at DATETIME DEFAULT CURRENT_TIMESTAMP
 *       );
 *
 * Run inside a CodeIgniter 4 application's public/ directory:
 *   php examples/01_database_query_builder.php
 */

// Minimal bootstrap — adjust path as needed.
define('FCPATH', __DIR__ . '/../public/');
require __DIR__ . '/../vendor/autoload.php';

use CodeIgniter\Database\Database;

// 1. Build a connection using the default config from app/Config/Database.php.
$db = Database::connect();

// 2. INSERT a row using the query builder.
$db->table('posts')->insert([
    'title'  => 'Hello CodeIgniter 4',
    'body'   => 'Query builder example',
    'status' => 'published',
]);
$newId = $db->insertID();
echo "Inserted post ID: {$newId}\n";

// 3. SELECT with conditions, ordering and limiting.
$posts = $db->table('posts')
    ->select('id, title, status, created_at')
    ->where('status', 'published')
    ->orderBy('created_at', 'DESC')
    ->limit(5)
    ->get()
    ->getResultArray();

echo "Published posts (last 5):\n";
foreach ($posts as $post) {
    echo "  [{$post['id']}] {$post['title']}\n";
}

// 4. UPDATE a row.
$db->table('posts')
    ->where('id', $newId)
    ->update(['status' => 'draft']);
echo "Updated post #{$newId} to draft.\n";

// 5. Prepared query using query binding for raw SQL (safe against injection).
$result = $db->query(
    'SELECT id, title FROM posts WHERE status = ? ORDER BY id DESC LIMIT ?',
    ['draft', 10]
);
echo "Draft posts via prepared query:\n";
foreach ($result->getResultArray() as $row) {
    echo "  [{$row['id']}] {$row['title']}\n";
}

// 6. DELETE.
$db->table('posts')->where('id', $newId)->delete();
echo "Deleted post #{$newId}.\n";

// 7. Transaction example.
$db->transStart();
$db->table('posts')->insert(['title' => 'TX row 1', 'status' => 'draft']);
$db->table('posts')->insert(['title' => 'TX row 2', 'status' => 'draft']);
$success = $db->transComplete();
echo 'Transaction ' . ($success ? 'committed' : 'rolled back') . ".\n";
