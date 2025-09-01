CREATE TABLE player (
    player_id INTEGER PRIMARY KEY AUTOINCREMENT,
    alias TEXT NOT NULL UNIQUE,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    image_path TEXT NOT NULL DEFAULT 'url/a/imagen/por/defecto.png',
    creation_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE game (
    game_id INTEGER PRIMARY KEY AUTOINCREMENT,
    date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    duration INTEGER NOT NULL,
    player1_id INTEGER NOT NULL,
    player2_id INTEGER NOT NULL,
    player3_id INTEGER,
    player4_id INTEGER,
    player1_score INTEGER NOT NULL,
    player2_score INTEGER NOT NULL,
    player3_score INTEGER,
    player4_score INTEGER,
    winner_id INTEGER,
    tournament_id INTEGER,
    FOREIGN KEY (player1_id) REFERENCES player(player_id),
    FOREIGN KEY (player2_id) REFERENCES player(player_id),
    FOREIGN KEY (player3_id) REFERENCES player(player_id),
    FOREIGN KEY (player4_id) REFERENCES player(player_id),
    FOREIGN KEY (winner_id) REFERENCES player(player_id),
    FOREIGN KEY (tournament_id) REFERENCES tournament(tournament_id)
);

CREATE TABLE tournament (
    tournament_id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    start_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_date DATETIME,
    winner_id INTEGER,
    blockchain_hash TEXT,
    FOREIGN KEY (winner_id) REFERENCES player(player_id)
);

CREATE TABLE tournament_participants (
    tournament_id INTEGER NOT NULL,
    player_id INTEGER NOT NULL,
    final_score INTEGER,
    final_position INTEGER,
    FOREIGN KEY (tournament_id) REFERENCES tournament(tournament_id),
    FOREIGN KEY (player_id) REFERENCES player(player_id),
    PRIMARY KEY (tournament_id, player_id)
);

CREATE TABLE friends (
    player1_id INTEGER NOT NULL,
    player2_id INTEGER NOT NULL,
    request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (player1_id) REFERENCES player(player_id),
    FOREIGN KEY (player2_id) REFERENCES player(player_id),
    PRIMARY KEY (player1_id, player2_id)
);

CREATE TABLE chat (
    chat_id INTEGER PRIMARY KEY AUTOINCREMENT,
    is_group INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE messages (
    message_id INTEGER PRIMARY KEY AUTOINCREMENT,
    chat_id INTEGER NOT NULL,
    sender_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chat(chat_id),
    FOREIGN KEY (sender_id) REFERENCES player(player_id)
);

CREATE TABLE blocked_players (
    blocker_id INTEGER NOT NULL,
    blocked_id INTEGER NOT NULL,
    block_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blocker_id) REFERENCES player(player_id),
    FOREIGN KEY (blocked_id) REFERENCES player(player_id),
    PRIMARY KEY (blocker_id, blocked_id)
);

CREATE TABLE ia_configuration (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parameter_name TEXT NOT NULL UNIQUE,
    parameter_value TEXT NOT NULL
);

CREATE TABLE game_events (
    event_id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL,
    timestamp INTEGER NOT NULL,
    ball_x INTEGER NOT NULL,
    ball_y INTEGER NOT NULL,
    paddle1_y INTEGER NOT NULL,
    paddle2_y INTEGER NOT NULL,
    paddle3_y INTEGER,
    paddle4_y INTEGER,
    FOREIGN KEY (game_id) REFERENCES game(game_id)
);

CREATE TABLE player_configuration (
    player_id INTEGER PRIMARY KEY,
    paddle_color TEXT NOT NULL DEFAULT '#00E0FF',
    board_color TEXT NOT NULL DEFAULT '#0d0c1d',
    ball_color TEXT NOT NULL DEFAULT '#FFFFFF',
    cheat_unlocked TEXT,
    ia_difficulty TEXT NOT NULL DEFAULT 'medium',
    FOREIGN KEY (player_id) REFERENCES player(player_id)
);

CREATE TABLE tournament_configuration (
    config_id INTEGER PRIMARY KEY AUTOINCREMENT,
    tournament_id INTEGER NOT NULL UNIQUE,
    victory_condition TEXT NOT NULL DEFAULT 'score',
    win_score INTEGER,
    game_duration_seconds INTEGER,
    tiebreaker TEXT,
    max_players INTEGER NOT NULL DEFAULT 2,
    powerups_enabled INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (tournament_id) REFERENCES tournament(tournament_id)
);

CREATE TABLE audit_log (
    log_id INTEGER PRIMARY KEY AUTOINCREMENT,
    table_name TEXT NOT NULL,
    record_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    old_value TEXT,
    new_value TEXT,
    actor_id INTEGER,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_id) REFERENCES player(player_id)
);
