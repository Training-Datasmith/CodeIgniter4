<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 — Database migrations and seeding example.
 *
 * Shows how to write a migration class and a seed class following
 * CodeIgniter 4 conventions. Place migration files in:
 *   app/Database/Migrations/
 * and seed files in:
 *   app/Database/Seeds/
 *
 * Run via Spark CLI:
 *   php spark migrate
 *   php spark db:seed PostsSeeder
 */

// ===== Migration: CreatePostsTable ===========================================

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the `posts` table.
 *
 * Run:  php spark migrate
 * Roll back: php spark migrate:rollback
 */
class CreatePostsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'body' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'published', 'archived'],
                'default'    => 'draft',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true); // primary key
        $this->forge->addKey('status');   // index for status filter queries
        $this->forge->createTable('posts');
    }

    public function down(): void
    {
        $this->forge->dropTable('posts', ifExists: true);
    }
}

// ===== Seeder: PostsSeeder ===================================================

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the `posts` table with sample data.
 *
 * Run:
 *   php spark db:seed PostsSeeder
 */
class PostsSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            [
                'title'      => 'Welcome to CodeIgniter 4',
                'body'       => 'This is the first seeded post.',
                'status'     => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title'      => 'Getting started with the Query Builder',
                'body'       => 'The fluent query builder makes SQL safe and readable.',
                'status'     => 'published',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title'      => 'Draft post',
                'body'       => 'This post has not been published yet.',
                'status'     => 'draft',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $this->db->table('posts')->insertBatch($data);
        echo "Seeded " . count($data) . " posts.\n";
    }
}
