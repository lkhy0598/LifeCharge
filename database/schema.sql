USE lifecharge;

CREATE TABLE games (
    id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    title_zh VARCHAR(200) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    rating DECIMAL(2, 1) NOT NULL,
    release_date DATE NOT NULL,
    developer VARCHAR(200) NOT NULL,
    publisher VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    detail_description TEXT NOT NULL,
    genre JSON NOT NULL,
    tags JSON NOT NULL,
    status JSON NOT NULL,
    cover VARCHAR(255) NOT NULL,
    banner VARCHAR(255) NOT NULL,
    screenshots JSON NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT chk_games_price
        CHECK (price >= 0),

    CONSTRAINT chk_games_rating
        CHECK (rating BETWEEN 0 AND 5),

    CONSTRAINT chk_games_genre
        CHECK (JSON_TYPE(genre) = 'ARRAY'),

    CONSTRAINT chk_games_tags
        CHECK (JSON_TYPE(tags) = 'ARRAY'),

    CONSTRAINT chk_games_status
        CHECK (JSON_TYPE(status) = 'ARRAY'),

    CONSTRAINT chk_games_screenshots
        CHECK (
            JSON_TYPE(screenshots) = 'ARRAY'
            AND JSON_LENGTH(screenshots) = 5
        )
) ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;


CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    avatar VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT uq_users_username
        UNIQUE (username),

    CONSTRAINT uq_users_email
        UNIQUE (email),

    CONSTRAINT chk_users_username_length
        CHECK (CHAR_LENGTH(username) BETWEEN 3 AND 50),

    CONSTRAINT chk_users_username_at
        CHECK (LOCATE('@', username) = 0),

    CONSTRAINT chk_users_display_name
        CHECK (CHAR_LENGTH(display_name) BETWEEN 1 AND 100)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;


CREATE TABLE orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    request_key VARCHAR(64)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    currency CHAR(3)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(16)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT uq_orders_user_request
        UNIQUE (user_id, request_key),

    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT chk_orders_request_key
        CHECK (CHAR_LENGTH(request_key) > 0),

    CONSTRAINT chk_orders_total
        CHECK (total_amount >= 0),

    CONSTRAINT chk_orders_currency
        CHECK (currency = 'TWD'),

    CONSTRAINT chk_orders_status
        CHECK (status = 'completed')
) ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;



CREATE TABLE order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    title_zh VARCHAR(200) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,

    PRIMARY KEY (id),

    CONSTRAINT uq_order_items_order_game
        UNIQUE (order_id, game_id),

    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id)
        REFERENCES orders (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_order_items_game
        FOREIGN KEY (game_id)
        REFERENCES games (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT chk_order_items_price
        CHECK (unit_price >= 0)
) ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;


CREATE TABLE user_games (
    user_id INT UNSIGNED NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    source VARCHAR(16)
        CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    order_item_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,

    PRIMARY KEY (user_id, game_id),

    CONSTRAINT uq_user_games_order_item
        UNIQUE (order_item_id),

    CONSTRAINT fk_user_games_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_user_games_game
        FOREIGN KEY (game_id)
        REFERENCES games (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT fk_user_games_order_item
        FOREIGN KEY (order_item_id)
        REFERENCES order_items (id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,

    CONSTRAINT chk_user_games_source
        CHECK (
            (source = 'import' AND order_item_id IS NULL)
            OR
            (source = 'purchase' AND order_item_id IS NOT NULL)
        )
) ENGINE = InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;