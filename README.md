# Nosework Competition Information System

A high-quality, object-oriented PHP application for managing nosework competition results using SQLite database.

## Features

- Manage multiple competitions
- Store multiple categories per competition
- Category rules include time limit, number of hides, maximum score and penalty configuration
- Support flat and progressive penalty types per penalty rule within a category
- Store participant results per category with penalty score calculation
- Sorted results display: by found items (descending), then time (ascending), then total score (descending)
- SQLite database for data persistence

## Architecture

The system follows SOLID principles with clean separation of concerns:

- **Competition**: Domain model для соревнования
- **Category**: Domain model для категории с правилами и штрафами
- **PenaltyRule**: Domain model для правила штрафа
- **Result**: Domain model для результата участника
- **DatabaseManager**: Data access layer для SQLite операций
- **CompetitionService**: Бизнес-логика управления соревнованиями, категориями и результатами

## Installation

1. Ensure PHP 8.0+ is installed
2. Install dependencies:
   ```bash
   composer install
   ```

## Usage

Start a local PHP server from the project root:
```bash
php -S localhost:8000
```

Then open the app in your browser:
- `http://localhost:8000/index.php` — главная страница
- `http://localhost:8000/manage.php` — создание соревнований и категорий
- `http://localhost:8000/results.php` — просмотр и добавление результатов

This will create sample data and let you navigate between pages.

## Database Schema

```sql
CREATE TABLE competitions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT NOT NULL
);

CREATE TABLE categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    competition_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    time_limit REAL NOT NULL,
    hides_count INTEGER NOT NULL,
    max_score REAL NOT NULL,
    penalty_type TEXT NOT NULL
);

CREATE TABLE penalty_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    points REAL NOT NULL,
    sequence_index INTEGER NOT NULL
);

CREATE TABLE results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category_id INTEGER NOT NULL,
    participant_name TEXT NOT NULL,
    time REAL NOT NULL,
    found_items INTEGER NOT NULL,
    penalties INTEGER NOT NULL,
    penalty_score REAL NOT NULL,
    total_score REAL NOT NULL,
    created_at TEXT NOT NULL
);
```

## Scoring Formula

Total score = max_score + found_items - penalty_score - (time / 60)

The current category rules are defined in `Category::calculateTotalScore()`, while penalty logic is handled by `Category::calculatePenaltyScore()` depending on penalty type.